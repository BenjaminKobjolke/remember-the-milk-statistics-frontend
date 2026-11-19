<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\SnapshotRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\ContractSchema;

final class SnapshotRepositoryTest extends TestCase
{
    private SnapshotRepository $repository;

    protected function setUp(): void
    {
        $pdo = ContractSchema::createDatabase($this);
        foreach ([[12, 0], [10, 2], [7, 5]] as $hour => [$open, $completed]) {
            ContractSchema::insert($pdo, "2026-11-19T0{$hour}:00:00+00:00", 'Today', $open, $completed);
        }
        $this->repository = new SnapshotRepository($pdo);
    }

    public function testLatestIsNewestRow(): void
    {
        $latest = $this->repository->latest();

        $this->assertNotNull($latest);
        $this->assertSame(7, $latest->openCount);
        $this->assertSame(5, $latest->completedCount);
        $this->assertSame('2026-11-19 02:00', $latest->takenAt->format('Y-m-d H:i'));
    }

    public function testCount(): void
    {
        $this->assertSame(3, $this->repository->count());
    }

    public function testPageIsNewestFirstAndPaged(): void
    {
        $first = $this->repository->page(1, 2);
        $second = $this->repository->page(2, 2);

        $this->assertSame([7, 10], array_map(fn ($s) => $s->openCount, $first));
        $this->assertSame([12], array_map(fn ($s) => $s->openCount, $second));
    }

    public function testRecentIsOldestFirstForChart(): void
    {
        $this->assertSame([10, 7], array_map(fn ($s) => $s->openCount, $this->repository->recent(2)));
    }

    public function testEmptyTable(): void
    {
        $repository = new SnapshotRepository(ContractSchema::createDatabase($this));

        $this->assertNull($repository->latest());
        $this->assertSame(0, $repository->count());
        $this->assertSame([], $repository->page(1, 10));
    }

    public function testOpenReadOnlyRefusesWrites(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rtm_frontend_');
        ContractSchema::createDatabase($this, 'sqlite:' . $file);

        $pdo = SnapshotRepository::openReadOnly($file);

        try {
            $this->expectException(\PDOException::class);
            ContractSchema::insert($pdo, '2026-11-19T00:00:00+00:00', 'Today', 1, 1);
        } finally {
            unset($pdo);
            @unlink($file);
        }
    }
}
