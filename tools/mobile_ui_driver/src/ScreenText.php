<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

use RuntimeException;
use SimpleXMLElement;

/** Extracts distinct visible labels and their screen bounds from uiautomator XML. */
final class ScreenText
{
    /** @return list<string> */
    public static function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml);
            if (!$root instanceof SimpleXMLElement) {
                throw new RuntimeException('Could not parse Android screen text XML.');
            }

            $lines = [];
            $seen = [];
            self::collect($root, $lines, $seen);

            return $lines;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param list<string> $lines @param array<string, true> $seen */
    private static function collect(SimpleXMLElement $node, array &$lines, array &$seen): void
    {
        $attributes = $node->attributes();
        $bounds = trim((string) ($attributes['bounds'] ?? ''));
        foreach (['text', 'content-desc'] as $attribute) {
            $value = trim((string) ($attributes[$attribute] ?? ''));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $lines[] = $value . ($bounds === '' ? '' : ' ' . $bounds);
        }

        foreach ($node->children() as $child) {
            self::collect($child, $lines, $seen);
        }
    }
}
