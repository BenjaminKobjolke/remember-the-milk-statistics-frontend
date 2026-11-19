<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\OpenCountChart;
use App\Snapshot;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OpenCountChartTest extends TestCase
{
    private static function snapshot(int $open): Snapshot
    {
        return new Snapshot(new DateTimeImmutable('2026-11-19T08:00:00+00:00'), 'Today', $open, 0);
    }

    public function testEmptyListHasNoPoints(): void
    {
        $this->assertSame('', OpenCountChart::points([]));
    }

    public function testSinglePointIsCenteredOnBaseline(): void
    {
        $x = OpenCountChart::WIDTH / 2;
        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $this->assertSame("$x,$bottom", OpenCountChart::points([self::snapshot(5)]));
    }

    public function testScalesBetweenMinimumAndMaximum(): void
    {
        $points = explode(' ', OpenCountChart::points([self::snapshot(70), self::snapshot(72)]));

        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $this->assertStringEndsWith(",$bottom", $points[0]);
        $this->assertStringEndsWith(',' . OpenCountChart::PADDING, $points[1]);
    }

    public function testPointsSpanWidthAndScaleToMaximum(): void
    {
        $points = explode(' ', OpenCountChart::points([self::snapshot(0), self::snapshot(5), self::snapshot(10)]));

        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $middle = (OpenCountChart::HEIGHT) / 2;
        $right = OpenCountChart::WIDTH - OpenCountChart::PADDING;
        $this->assertSame(
            [OpenCountChart::PADDING . ",$bottom", OpenCountChart::WIDTH / 2 . ",$middle", "$right," . OpenCountChart::PADDING],
            $points,
        );
    }

    public function testAllZeroStaysOnBaseline(): void
    {
        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $this->assertStringEndsWith(",$bottom", OpenCountChart::points([self::snapshot(0), self::snapshot(0)]));
    }
}
