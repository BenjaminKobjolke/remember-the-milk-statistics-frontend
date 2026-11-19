<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * Turns one day of snapshots into SVG polyline points and axis labels: x = time of day between
 * $from and $to, y = open count scaled between the series minimum (bottom) and maximum (top), so
 * small changes like 69..72 stay visible.
 */
final class OpenCountChart
{
    public const WIDTH = 600;
    public const HEIGHT = 160;
    public const PADDING = 8;
    public const TICK_HOURS = 3;

    /** @param list<Snapshot> $snapshots oldest first, all within [$from, $to) */
    public static function points(array $snapshots, DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        if ($snapshots === []) {
            return '';
        }
        [$min, $max] = self::bounds($snapshots);

        $points = [];
        foreach ($snapshots as $snapshot) {
            $x = self::x($snapshot->takenAt, $from, $to);
            $points[] = round($x, 1) . ',' . round(self::y($snapshot->openCount, $min, $max), 1);
        }

        return implode(' ', $points);
    }

    /**
     * Vertical axis labels: the maximum, the middle and the minimum open count of the series.
     *
     * @param list<Snapshot> $snapshots
     * @return array<int, float> open count => y in percent of HEIGHT, top first
     */
    public static function levels(array $snapshots): array
    {
        if ($snapshots === []) {
            return [];
        }
        [$min, $max] = self::bounds($snapshots);

        $levels = [];
        foreach (array_unique([$max, (int) round(($min + $max) / 2), $min]) as $count) {
            $levels[$count] = round(self::y($count, $min, $max) / self::HEIGHT * 100, 2);
        }

        return $levels;
    }

    /**
     * Hour labels every TICK_HOURS hours, placed by real time so 23/25-hour DST days stay right.
     *
     * @return array<int|string, float> hour label ('00', '03', ...) => x in percent of WIDTH
     */
    public static function ticks(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $ticks = [];
        for ($hour = 0; $hour < 24; $hour += self::TICK_HOURS) {
            $at = $from->setTime($hour, 0);
            $ticks[$at->format('H')] = round(self::x($at, $from, $to) / self::WIDTH * 100, 2);
        }

        return $ticks;
    }

    /**
     * @param non-empty-list<Snapshot> $snapshots
     * @return array{int, int} minimum and maximum open count
     */
    private static function bounds(array $snapshots): array
    {
        $counts = array_map(static fn (Snapshot $s): int => $s->openCount, $snapshots);

        return [min($counts), max($counts)];
    }

    private static function y(int $count, int $min, int $max): float
    {
        // max(1, ...) keeps a flat series on the baseline instead of dividing by zero
        $range = max(1, $max - $min);

        return self::HEIGHT - self::PADDING - ($count - $min) / $range * (self::HEIGHT - 2 * self::PADDING);
    }

    private static function x(DateTimeImmutable $at, DateTimeImmutable $from, DateTimeImmutable $to): float
    {
        $fraction = ($at->getTimestamp() - $from->getTimestamp()) / ($to->getTimestamp() - $from->getTimestamp());

        return self::PADDING + $fraction * (self::WIDTH - 2 * self::PADDING);
    }
}
