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

    public function testStartLaunchesEmulatorWithoutWindowAndCrashDialog(): void
    {
        $previousAndroidHome = getenv('ANDROID_HOME');
        $previousAvd = getenv('MOBILE_UI_DRIVER_AVD');
        $sdk = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rtm-mobile-sdk-' . bin2hex(random_bytes(6));
        $emulatorDirectory = $sdk . DIRECTORY_SEPARATOR . 'emulator';
        $platformToolsDirectory = $sdk . DIRECTORY_SEPARATOR . 'platform-tools';
        mkdir($emulatorDirectory, 0777, true);
        mkdir($platformToolsDirectory, 0777, true);
        foreach (['emulator.exe', 'emulator'] as $binary) {
            file_put_contents($emulatorDirectory . DIRECTORY_SEPARATOR . $binary, '');
        }
        foreach (['adb.exe', 'adb'] as $binary) {
            file_put_contents($platformToolsDirectory . DIRECTORY_SEPARATOR . $binary, '');
        }
        putenv('ANDROID_HOME=' . $sdk);
        putenv('MOBILE_UI_DRIVER_AVD');

        try {
            $runner = new EmulatorRecordingProcessRunner();
            $emulator = new Emulator($runner);

            try {
                $emulator->start();
                self::fail('Expected emulator startup to fail while waiting for the device.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('wait for emulator device', $exception->getMessage());
            }

            $startCommand = $runner->startCommands[0] ?? [];
            self::assertContains('-no-window', $startCommand);
            $crashReportModeIndex = array_search('-crash-report-mode', $startCommand, true);
            self::assertNotFalse($crashReportModeIndex);
            self::assertSame('disabled', $startCommand[$crashReportModeIndex + 1] ?? null);
            self::assertContains('-read-only', $startCommand);
        } finally {
            $previousAndroidHome === false ? putenv('ANDROID_HOME') : putenv('ANDROID_HOME=' . $previousAndroidHome);
            $previousAvd === false ? putenv('MOBILE_UI_DRIVER_AVD') : putenv('MOBILE_UI_DRIVER_AVD=' . $previousAvd);
            foreach (['emulator.exe', 'emulator'] as $binary) {
                unlink($emulatorDirectory . DIRECTORY_SEPARATOR . $binary);
            }
            foreach (['adb.exe', 'adb'] as $binary) {
                unlink($platformToolsDirectory . DIRECTORY_SEPARATOR . $binary);
            }
            rmdir($emulatorDirectory);
            rmdir($platformToolsDirectory);
            rmdir($sdk);
        }
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

    /** @var list<list<string>> */
    public array $startCommands = [];

    /** @var list<int> */
    public array $stoppedProcessIds = [];

    public function __construct(private readonly bool $failGrant = false)
    {
    }

    public function run(array $command, int $timeoutSeconds = 30, array $environment = []): ProcessResult
    {
        $this->commands[] = $command;
        $isGrant = in_array('grant', $command, true);

        if (in_array('-list-avds', $command, true)) {
            return new ProcessResult("Pixel_9_Isolated\n", 0);
        }
        if (in_array('devices', $command, true)) {
            return new ProcessResult("List of devices attached\n", 0);
        }
        if (in_array('wait-for-device', $command, true)) {
            return new ProcessResult('', 1);
        }

        return new ProcessResult('', $isGrant && $this->failGrant ? 1 : 0);
    }

    public function start(array $command, string $workingDirectory, array $environment = []): int
    {
        $this->startCommands[] = $command;

        return 12345;
    }

    public function stop(int $processId): void
    {
        $this->stoppedProcessIds[] = $processId;
    }
}
