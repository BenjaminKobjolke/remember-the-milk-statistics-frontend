<?php

declare(strict_types=1);

namespace App\Config;

use App\Logger;

/**
 * Typed application settings from config/app.php, overridden by the optional, gitignored
 * config/app.local.php (debug lives there, never in the deployed file).
 */
final readonly class AppConfig
{
    public const FILE = 'app.php';
    public const LOCAL_FILE = 'app.local.php';
    public const CONFIG_DIR_ENV = 'RTM_STATS_CONFIG_DIR';

    public function __construct(
        public string $dbPath,
        public int $pageSize,
        public string $logLevel,
        public string $timezone,
        public bool $debug,
    ) {
    }

    public static function load(string $configDir): self
    {
        $values = array_replace(
            self::read($configDir . '/' . self::FILE, true),
            self::read($configDir . '/' . self::LOCAL_FILE, false),
        );

        $dbPath = $values['db_path'] ?? null;
        $pageSize = $values['page_size'] ?? null;
        $logLevel = $values['log_level'] ?? null;
        $timezone = $values['timezone'] ?? null;
        $debug = $values['debug'] ?? false;

        self::ensure(is_string($dbPath) && $dbPath !== '', 'db_path must be a non-empty string');
        self::ensure(is_int($pageSize) && $pageSize >= 1, 'page_size must be an integer >= 1');
        self::ensure(
            is_string($logLevel) && Logger::isLevel($logLevel),
            'log_level must be one of: ' . implode(', ', Logger::LEVELS),
        );
        self::ensure(
            is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true),
            'timezone must be a PHP timezone identifier, e.g. Europe/Berlin',
        );
        self::ensure(is_bool($debug), 'debug must be true or false');

        return new self($dbPath, $pageSize, $logLevel, $timezone, $debug);
    }

    private static function ensure(bool $valid, string $message): void
    {
        if (!$valid) {
            throw new ConfigException($message);
        }
    }

    /** @return array<string, mixed> */
    private static function read(string $file, bool $required): array
    {
        if (!is_file($file)) {
            if ($required) {
                throw new ConfigException("Missing $file - copy " . basename($file) . '.example and edit it');
            }

            return [];
        }
        $values = require $file;
        if (!is_array($values)) {
            throw new ConfigException("$file must return an array");
        }

        return $values;
    }
}
