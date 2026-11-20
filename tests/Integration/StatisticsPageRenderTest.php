<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config\AppConfig;
use App\SnapshotRepository;
use App\StatisticsPage;
use App\TwigFactory;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\ContractSchema;

/** Request-to-render path: real Twig, real translations, contract-shaped database. */
final class StatisticsPageRenderTest extends TestCase
{
    private const TIMEZONE = 'Europe/Berlin';

    private static function config(int $pageSize = 2): AppConfig
    {
        return new AppConfig('unused.sqlite', $pageSize, 'error', self::TIMEZONE, true);
    }

    private static function page(?SnapshotRepository $repository, int $pageSize = 2): StatisticsPage
    {
        $config = self::config($pageSize);

        return new StatisticsPage($repository, $config, TwigFactory::create(dirname(__DIR__, 2), $config));
    }

    private static function day(string $day): DateTimeImmutable
    {
        return new DateTimeImmutable($day, new DateTimeZone(self::TIMEZONE));
    }

    private function repositoryWithRows(int $rows): SnapshotRepository
    {
        $pdo = ContractSchema::createDatabase($this);
        for ($i = 1; $i <= $rows; $i++) {
            ContractSchema::insert($pdo, "2026-11-19T0{$i}:00:00+00:00", 'Today', 20 + $i, $i);
        }

        return new SnapshotRepository($pdo);
    }

    /** Renders the page over three snapshots on 2026-11-19 (01:00..03:00 UTC). */
    private function renderDay(string $day, int $page = 1): string
    {
        return self::page($this->repositoryWithRows(3))->render($page, self::day($day));
    }

    public function testRendersSummaryTableChartAndPager(): void
    {
        $html = $this->renderDay('2026-11-19');

        $this->assertStringContainsString('Today', $html);
        $this->assertMatchesRegularExpression('/<polyline class="chart-line" points="[^"]+"/', $html);
        $this->assertStringNotContainsString('No snapshots on this day.', $html);
        // vertical axis: max, middle, min of the day (21..23)
        $this->assertMatchesRegularExpression('/class="chart-level"[^>]*>22</', $html);
        $this->assertStringContainsString('>23<', $html);
        $this->assertStringContainsString('Page 1 of 2', $html);
        $this->assertStringContainsString('href="?page=2&amp;day=2026-11-19"', $html);
        $this->assertStringNotContainsString('?page=0', $html);
        // stored 03:00 UTC, shown in the configured Europe/Berlin time
        $this->assertStringContainsString('2026-11-19 04:00', $html);
    }

    public function testCompactLayoutPutsChartFirstAndSnapshotBelow(): void
    {
        $html = $this->renderDay('2026-11-19');

        $this->assertStringNotContainsString('Open and completed tasks of a Remember The Milk smart list', $html);
        $this->assertStringNotContainsString('Open tasks over time', $html);
        $this->assertStringContainsString('class="visually-hidden"', $html);
        $this->assertStringContainsString('Last snapshot: 2026-11-19 04:00 · Today', $html);

        $completedPosition = strpos($html, 'Completed today');
        $chartPosition = strpos($html, 'class="chart-line"');
        $snapshotPosition = strpos($html, 'Last snapshot:');

        $this->assertNotFalse($completedPosition);
        $this->assertNotFalse($chartPosition);
        $this->assertNotFalse($snapshotPosition);
        $this->assertLessThan($chartPosition, $completedPosition);
        $this->assertLessThan($snapshotPosition, $chartPosition);
    }

    public function testChartShowsHoursAndDayNavigation(): void
    {
        // a past day, so the next-day link exists no matter when the test runs
        $html = $this->renderDay('2026-11-10');

        $this->assertStringContainsString('>03<', $html);
        $this->assertStringContainsString('>21<', $html);
        $this->assertStringContainsString('href="?day=2026-11-09&amp;page=1"', $html);
        $this->assertStringContainsString('href="?day=2026-11-11&amp;page=1"', $html);
    }

    public function testTodayHasNoNextDayLink(): void
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE));

        $html = self::page($this->repositoryWithRows(3))->render(1, $today);

        $this->assertStringContainsString('Previous day', $html);
        $this->assertStringNotContainsString('Next day', $html);
    }

    public function testDayWithoutSnapshotsShowsChartEmptyText(): void
    {
        $html = $this->renderDay('2026-11-10');

        $this->assertStringContainsString('No snapshots on this day.', $html);
        $this->assertStringContainsString('<table', $html);
    }

    public function testPageBeyondLastShowsLastPage(): void
    {
        $html = $this->renderDay('2026-11-19', 999);

        $this->assertStringContainsString('Page 2 of 2', $html);
        $this->assertStringContainsString('>21<', $html);
    }

    public function testMissingDatabaseShowsEmptyState(): void
    {
        $html = self::page(null)->render(1, self::day('2026-11-19'));

        $this->assertStringContainsString('No statistics database found yet.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function testEmptyTableShowsEmptyState(): void
    {
        $html = self::page($this->repositoryWithRows(0))->render(1, self::day('2026-11-19'));

        $this->assertStringContainsString('No snapshots recorded yet.', $html);
    }

    public function testErrorPageRenders(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2), self::config());

        $html = $twig->render(StatisticsPage::TEMPLATE_ERROR, ['detail' => null]);

        $this->assertStringContainsString('Something went wrong', $html);
    }
}
