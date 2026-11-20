<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reads contract/schema.json (git submodule) so tests check the frontend against the
 * producer's published schema instead of the frontend's own assumptions.
 */
final class ContractSchema
{
    public const TABLE = 'snapshots';
    private const FILE = __DIR__ . '/../../contract/schema.json';

    /** @return array<string, string> column name => SQLite type */
    public static function columnsOrSkip(TestCase $test): array
    {
        if (!is_file(self::FILE)) {
            $test->markTestSkipped('contract/ submodule not checked out');
        }
        $schema = json_decode((string) file_get_contents(self::FILE), true, flags: JSON_THROW_ON_ERROR);

        return $schema[self::TABLE]['columns'];
    }

    /** Creates the snapshots table exactly as the contract describes it. */
    public static function createDatabase(TestCase $test, string $dsn = 'sqlite::memory:'): PDO
    {
        self::columnsOrSkip($test);

        return self::createDatabaseFromContract($dsn);
    }

    /** Creates the contract table without requiring PHPUnit, for the local mobile driver fixtures. */
    public static function createDatabaseFromContract(string $dsn): PDO
    {
        if (!is_file(self::FILE)) {
            throw new RuntimeException('contract/schema.json is missing; check out the contract submodule.');
        }

        $definitions = [];
        foreach (self::readColumns() as $name => $type) {
            $definitions[] = $name === 'id' ? "$name $type PRIMARY KEY" : "$name $type";
        }
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(sprintf('CREATE TABLE %s (%s)', self::TABLE, implode(', ', $definitions)));

        return $pdo;
    }

    public static function insert(PDO $pdo, string $takenAt, string $listName, int $open, int $completed): void
    {
        $pdo->prepare(sprintf(
            'INSERT INTO %s (taken_at, list_name, open_count, completed_count) VALUES (?, ?, ?, ?)',
            self::TABLE,
        ))->execute([$takenAt, $listName, $open, $completed]);
    }

    /** @return array<string, string> */
    private static function readColumns(): array
    {
        $schema = json_decode((string) file_get_contents(self::FILE), true, flags: JSON_THROW_ON_ERROR);

        return $schema[self::TABLE]['columns'];
    }
}
