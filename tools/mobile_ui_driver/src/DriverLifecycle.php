<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/** Owns fixture creation, server and emulator startup, and complete teardown. */
final class DriverLifecycle
{
    private readonly string $stateDirectory;
    private readonly string $statePath;
    private readonly LocalServer $server;

    public function __construct(
        string $projectRoot,
        private readonly ProcessRunner $processRunner,
        private readonly Emulator $emulator,
    ) {
        $this->stateDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'mobile_ui_driver';
        $this->statePath = $this->stateDirectory . DIRECTORY_SEPARATOR . 'state.json';
        $this->server = new LocalServer($projectRoot, $processRunner);
    }

    public function start(): int
    {
        if (file_exists($this->stateDirectory)) {
            throw new RuntimeException('The mobile UI driver state already exists. Run "stop" before starting it again.');
        }
        if (!mkdir($this->stateDirectory, 0777, true) && !is_dir($this->stateDirectory)) {
            throw new RuntimeException('Could not create the mobile UI driver state directory.');
        }

        $serverPid = null;
        $serial = '';
        try {
            $configDirectory = Fixtures::create($this->stateDirectory);
            $port = $this->server->freePort();
            $serverPid = $this->server->start($port, $configDirectory);
            (new DriverState($serverPid, $port, $serial, $this->stateDirectory))->save($this->statePath);
            $this->server->waitUntilReady($port);
            $serial = $this->emulator->start();
            $state = new DriverState($serverPid, $port, $serial, $this->stateDirectory);
            $state->save($this->statePath);

            fwrite(STDOUT, 'Mobile UI driver ready at http://10.0.2.2:' . $port . ' on ' . $serial . PHP_EOL);

            return 0;
        } catch (Throwable $exception) {
            $this->cleanup($serverPid, $serial);
            throw $exception;
        }
    }

    public function stop(): int
    {
        $state = DriverState::load($this->statePath);
        if ($state === null && !is_dir($this->stateDirectory)) {
            fwrite(STDOUT, "The mobile UI driver is not running.\n");

            return 0;
        }
        if ($state === null) {
            throw new RuntimeException('Driver state is missing; no emulator or server will be stopped. Remove the stale state folder manually.');
        }

        $errors = $this->cleanup($state->serverPid, $state->emulatorSerial);
        if ($errors !== []) {
            fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);

            return 1;
        }
        fwrite(STDOUT, "The mobile UI driver stopped and its sample data was removed.\n");

        return 0;
    }

    /** @return list<string> */
    private function cleanup(?int $serverPid, string $serial): array
    {
        $errors = [];
        if ($serial !== '') {
            try {
                $this->emulator->stop($serial);
            } catch (Throwable $exception) {
                $errors[] = 'Could not stop emulator ' . $serial . ': ' . $exception->getMessage();
            }
        }
        if ($serverPid !== null) {
            try {
                $this->processRunner->stop($serverPid);
            } catch (Throwable $exception) {
                $errors[] = 'Could not stop local PHP server: ' . $exception->getMessage();
            }
        }
        if ($errors === []) {
            try {
                $this->removeStateDirectory();
            } catch (Throwable $exception) {
                $errors[] = 'Could not remove mobile UI driver state: ' . $exception->getMessage();
            }
        }

        return $errors;
    }

    private function removeStateDirectory(): void
    {
        if (!is_dir($this->stateDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->stateDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $path = $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($this->stateDirectory);
    }
}
