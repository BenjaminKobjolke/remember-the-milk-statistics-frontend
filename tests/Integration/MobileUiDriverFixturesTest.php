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
use Tools\MobileUiDriver\Fixtures;

final class MobileUiDriverFixturesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rtm_mobile_fixture_' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }
    }

    public function testFixtureRendersBothListsAndChartForToday(): void
    {
        Fixtures::create($this->directory);
        $config = AppConfig::load($this->directory . '/config');
        $repository = new SnapshotRepository(SnapshotRepository::openReadOnly($config->dbPath));
        $page = new StatisticsPage($repository, $config, TwigFactory::create(dirname(__DIR__, 2), $config));
        $today = new DateTimeImmutable('today', new DateTimeZone($config->timezone));

        $html = $page->render(1, $today);

        self::assertStringContainsString('Today', $html);
        self::assertStringContainsString('Inbox', $html);
        self::assertMatchesRegularExpression('/<polyline class="chart-line" points="[^\"]+"/', $html);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $path = $entry->getPathname();
            $entry->isDir() ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
