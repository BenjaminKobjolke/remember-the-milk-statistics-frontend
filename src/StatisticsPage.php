<?php

declare(strict_types=1);

namespace App;

use App\Config\AppConfig;
use DateTimeImmutable;
use DateTimeZone;
use Twig\Environment;

/** The one page: assembles summary, the chart of one day and the requested table page as template context. */
final class StatisticsPage
{
    public const TEMPLATE_INDEX = 'index.html.twig';
    public const TEMPLATE_ERROR = 'error.html.twig';
    private const DAY_FORMAT = 'Y-m-d';

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

    /** Validates the ?day= query value (Y-m-d); anything invalid or in the future means today. Local midnight. */
    public static function dayFromQuery(mixed $raw, DateTimeZone $timezone): DateTimeImmutable
    {
        $today = new DateTimeImmutable('today', $timezone);
        $day = is_string($raw) ? DateTimeImmutable::createFromFormat('!' . self::DAY_FORMAT, $raw, $timezone) : false;
        // the round trip rejects dates PHP silently rolls over, like 2026-02-30
        $isValid = $day !== false && $day->format(self::DAY_FORMAT) === $raw;

        return $isValid ? min($day, $today) : $today;
    }

    public function render(int $page, DateTimeImmutable $day): string
    {
        $dayEnd = $day->modify('+1 day');
        $isToday = $day >= new DateTimeImmutable('today', $day->getTimezone());
        $context = [
            'has_database' => $this->repository !== null,
            'latest' => null,
            'rows' => [],
            'page' => 1,
            'page_count' => 1,
            'day' => $day->format(self::DAY_FORMAT),
            'previous_day' => $day->modify('-1 day')->format(self::DAY_FORMAT),
            'next_day' => $isToday ? null : $dayEnd->format(self::DAY_FORMAT),
            'chart_points' => '',
            'chart_count' => 0,
            'chart_ticks' => OpenCountChart::ticks($day, $dayEnd),
            'chart_levels' => [],
            'chart_width' => OpenCountChart::WIDTH,
            'chart_height' => OpenCountChart::HEIGHT,
        ];
        if ($this->repository === null) {
            return $this->twig->render(self::TEMPLATE_INDEX, $context);
        }

        $pageCount = max(1, (int) ceil($this->repository->count() / $this->config->pageSize));
        $page = min($page, $pageCount);
        $snapshots = $this->repository->between($day, $dayEnd);

        return $this->twig->render(self::TEMPLATE_INDEX, [
            'latest' => $this->repository->latest(),
            'rows' => $this->repository->page($page, $this->config->pageSize),
            'page' => $page,
            'page_count' => $pageCount,
            'chart_points' => OpenCountChart::points($snapshots, $day, $dayEnd),
            'chart_count' => count($snapshots),
            'chart_levels' => OpenCountChart::levels($snapshots),
        ] + $context);
    }
}
