<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\OpenCountChart;
use App\Snapshot;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class OpenCountChartTest extends TestCase
{
    private const DAY = '2026-11-19';

    private static function from(string $day = self::DAY, string $tz = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($day . ' 00:00:00', new DateTimeZone($tz));
    }

    private static function snapshot(int $open, string $time = '08:00:00'): Snapshot
    {
        return new Snapshot(new DateTimeImmutable(self::DAY . "T{$time}+00:00"), 'Today', $open, 0);
    }

    /** @param list<Snapshot> $snapshots */
    private static function points(array $snapshots): string
    {
        $from = self::from();

        return OpenCountChart::points($snapshots, $from, $from->modify('+1 day'));
    }

    public function testEmptyListHasNoPoints(): void
    {
        $this->assertSame('', self::points([]));
    }

    public function testSinglePointSitsAtItsTimeOnBaseline(): void
    {
        $x = OpenCountChart::WIDTH / 2;
        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $this->assertSame("$x,$bottom", self::points([self::snapshot(5, '12:00:00')]));
    }

    public function testScalesBetweenMinimumAndMaximum(): void
    {
        $points = explode(' ', self::points([self::snapshot(70), self::snapshot(72, '09:00:00')]));

        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $this->assertStringEndsWith(",$bottom", $points[0]);
        $this->assertStringEndsWith(',' . OpenCountChart::PADDING, $points[1]);
    }

    public function testXFollowsTimeOfDay(): void
    {
        $points = explode(' ', self::points([
            self::snapshot(0, '00:00:00'),
            self::snapshot(5, '12:00:00'),
            self::snapshot(10, '23:59:59'),
        ]));

        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $middle = OpenCountChart::HEIGHT / 2;
        $this->assertSame(OpenCountChart::PADDING . ",$bottom", $points[0]);
        $this->assertSame(OpenCountChart::WIDTH / 2 . ",$middle", $points[1]);
        $this->assertStringEndsWith(',' . OpenCountChart::PADDING, $points[2]);
    }

    public function testAllZeroStaysOnBaseline(): void
    {
        $bottom = OpenCountChart::HEIGHT - OpenCountChart::PADDING;
        $this->assertStringEndsWith(",$bottom", self::points([self::snapshot(0), self::snapshot(0, '09:00:00')]));
    }

    public function testLevelsLabelMaximumMiddleAndMinimum(): void
    {
        $levels = OpenCountChart::levels([self::snapshot(60), self::snapshot(70, '09:00:00')]);

        $this->assertSame([70, 65, 60], array_keys($levels));
        $this->assertSame(round(OpenCountChart::PADDING / OpenCountChart::HEIGHT * 100, 2), $levels[70]);
        $this->assertSame(50.0, $levels[65]);
        $this->assertSame(round((OpenCountChart::HEIGHT - OpenCountChart::PADDING) / OpenCountChart::HEIGHT * 100, 2), $levels[60]);
    }

    public function testLevelsOfFlatSeriesIsOneBaselineLabel(): void
    {
        $bottom = round((OpenCountChart::HEIGHT - OpenCountChart::PADDING) / OpenCountChart::HEIGHT * 100, 2);

        $this->assertSame([5 => $bottom], OpenCountChart::levels([self::snapshot(5), self::snapshot(5, '09:00:00')]));
        $this->assertSame([], OpenCountChart::levels([]));
    }

    public function testTicksEveryThreeHours(): void
    {
        $from = self::from();
        $ticks = OpenCountChart::ticks($from, $from->modify('+1 day'));

        $this->assertSame(['00', '03', '06', '09', '12', '15', '18', '21'], array_map('strval', array_keys($ticks)));
        $this->assertSame(round(OpenCountChart::PADDING / OpenCountChart::WIDTH * 100, 2), $ticks['00']);
        $this->assertSame(50.0, $ticks['12']);
    }

    public function testTicksFollowShortDstDay(): void
    {
        // 2026-03-29 has 23 hours in Europe/Berlin: noon is only 11 hours in, before the middle
        $from = self::from('2026-03-29', 'Europe/Berlin');
        $ticks = OpenCountChart::ticks($from, $from->modify('+1 day'));

        $this->assertCount(8, $ticks);
        $this->assertLessThan(50.0, $ticks['12']);
    }
}
