<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use Closure;
use RuntimeException;
use Throwable;

/** Validates and dispatches the mobile driver's small command line interface. */
final class Commands
{
    private const USAGE = 'Usage: tools/mobile_ui_driver.bat start|open [path]|text|screenshot <absolute.png>|scroll up|down|tap <x> <y>|back|stop';
    private const SCREEN_XML_PATH = '/sdcard/window.xml';
    private const SCREENSHOT_EXTENSION = 'png';
    private const ROOT_PATH = '/';
    private readonly string $statePath;
    private readonly Closure $writeError;
    private readonly Emulator $emulator;
    private readonly DriverLifecycle $lifecycle;

    public function __construct(
        string $projectRoot,
        ?ProcessRunner $processRunner = null,
        ?Closure $writeError = null,
    ) {
        $processRunner ??= new ProcessRunner();
        $this->writeError = $writeError ?? static function (string $message): void {
            fwrite(STDERR, $message . PHP_EOL);
        };
        $this->statePath = $projectRoot . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR
            . 'mobile_ui_driver' . DIRECTORY_SEPARATOR . 'state.json';
        $this->emulator = new Emulator($processRunner);
        $this->lifecycle = new DriverLifecycle($projectRoot, $processRunner, $this->emulator);
    }

    /** @param list<string> $arguments */
    public function dispatch(string $command, array $arguments): int
    {
        try {
            return match ($command) {
                'start' => $this->noArguments($command, $arguments, fn (): int => $this->lifecycle->start()),
                'open' => $this->open($arguments),
                'text' => $this->noArguments($command, $arguments, fn (): int => $this->text()),
                'screenshot' => $this->oneArgument($command, $arguments, fn (string $path): int => $this->screenshot($path)),
                'scroll' => $this->scroll($arguments),
                'tap' => $this->tap($arguments),
                'back' => $this->noArguments($command, $arguments, fn (): int => $this->back()),
                'stop' => $this->noArguments($command, $arguments, fn (): int => $this->lifecycle->stop()),
                default => $this->usage('Unknown command: ' . $command),
            };
        } catch (Throwable $exception) {
            ($this->writeError)($exception->getMessage());

            return 1;
        }
    }

    /** @param list<string> $arguments */
    private function open(array $arguments): int
    {
        if (count($arguments) > 1) {
            return $this->usage('open accepts at most one path.');
        }
        $path = $arguments[0] ?? self::ROOT_PATH;
        if (self::isInvalidLocalPath($path)) {
            return $this->usage('open path must be a path on the local statistics page.');
        }
        $state = $this->requireRunningState();
        $url = 'http://10.0.2.2:' . $state->httpPort . $path;
        $this->assertSuccessful($this->emulator->runAdb($state->emulatorSerial, [
            'shell',
            'am',
            'start',
            '-a',
            'android.intent.action.VIEW',
            '-d',
            $url,
        ]), 'open the statistics page in Chrome');

        return 0;
    }

    private function text(): int
    {
        $state = $this->requireRunningState();
        $serial = $state->emulatorSerial;
        $this->assertSuccessful($this->emulator->runAdb($serial, ['shell', 'uiautomator', 'dump', self::SCREEN_XML_PATH]), 'read Android screen text');
        $xml = $this->emulator->runAdb($serial, ['shell', 'cat', self::SCREEN_XML_PATH]);
        $this->assertSuccessful($xml, 'read Android screen text');
        $this->emulator->runAdb($serial, ['shell', 'rm', self::SCREEN_XML_PATH]);
        $lines = ScreenText::parse($xml->output);
        fwrite(STDOUT, $lines === [] ? "No visible text found.\n" : implode(PHP_EOL, $lines) . PHP_EOL);

        return 0;
    }

    private function screenshot(string $path): int
    {
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
        if (!$isAbsolute || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== self::SCREENSHOT_EXTENSION) {
            return $this->usage('screenshot requires an absolute .png path.');
        }
        if (!is_dir(dirname($path))) {
            throw new RuntimeException('Screenshot parent directory does not exist.');
        }
        $state = $this->requireRunningState();
        $result = $this->emulator->runAdb($state->emulatorSerial, ['exec-out', 'screencap', '-p']);
        $this->assertSuccessful($result, 'capture Android screen');
        if (file_put_contents($path, $result->output, LOCK_EX) === false) {
            throw new RuntimeException('Could not write screenshot: ' . $path);
        }
        fwrite(STDOUT, 'Screenshot saved: ' . $path . PHP_EOL);

        return 0;
    }

    /** @param list<string> $arguments */
    private function scroll(array $arguments): int
    {
        if (count($arguments) !== 1 || !in_array($arguments[0], ['up', 'down'], true)) {
            return $this->usage('scroll requires up or down.');
        }
        $state = $this->requireRunningState();
        $fromY = $arguments[0] === 'down' ? 500 : 1800;
        $toY = $arguments[0] === 'down' ? 1800 : 500;
        $this->assertSuccessful($this->emulator->runAdb($state->emulatorSerial, [
            'shell', 'input', 'swipe', '540', (string) $fromY, '540', (string) $toY, '500',
        ]), 'scroll Android screen');

        return 0;
    }

    /** @param list<string> $arguments */
    private function tap(array $arguments): int
    {
        $hasValidCoordinateCount = count($arguments) === 2;
        $hasValidCoordinates = $hasValidCoordinateCount
            && ctype_digit($arguments[0])
            && ctype_digit($arguments[1]);
        if (!$hasValidCoordinates) {
            return $this->usage('tap requires non-negative x and y coordinates.');
        }
        $state = $this->requireRunningState();
        $this->assertSuccessful($this->emulator->runAdb($state->emulatorSerial, ['shell', 'input', 'tap', ...$arguments]), 'tap Android screen');

        return 0;
    }

    private function back(): int
    {
        $state = $this->requireRunningState();
        $this->assertSuccessful($this->emulator->runAdb($state->emulatorSerial, ['shell', 'input', 'keyevent', 'KEYCODE_BACK']), 'navigate back in Chrome');

        return 0;
    }

    private function requireRunningState(): DriverState
    {
        $state = DriverState::load($this->statePath);
        $hasNoState = $state === null;
        $hasNoEmulator = $state !== null && $state->emulatorSerial === '';
        if ($hasNoState || $hasNoEmulator) {
            throw new RuntimeException('The mobile UI driver is not running. Run "start" first.');
        }

        return $state;
    }

    /** @param list<string> $arguments */
    private function noArguments(string $command, array $arguments, callable $callback): int
    {
        return $arguments === [] ? $callback() : $this->usage($command . ' does not accept arguments.');
    }

    /** @param list<string> $arguments */
    private function oneArgument(string $command, array $arguments, callable $callback): int
    {
        return count($arguments) === 1 ? $callback($arguments[0]) : $this->usage($command . ' requires one argument.');
    }

    private function usage(string $message): int
    {
        ($this->writeError)($message . PHP_EOL . self::USAGE);

        return 2;
    }

    private function assertSuccessful(ProcessResult $result, string $action): void
    {
        if (!$result->isSuccessful()) {
            throw new RuntimeException('Could not ' . $action . ': ' . trim($result->output));
        }
    }

    private static function isInvalidLocalPath(string $path): bool
    {
        $hasInvalidPathCharacters = preg_match('/^\/[A-Za-z0-9\/_\.\-?=&%+]*$/D', $path) !== 1;
        $hasExternalUrlParts = parse_url($path, PHP_URL_SCHEME) !== null || parse_url($path, PHP_URL_HOST) !== null;

        return $path === '' || $hasInvalidPathCharacters || $hasExternalUrlParts;
    }
}
