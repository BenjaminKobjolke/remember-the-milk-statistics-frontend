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

    public function __construct(
        public string $dbPath,
        public int $pageSize,
        public int $chartPoints,
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
        $chartPoints = $values['chart_points'] ?? null;
        $logLevel = $values['log_level'] ?? null;
        $timezone = $values['timezone'] ?? null;
        $debug = $values['debug'] ?? false;

        if (!is_string($dbPath) || $dbPath === '') {
            throw new ConfigException('db_path must be a non-empty string');
        }
        if (!is_int($pageSize) || $pageSize < 1) {
            throw new ConfigException('page_size must be an integer >= 1');
        }
        if (!is_int($chartPoints) || $chartPoints < 2) {
            throw new ConfigException('chart_points must be an integer >= 2');
        }
        if (!is_string($logLevel) || !Logger::isLevel($logLevel)) {
            throw new ConfigException('log_level must be one of: ' . implode(', ', Logger::LEVELS));
        }
        if (!is_string($timezone) || !in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ConfigException('timezone must be a PHP timezone identifier, e.g. Europe/Berlin');
        }
        if (!is_bool($debug)) {
            throw new ConfigException('debug must be true or false');
        }

        return new self($dbPath, $pageSize, $chartPoints, $logLevel, $timezone, $debug);
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
