<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use JsonException;
use RuntimeException;

/** Immutable process and emulator details needed to stop one isolated driver session. */
final readonly class DriverState
{
    public function __construct(
        public int $serverPid,
        public int $httpPort,
        public string $emulatorSerial,
        public string $stateDirectory,
    ) {
    }

    public static function load(string $statePath): ?self
    {
        if (!is_file($statePath)) {
            return null;
        }
        $contents = file_get_contents($statePath);
        if ($contents === false) {
            throw new RuntimeException('Could not read mobile UI driver state.');
        }
        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Mobile UI driver state is not valid JSON.', previous: $exception);
        }
        if (!self::hasValidShape($data)) {
            throw new RuntimeException('Mobile UI driver state has an invalid shape.');
        }

        return new self($data['server_pid'], $data['http_port'], $data['emulator_serial'], $data['state_directory']);
    }

    private static function hasValidShape(mixed $data): bool
    {
        return is_array($data)
            && is_int($data['server_pid'] ?? null)
            && is_int($data['http_port'] ?? null)
            && is_string($data['emulator_serial'] ?? null)
            && is_string($data['state_directory'] ?? null);
    }

    public function save(string $statePath): void
    {
        $json = json_encode([
            'server_pid' => $this->serverPid,
            'http_port' => $this->httpPort,
            'emulator_serial' => $this->emulatorSerial,
            'state_directory' => $this->stateDirectory,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($statePath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Could not write mobile UI driver state.');
        }
    }
}
