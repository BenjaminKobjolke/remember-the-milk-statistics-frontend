<?php

declare(strict_types=1);

namespace App;

/**
 * Turns snapshots into SVG polyline points: x = time order, y = open count scaled between the
 * series minimum (bottom) and maximum (top), so small changes like 69..72 stay visible.
 */
final class OpenCountChart
{
    public const WIDTH = 600;
    public const HEIGHT = 160;
    public const PADDING = 8;

    /** @param list<Snapshot> $snapshots oldest first */
    public static function points(array $snapshots): string
    {
        $count = count($snapshots);
        if ($count === 0) {
            return '';
        }
        $counts = array_map(static fn (Snapshot $s): int => $s->openCount, $snapshots);
        $min = min($counts);
        // max(1, ...) keeps a flat series on the baseline instead of dividing by zero
        $range = max(1, max($counts) - $min);
        $plotWidth = self::WIDTH - 2 * self::PADDING;
        $plotHeight = self::HEIGHT - 2 * self::PADDING;

        $points = [];
        foreach ($snapshots as $index => $snapshot) {
            $x = $count === 1 ? self::WIDTH / 2 : self::PADDING + $index * $plotWidth / ($count - 1);
            $y = self::HEIGHT - self::PADDING - ($snapshot->openCount - $min) / $range * $plotHeight;
            $points[] = round($x, 1) . ',' . round($y, 1);
        }

        return implode(' ', $points);
    }
}
