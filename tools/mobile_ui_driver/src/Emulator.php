<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use RuntimeException;

/** Starts and controls only the read-only Android emulator instance owned by this driver. */
final class Emulator
{
    private const DEFAULT_AVD = 'Pixel_9_Isolated';
    private const DEFAULT_ADB_BINARY = 'adb';
    private const FIRST_CONSOLE_PORT = 5580;
    private const BOOT_TIMEOUT_SECONDS = 180;
    private const CHROME_FLAGS = '_ --disable-fre --no-default-browser-check --no-first-run';
    private ?string $adbBinary = null;

    public function __construct(private readonly ProcessRunner $processRunner)
    {
    }

    public function start(): string
    {
        $sdk = $this->sdkPath();
        $emulatorBinary = $sdk . DIRECTORY_SEPARATOR . 'emulator' . DIRECTORY_SEPARATOR
            . (PHP_OS_FAMILY === 'Windows' ? 'emulator.exe' : 'emulator');
        $this->adbBinary = $sdk . DIRECTORY_SEPARATOR . 'platform-tools' . DIRECTORY_SEPARATOR
            . (PHP_OS_FAMILY === 'Windows' ? 'adb.exe' : 'adb');
        if (!is_file($emulatorBinary) || !is_file($this->adbBinary)) {
            throw new RuntimeException('Android SDK emulator or adb was not found under the configured SDK.');
        }

        $avd = getenv('MOBILE_UI_DRIVER_AVD') ?: self::DEFAULT_AVD;
        $this->assertAvdIsInstalled($emulatorBinary, $avd);

        $port = $this->freeConsolePort();
        $serial = 'emulator-' . $port;
        $emulatorPid = $this->processRunner->start([
            $emulatorBinary,
            '-avd',
            $avd,
            '-port',
            (string) $port,
            '-read-only',
            '-no-snapshot-save',
            '-no-audio',
            '-no-boot-anim',
        ], $sdk);

        try {
            $this->assertSuccessful($this->runAdb($serial, ['wait-for-device'], self::BOOT_TIMEOUT_SECONDS), 'wait for emulator device');
            $this->waitForBoot($serial);
            $this->prepareChrome($serial);
        } catch (\Throwable $exception) {
            try {
                $this->stop($serial);
            } catch (\Throwable) {
                // The process id remains the final cleanup path if adb did not connect.
            }
            $this->processRunner->stop($emulatorPid);
            throw $exception;
        }

        return $serial;
    }

    public function stop(string $serial): void
    {
        $result = $this->runAdb($serial, ['emu', 'kill'], 15);
        if (!$result->isSuccessful() && !str_contains(strtolower($result->output), 'not found')) {
            throw new RuntimeException('Could not stop emulator ' . $serial . ': ' . trim($result->output));
        }
    }

    /** @param list<string> $arguments */
    public function runAdb(string $serial, array $arguments, int $timeoutSeconds = 30): ProcessResult
    {
        return $this->processRunner->run([
            $this->adbBinary ?? self::DEFAULT_ADB_BINARY,
            '-s',
            $serial,
            ...$arguments,
        ], $timeoutSeconds);
    }

    private function assertAvdIsInstalled(string $emulatorBinary, string $avd): void
    {
        $result = $this->processRunner->run([$emulatorBinary, '-list-avds']);
        $this->assertSuccessful($result, 'list Android virtual devices');
        $availableAvds = preg_split('/\R/', trim($result->output));
        if ($availableAvds === false || !in_array($avd, $availableAvds, true)) {
            throw new RuntimeException('Android virtual device "' . $avd . '" is not installed.');
        }
    }

    private function sdkPath(): string
    {
        $localAppData = getenv('LOCALAPPDATA');
        $candidates = array_filter([
            getenv('ANDROID_HOME') ?: null,
            getenv('ANDROID_SDK_ROOT') ?: null,
            $localAppData === false ? null : $localAppData . DIRECTORY_SEPARATOR . 'Android' . DIRECTORY_SEPARATOR . 'Sdk',
        ]);
        foreach ($candidates as $candidate) {
            if (is_dir($candidate . DIRECTORY_SEPARATOR . 'emulator')
                && is_dir($candidate . DIRECTORY_SEPARATOR . 'platform-tools')) {
                return $candidate;
            }
        }

        throw new RuntimeException('Android SDK not found; set ANDROID_HOME or ANDROID_SDK_ROOT.');
    }

    private function freeConsolePort(): int
    {
        $devices = $this->processRunner->run([$this->adbBinary ?? self::DEFAULT_ADB_BINARY, 'devices']);
        $this->assertSuccessful($devices, 'list connected Android devices');
        preg_match_all('/^emulator-(\d+)\s/m', $devices->output, $matches);
        $matchedPorts = $matches[1];
        $usedPorts = array_map('intval', $matchedPorts);

        for ($port = self::FIRST_CONSOLE_PORT; $port <= 65534; $port += 2) {
            if (in_array($port, $usedPorts, true)) {
                continue;
            }
            $consoleSocket = @stream_socket_server('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage);
            if ($consoleSocket === false) {
                continue;
            }
            $adbSocket = @stream_socket_server('tcp://127.0.0.1:' . ($port + 1), $errorCode, $errorMessage);
            fclose($consoleSocket);
            if ($adbSocket === false) {
                continue;
            }
            fclose($adbSocket);

            return $port;
        }

        throw new RuntimeException('No free Android emulator console port was found.');
    }

    private function waitForBoot(string $serial): void
    {
        $deadline = microtime(true) + self::BOOT_TIMEOUT_SECONDS;
        do {
            $result = $this->runAdb($serial, ['shell', 'getprop', 'sys.boot_completed'], 10);
            if ($result->isSuccessful() && trim($result->output) === '1') {
                return;
            }
            usleep(1_000_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Android emulator did not finish booting within 180 seconds.');
    }

    private function prepareChrome(string $serial): void
    {
        $this->assertSuccessful(
            $this->runAdb($serial, ['shell', 'am', 'set-debug-app', '--persistent', 'com.android.chrome']),
            'configure Chrome first-run behavior',
        );
        $path = tempnam(sys_get_temp_dir(), 'rtm-chrome-flags-');
        if ($path === false) {
            throw new RuntimeException('Could not prepare Chrome first-run flags.');
        }
        try {
            if (file_put_contents($path, self::CHROME_FLAGS) === false) {
                throw new RuntimeException('Could not write Chrome first-run flags.');
            }
            $this->assertSuccessful(
                $this->runAdb($serial, ['push', $path, '/data/local/tmp/chrome-command-line']),
                'write Chrome first-run flags to the isolated emulator',
            );
        } finally {
            unlink($path);
        }
    }

    private function assertSuccessful(ProcessResult $result, string $action): void
    {
        if (!$result->isSuccessful()) {
            throw new RuntimeException('Could not ' . $action . ': ' . trim($result->output));
        }
    }
}
