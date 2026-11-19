<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\AppConfig;
use App\Config\ConfigException;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rtm_frontend_config_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    /** @param array<string, mixed> $values */
    private function write(string $file, array $values): void
    {
        file_put_contents($this->dir . '/' . $file, '<?php return ' . var_export($values, true) . ';');
    }

    /** @return array<string, mixed> */
    private static function valid(): array
    {
        return [
            'db_path' => 'x.sqlite',
            'page_size' => 20,
            'chart_points' => 50,
            'log_level' => 'warning',
            'timezone' => 'Europe/Berlin',
            'debug' => false,
        ];
    }

    public function testUnknownTimezoneFails(): void
    {
        $this->write('app.php', ['timezone' => 'Mars/Olympus'] + self::valid());

        $this->expectException(ConfigException::class);
        AppConfig::load($this->dir);
    }

    public function testLoadsValues(): void
    {
        $this->write('app.php', self::valid());

        $config = AppConfig::load($this->dir);

        $this->assertSame('x.sqlite', $config->dbPath);
        $this->assertSame(20, $config->pageSize);
        $this->assertFalse($config->debug);
    }

    public function testLocalOverrideWins(): void
    {
        $this->write('app.php', self::valid());
        $this->write('app.local.php', ['debug' => true]);

        $this->assertTrue(AppConfig::load($this->dir)->debug);
    }

    public function testMissingDebugReadsAsOff(): void
    {
        $values = self::valid();
        unset($values['debug']);
        $this->write('app.php', $values);

        $this->assertFalse(AppConfig::load($this->dir)->debug);
    }

    public function testMissingFileFails(): void
    {
        $this->expectException(ConfigException::class);
        AppConfig::load($this->dir);
    }

    public function testInvalidPageSizeFails(): void
    {
        $this->write('app.php', ['page_size' => 0] + self::valid());

        $this->expectException(ConfigException::class);
        AppConfig::load($this->dir);
    }

    public function testUnknownLogLevelFails(): void
    {
        $this->write('app.php', ['log_level' => 'loud'] + self::valid());

        $this->expectException(ConfigException::class);
        AppConfig::load($this->dir);
    }
}
