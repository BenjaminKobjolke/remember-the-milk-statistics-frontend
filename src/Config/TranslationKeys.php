<?php

declare(strict_types=1);

namespace App\Config;

/** Every translation key used by templates; lang/en.json holds the texts. */
final class TranslationKeys
{
    public const PAGE_TITLE = 'page.title';
    public const PAGE_SUBTITLE = 'page.subtitle';

    public const SUMMARY_OPEN = 'summary.open';
    public const SUMMARY_COMPLETED = 'summary.completed';
    public const SUMMARY_TAKEN_AT = 'summary.taken_at';

    public const CHART_TITLE = 'chart.title';
    public const CHART_LABEL = 'chart.label';

    public const TABLE_TITLE = 'table.title';
    public const TABLE_TAKEN_AT = 'table.taken_at';
    public const TABLE_LIST = 'table.list';
    public const TABLE_OPEN = 'table.open';
    public const TABLE_COMPLETED = 'table.completed';

    public const PAGER_LABEL = 'pager.label';
    public const PAGER_PREVIOUS = 'pager.previous';
    public const PAGER_NEXT = 'pager.next';
    public const PAGER_POSITION = 'pager.position';

    public const EMPTY_NO_DATABASE = 'empty.no_database';
    public const EMPTY_NO_ROWS = 'empty.no_rows';
    public const EMPTY_HINT = 'empty.hint';

    public const ERROR_TITLE = 'error.title';
    public const ERROR_TEXT = 'error.text';
}
