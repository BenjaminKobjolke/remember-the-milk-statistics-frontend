<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Tests\Support\ContractSchema;

/** Writes the driver's throwaway database and configuration under its owned state directory. */
final class Fixtures
{
    private const TIMEZONE = 'Europe/Berlin';
    private const LISTS = ['Today', 'Inbox'];
    private const FIRST_HOUR = 6;
    private const LAST_HOUR = 20;
    private const DAY_COUNT = 2;

    public static function create(string $stateDirectory): string
    {
        $databasePath = $stateDirectory . DIRECTORY_SEPARATOR . 'statistics.sqlite';
        $pdo = ContractSchema::createDatabaseFromContract('sqlite:' . $databasePath);
        $timezone = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $timezone);
        $insert = $pdo->prepare(
            'INSERT INTO snapshots (taken_at, list_name, open_count, completed_count) VALUES (?, ?, ?, ?)'
        );

        for ($daysAgo = self::DAY_COUNT - 1; $daysAgo >= 0; $daysAgo--) {
            $day = $today->sub(new DateInterval('P' . $daysAgo . 'D'));
            for ($hour = self::FIRST_HOUR; $hour <= self::LAST_HOUR; $hour++) {
                foreach (self::LISTS as $listIndex => $listName) {
                    $takenAt = $day->setTime($hour, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
                    $openCount = 46 - ($hour - self::FIRST_HOUR) * 2 + ($listIndex * 9) + ($daysAgo * 5);
                    $completedCount = 3 + ($hour - self::FIRST_HOUR) + ($listIndex * 2);
                    $insert->execute([$takenAt, $listName, $openCount, $completedCount]);
                }
            }
        }

        $configDirectory = $stateDirectory . DIRECTORY_SEPARATOR . 'config';
        if (!mkdir($configDirectory, 0777, true) && !is_dir($configDirectory)) {
            throw new RuntimeException('Could not create the mobile UI driver config directory.');
        }
        $config = [
            'db_path' => $databasePath,
            'page_size' => 25,
            'log_level' => 'warning',
            'timezone' => self::TIMEZONE,
            'debug' => false,
        ];
        if (file_put_contents(
            $configDirectory . DIRECTORY_SEPARATOR . 'app.php',
            '<?php' . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL,
            LOCK_EX,
        ) === false) {
            throw new RuntimeException('Could not write mobile UI driver config.');
        }

        return $configDirectory;
    }
}
