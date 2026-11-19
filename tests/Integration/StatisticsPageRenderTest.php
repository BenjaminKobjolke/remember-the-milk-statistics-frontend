<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config\AppConfig;
use App\SnapshotRepository;
use App\StatisticsPage;
use App\TwigFactory;
use PHPUnit\Framework\TestCase;
use Tests\Support\ContractSchema;

/** Request-to-render path: real Twig, real translations, contract-shaped database. */
final class StatisticsPageRenderTest extends TestCase
{
    private static function config(int $pageSize = 2): AppConfig
    {
        return new AppConfig('unused.sqlite', $pageSize, 50, 'error', 'Europe/Berlin', true);
    }

    private static function page(?SnapshotRepository $repository, int $pageSize = 2): StatisticsPage
    {
        $config = self::config($pageSize);

        return new StatisticsPage($repository, $config, TwigFactory::create(dirname(__DIR__, 2), $config));
    }

    private function repositoryWithRows(int $rows): SnapshotRepository
    {
        $pdo = ContractSchema::createDatabase($this);
        for ($i = 1; $i <= $rows; $i++) {
            ContractSchema::insert($pdo, "2026-11-19T0{$i}:00:00+00:00", 'Today', 20 + $i, $i);
        }

        return new SnapshotRepository($pdo);
    }

    public function testRendersSummaryTableChartAndPager(): void
    {
        $html = self::page($this->repositoryWithRows(3))->render(1);

        $this->assertStringContainsString('Today', $html);
        $this->assertStringContainsString('<polyline', $html);
        $this->assertStringContainsString('>23<', $html);
        $this->assertStringContainsString('Page 1 of 2', $html);
        $this->assertStringContainsString('href="?page=2"', $html);
        $this->assertStringNotContainsString('href="?page=0"', $html);
        // stored 03:00 UTC, shown in the configured Europe/Berlin time
        $this->assertStringContainsString('2026-11-19 04:00', $html);
    }

    public function testPageBeyondLastShowsLastPage(): void
    {
        $html = self::page($this->repositoryWithRows(3))->render(999);

        $this->assertStringContainsString('Page 2 of 2', $html);
        $this->assertStringContainsString('>21<', $html);
    }

    public function testMissingDatabaseShowsEmptyState(): void
    {
        $html = self::page(null)->render(1);

        $this->assertStringContainsString('No statistics database found yet.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function testEmptyTableShowsEmptyState(): void
    {
        $html = self::page($this->repositoryWithRows(0))->render(1);

        $this->assertStringContainsString('No snapshots recorded yet.', $html);
    }

    public function testErrorPageRenders(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2), self::config());

        $html = $twig->render(StatisticsPage::TEMPLATE_ERROR, ['detail' => null]);

        $this->assertStringContainsString('Something went wrong', $html);
    }
}
