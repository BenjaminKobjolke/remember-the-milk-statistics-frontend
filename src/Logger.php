<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Central logger: the only place that writes log output. Messages below the configured
 * level are dropped. Sink is PHP's error_log (web server / CLI error log).
 */
final class Logger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    /** Ordered from most to least verbose. */
    public const LEVELS = [self::DEBUG, self::INFO, self::WARNING, self::ERROR];

    private int $threshold;

    public function __construct(string $level)
    {
        $this->threshold = (int) array_search($level, self::LEVELS, true);
    }

    public static function isLevel(string $level): bool
    {
        return in_array($level, self::LEVELS, true);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        if (array_search($level, self::LEVELS, true) < $this->threshold) {
            return;
        }
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key] = sprintf(
                    '%s: %s at %s:%d',
                    $value::class,
                    $value->getMessage(),
                    $value->getFile(),
                    $value->getLine(),
                );
            }
        }
        $suffix = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        error_log(sprintf('[rtm-statistics-frontend] %s: %s%s', strtoupper($level), $message, $suffix));
    }
}
