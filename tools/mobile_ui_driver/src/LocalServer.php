<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use App\Config\AppConfig;
use RuntimeException;

/** Starts the statistics page on a temporary port with only the driver's fixture config. */
final class LocalServer
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ProcessRunner $processRunner,
    ) {
    }

    public function freePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException('Could not reserve a local web server port: ' . $errorMessage);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($address) || preg_match('/:(\d+)$/', $address, $matches) !== 1) {
            throw new RuntimeException('Could not determine the local web server port.');
        }

        return (int) $matches[1];
    }

    public function start(int $port, string $configDirectory): int
    {
        $phpBinary = PHP_BINARY;

        return $this->processRunner->start([
            $phpBinary,
            '-S',
            '127.0.0.1:' . $port,
            '-t',
            $this->projectRoot . DIRECTORY_SEPARATOR . 'public',
            $this->projectRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR
                . 'mobile_ui_driver' . DIRECTORY_SEPARATOR . 'router.php',
        ], $this->projectRoot, [AppConfig::CONFIG_DIR_ENV => $configDirectory]);
    }

    public function waitUntilReady(int $port): void
    {
        $url = 'http://127.0.0.1:' . $port . '/';
        $deadline = microtime(true) + 20;
        $context = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        do {
            if (@file_get_contents($url, false, $context) !== false) {
                return;
            }
            usleep(200_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out waiting for the isolated statistics page server.');
    }
}

