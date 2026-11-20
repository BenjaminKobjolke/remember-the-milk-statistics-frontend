<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUiDriver;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tools\MobileUiDriver\Commands;
use Tools\MobileUiDriver\ProcessRunner;

final class CommandsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rtm_mobile_ui_driver_' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }
    }

    public function testUnknownCommandReturnsUsageError(): void
    {
        $errors = [];
        $commands = new Commands($this->directory, new RecordingProcessRunner(), static function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        self::assertSame(2, $commands->dispatch('unknown', []));
        self::assertStringContainsString('Usage:', implode("\n", $errors));
    }

    public function testMissingArgumentsReturnUsageError(): void
    {
        $errors = [];
        $commands = new Commands($this->directory, new RecordingProcessRunner(), static function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        self::assertSame(2, $commands->dispatch('screenshot', []));
        self::assertStringContainsString('Usage:', implode("\n", $errors));
    }

    #[DataProvider('commandsRequiringState')]
    public function testDeviceCommandsRequireStartedState(string $command, array $arguments): void
    {
        $errors = [];
        $commands = new Commands($this->directory, new RecordingProcessRunner(), static function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        self::assertSame(1, $commands->dispatch($command, $arguments));
        self::assertStringContainsString('start', implode("\n", $errors));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function commandsRequiringState(): iterable
    {
        yield 'open' => ['open', ['/']];
        yield 'text' => ['text', []];
        yield 'screenshot' => ['screenshot', [sys_get_temp_dir() . '/mobile-driver-test.png']];
        yield 'scroll' => ['scroll', ['down']];
        yield 'tap' => ['tap', ['10', '20']];
        yield 'back' => ['back', []];
    }

    public function testStartRefusesExistingStateWithoutStartingProcesses(): void
    {
        $state_directory = $this->directory . '/tmp/mobile_ui_driver';
        mkdir($state_directory, 0777, true);
        file_put_contents($state_directory . '/state.json', '{}');
        $runner = new RecordingProcessRunner();
        $errors = [];
        $commands = new Commands($this->directory, $runner, static function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        self::assertSame(1, $commands->dispatch('start', []));
        self::assertSame([], $runner->startedCommands);
        self::assertStringContainsString('stop', implode("\n", $errors));
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

final class RecordingProcessRunner extends ProcessRunner
{
    /** @var list<list<string>> */
    public array $startedCommands = [];

    public function start(array $command, ?string $working_directory = null, array $environment = []): int
    {
        $this->startedCommands[] = $command;
        throw new RuntimeException('Unexpected process start in this unit test.');
    }
}
