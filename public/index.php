<?php

/**
 * Front controller and central error handler: every request renders the statistics page.
 * Expected failures render a translated message; details go to the Logger only.
 */

declare(strict_types=1);

use App\Config\AppConfig;
use App\Logger;
use App\SnapshotRepository;
use App\StatisticsPage;
use App\TwigFactory;

require __DIR__ . '/../vendor/autoload.php';

$baseDir = dirname(__DIR__);
$logger = new Logger(Logger::ERROR);

try {
    $config = AppConfig::load($baseDir . '/config');
    $logger = new Logger($config->logLevel);
    $twig = TwigFactory::create($baseDir, $config);
} catch (Throwable $e) {
    // Nothing to render with yet; fail with a plain server-side message (see README setup)
    $logger->error('Startup failed', ['exception' => $e]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Configuration error. See the server log.';
    exit;
}

try {
    $repository = null;
    if (is_file($config->dbPath)) {
        $repository = new SnapshotRepository(SnapshotRepository::openReadOnly($config->dbPath));
    } else {
        $logger->warning('Statistics database not found', ['db_path' => $config->dbPath]);
    }
    $page = new StatisticsPage($repository, $config, $twig);
    echo $page->render(
        StatisticsPage::pageFromQuery($_GET['page'] ?? null),
        StatisticsPage::dayFromQuery($_GET['day'] ?? null, new DateTimeZone($config->timezone)),
    );
} catch (Throwable $e) {
    $logger->error('Rendering the statistics page failed', ['exception' => $e]);
    http_response_code(500);
    echo $twig->render(StatisticsPage::TEMPLATE_ERROR, ['detail' => $config->debug ? (string) $e : null]);
}
