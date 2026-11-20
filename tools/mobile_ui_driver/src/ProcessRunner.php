<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use RuntimeException;

/** Runs bounded foreground commands and owns the isolated background processes. */
class ProcessRunner
{
    /** @param list<string> $command @param array<string, string> $environment */
    public function run(array $command, int $timeoutSeconds = 30, array $environment = []): ProcessResult
    {
        if ($command === []) {
            throw new RuntimeException('Cannot run an empty command.');
        }

        $stdoutPath = tempnam(sys_get_temp_dir(), 'rtm-mobile-out-');
        if ($stdoutPath === false) {
            throw new RuntimeException('Could not create temporary process output files.');
        }
        $stderrPath = tempnam(sys_get_temp_dir(), 'rtm-mobile-err-');
        if ($stderrPath === false) {
            unlink($stdoutPath);
            throw new RuntimeException('Could not create temporary process output files.');
        }

        try {
            return $this->withEnvironment($environment, fn (): ProcessResult => $this->execute(
                $command,
                $timeoutSeconds,
                $stdoutPath,
                $stderrPath,
            ));
        } finally {
            @unlink($stdoutPath);
            @unlink($stderrPath);
        }
    }

    /** @param list<string> $command */
    private function execute(array $command, int $timeoutSeconds, string $stdoutPath, string $stderrPath): ProcessResult
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['file', $stdoutPath, 'w'], 2 => ['file', $stderrPath, 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start command: ' . $command[0]);
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + $timeoutSeconds;
        $exitCode = -1;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                proc_close($process);
                throw new RuntimeException('Command timed out after ' . $timeoutSeconds . ' seconds: ' . $command[0]);
            }
            usleep(100_000);
        } while (true);

        $closedExitCode = proc_close($process);
        if ($exitCode < 0) {
            $exitCode = $closedExitCode;
        }
        $stdout = file_get_contents($stdoutPath);
        $stderr = file_get_contents($stderrPath);

        return new ProcessResult(($stdout ?: '') . ($stderr ?: ''), $exitCode);
    }

    /** @param list<string> $command @param array<string, string> $environment */
    public function start(array $command, string $workingDirectory, array $environment = []): int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('The mobile UI driver currently starts background processes on Windows only.');
        }
        if ($command === []) {
            throw new RuntimeException('Cannot start an empty command.');
        }

        return $this->withEnvironment($environment, function () use ($command, $workingDirectory): int {
            $arguments = array_map(self::quoteWindowsArgument(...), array_slice($command, 1));
            $script = '$process = Start-Process -FilePath ' . self::quotePowerShell($command[0]);
            if ($arguments !== []) {
                $script .= ' -ArgumentList ' . self::quotePowerShell(implode(' ', $arguments));
            }
            $script .= ' -WorkingDirectory ' . self::quotePowerShell($workingDirectory)
                . ' -WindowStyle Hidden -PassThru; [Console]::Out.WriteLine($process.Id)';
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $result = $this->run(['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encoded]);
            if (!$result->isSuccessful() || preg_match('/\b(\d+)\b/', $result->output, $matches) !== 1) {
                throw new RuntimeException('Could not start background process: ' . trim($result->output));
            }

            return (int) $matches[1];
        });
    }

    public function stop(int $processId): void
    {
        if ($processId < 1) {
            return;
        }
        $result = $this->run(['taskkill.exe', '/PID', (string) $processId, '/T', '/F']);
        if (!$result->isSuccessful() && !str_contains(strtolower($result->output), 'not found')) {
            throw new RuntimeException('Could not stop process ' . $processId . ': ' . trim($result->output));
        }
    }

    /** @param array<string, string> $environment */
    private function withEnvironment(array $environment, callable $callback): mixed
    {
        $previous = [];
        foreach ($environment as $key => $value) {
            $previous[$key] = getenv($key);
            putenv($key . '=' . $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($previous as $key => $value) {
                $value === false ? putenv($key) : putenv($key . '=' . $value);
            }
        }
    }

    private static function quotePowerShell(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function quoteWindowsArgument(string $argument): string
    {
        $quoted = '"';
        $slashes = 0;
        foreach (str_split($argument) as $character) {
            if ($character === '\\') {
                $slashes++;
                continue;
            }
            $quoted .= $character === '"'
                ? str_repeat('\\', $slashes * 2 + 1) . '"'
                : str_repeat('\\', $slashes) . $character;
            $slashes = 0;
        }

        return $quoted . str_repeat('\\', $slashes * 2) . '"';
    }
}
