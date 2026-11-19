<?php

declare(strict_types=1);

namespace App;

use App\Config\AppConfig;
use Twig\Environment;

/** The one page: assembles summary, chart and the requested table page as template context. */
final class StatisticsPage
{
    public const TEMPLATE_INDEX = 'index.html.twig';
    public const TEMPLATE_ERROR = 'error.html.twig';

    /** @param ?SnapshotRepository $repository null when the database file does not exist yet */
    public function __construct(
        private readonly ?SnapshotRepository $repository,
        private readonly AppConfig $config,
        private readonly Environment $twig,
    ) {
    }

    /** Validates the ?page= query value; anything but a positive integer means page 1. */
    public static function pageFromQuery(mixed $raw): int
    {
        $page = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        return $page === false ? 1 : $page;
    }

    public function render(int $page): string
    {
        $context = [
            'has_database' => $this->repository !== null,
            'latest' => null,
            'rows' => [],
            'page' => 1,
            'page_count' => 1,
            'chart_points' => '',
            'chart_count' => 0,
            'chart_width' => OpenCountChart::WIDTH,
            'chart_height' => OpenCountChart::HEIGHT,
        ];
        if ($this->repository === null) {
            return $this->twig->render(self::TEMPLATE_INDEX, $context);
        }

        $pageCount = max(1, (int) ceil($this->repository->count() / $this->config->pageSize));
        $page = min($page, $pageCount);
        $recent = $this->repository->recent($this->config->chartPoints);

        return $this->twig->render(self::TEMPLATE_INDEX, [
            'latest' => $this->repository->latest(),
            'rows' => $this->repository->page($page, $this->config->pageSize),
            'page' => $page,
            'page_count' => $pageCount,
            'chart_points' => OpenCountChart::points($recent),
            'chart_count' => count($recent),
        ] + $context);
    }
}
