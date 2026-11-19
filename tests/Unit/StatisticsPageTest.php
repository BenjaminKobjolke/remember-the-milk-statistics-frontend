<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\StatisticsPage;
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
}
