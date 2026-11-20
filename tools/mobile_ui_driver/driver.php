<?php

declare(strict_types=1);

use Tools\MobileUiDriver\Commands;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

try {
    $arguments = $argv ?? [];
    exit((new Commands($projectRoot))->dispatch($arguments[1] ?? '', array_slice($arguments, 2)));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Mobile UI driver error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
