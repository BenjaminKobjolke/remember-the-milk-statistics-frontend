<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUiDriver;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Tools\MobileUiDriver\Emulator;
use Tools\MobileUiDriver\ProcessResult;
use Tools\MobileUiDriver\ProcessRunner;

final class EmulatorTest extends TestCase
{
    public function testPrepareChromeGrantsNotificationPermissionAfterWritingFirstRunFlags(): void
    {
        $runner = new EmulatorRecordingProcessRunner();
        $emulator = new Emulator($runner);

        $this->prepareChrome($emulator);

        $grant = ['adb', '-s', 'emulator-5580', 'shell', 'pm', 'grant', 'com.android.chrome', 'android.permission.POST_NOTIFICATIONS'];
        self::assertSame(1, count(array_filter($runner->commands, static fn (array $command): bool => $command === $grant)));

        $pushIndex = null;
        $grantIndex = null;
        foreach ($runner->commands as $index => $command) {
            if (($command[3] ?? null) === 'push' && end($command) === '/data/local/tmp/chrome-command-line') {
                $pushIndex = $index;
            }
            if ($command === $grant) {
                $grantIndex = $index;
            }
        }

        self::assertNotNull($pushIndex);
        self::assertNotNull($grantIndex);
        self::assertGreaterThan($pushIndex, $grantIndex);
    }

    public function testPrepareChromeReportsWhenNotificationPermissionGrantFails(): void
    {
        $runner = new EmulatorRecordingProcessRunner(failGrant: true);
        $emulator = new Emulator($runner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('grant Chrome notification permission');

        $this->prepareChrome($emulator);
    }

    private function prepareChrome(Emulator $emulator): void
    {
        $method = new ReflectionMethod(Emulator::class, 'prepareChrome');
        $method->invoke($emulator, 'emulator-5580');
    }
}

final class EmulatorRecordingProcessRunner extends ProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    public function __construct(private readonly bool $failGrant = false)
    {
    }

    public function run(array $command, int $timeoutSeconds = 30, array $environment = []): ProcessResult
    {
        $this->commands[] = $command;
        $isGrant = in_array('grant', $command, true);

        return new ProcessResult('', $isGrant && $this->failGrant ? 1 : 0);
    }
}
