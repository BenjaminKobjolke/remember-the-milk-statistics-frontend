<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\StatisticsPage;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatisticsPageTest extends TestCase
{
    /** @return array<string, array{mixed, int}> */
    public static function queryValues(): array
    {
        return [
            'missing' => [null, 1],
            'valid' => ['3', 3],
            'zero' => ['0', 1],
            'negative' => ['-2', 1],
            'text' => ['abc', 1],
            'array' => [['1'], 1],
        ];
    }

    #[DataProvider('queryValues')]
    public function testPageFromQuery(mixed $raw, int $expected): void
    {
        $this->assertSame($expected, StatisticsPage::pageFromQuery($raw));
    }

    public function testDayFromQueryAcceptsPastDayAtLocalMidnight(): void
    {
        $day = StatisticsPage::dayFromQuery('2026-11-01', new DateTimeZone('Europe/Berlin'));

        $this->assertSame('2026-11-01 00:00 Europe/Berlin', $day->format('Y-m-d H:i e'));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidDays(): array
    {
        return [
            'missing' => [null],
            'text' => ['abc'],
            'impossible date' => ['2026-02-30'],
            'trailing junk' => ['2026-11-01x'],
            'future' => ['2999-01-01'],
            'array' => [['2026-11-01']],
        ];
    }

    #[DataProvider('invalidDays')]
    public function testDayFromQueryFallsBackToToday(mixed $raw): void
    {
        $tz = new DateTimeZone('Europe/Berlin');

        $this->assertEquals(new DateTimeImmutable('today', $tz), StatisticsPage::dayFromQuery($raw, $tz));
    }
}
