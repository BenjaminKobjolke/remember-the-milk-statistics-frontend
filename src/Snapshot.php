<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/** One row of the producer's snapshots table. */
final readonly class Snapshot
{
    public function __construct(
        public DateTimeImmutable $takenAt,
        public string $listName,
        public int $openCount,
        public int $completedCount,
    ) {
    }
}
