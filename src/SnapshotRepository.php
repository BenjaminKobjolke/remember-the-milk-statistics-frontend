<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;
use Pdo\Sqlite;

/**
 * Read-only access to the snapshots table owned by remember-the-milk-statistics.
 * PDO instead of Cycle ORM is a documented project deviation (see CLAUDE.md): the table
 * is foreign and read-only, and ContractTest guards COLUMNS against contract/schema.json.
 */
final class SnapshotRepository
{
    public const TABLE = 'snapshots';
    /** Every column this frontend reads, with its SQLite type as published in the contract. */
    public const COLUMNS = [
        'id' => 'INTEGER',
        'taken_at' => 'TEXT',
        'list_name' => 'TEXT',
        'open_count' => 'INTEGER',
        'completed_count' => 'INTEGER',
    ];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function openReadOnly(string $path): PDO
    {
        return new Sqlite('sqlite:' . $path, null, null, [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY]);
    }

    public function latest(): ?Snapshot
    {
        return $this->select('ORDER BY id DESC LIMIT 1')[0] ?? null;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . self::TABLE)->fetchColumn();
    }

    /** @return list<Snapshot> newest first */
    public function page(int $page, int $size): array
    {
        return $this->select('ORDER BY id DESC LIMIT ? OFFSET ?', [$size, ($page - 1) * $size]);
    }

    /** @return list<Snapshot> the last $limit snapshots, oldest first (chart order) */
    public function recent(int $limit): array
    {
        return array_reverse($this->select('ORDER BY id DESC LIMIT ?', [$limit]));
    }

    /**
     * @param list<int> $params
     * @return list<Snapshot>
     */
    private function select(string $suffix, array $params = []): array
    {
        $statement = $this->pdo->prepare(
            sprintf('SELECT %s FROM %s %s', implode(', ', array_keys(self::COLUMNS)), self::TABLE, $suffix)
        );
        foreach ($params as $index => $value) {
            $statement->bindValue($index + 1, $value, PDO::PARAM_INT);
        }
        $statement->execute();

        return array_map(
            static fn (array $row): Snapshot => new Snapshot(
                new DateTimeImmutable($row['taken_at']),
                $row['list_name'],
                (int) $row['open_count'],
                (int) $row['completed_count'],
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }
}
