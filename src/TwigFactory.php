<?php

declare(strict_types=1);

namespace App;

use App\Config\AppConfig;
use App\Config\TranslationKeys;
use PhpLocalization\Localization;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/** Builds the Twig environment with the t() translation function and the TK key constants. */
final class TwigFactory
{
    public const LANGUAGE = 'en';

    public static function create(string $baseDir, AppConfig $config): Environment
    {
        $localization = new Localization([
            'driver' => 'json',
            'langDir' => $baseDir . '/lang/',
            'defaultLang' => self::LANGUAGE,
            'fallBackLang' => self::LANGUAGE,
        ]);
        // shortcut: no template cache, one small page renders fast; add 'cache' if pages grow
        $twig = new Environment(new FilesystemLoader($baseDir . '/templates'), [
            'autoescape' => 'html',
            'strict_variables' => $config->debug,
            'debug' => $config->debug,
        ]);
        // Snapshots are stored in UTC; the |date filter shows them in the configured local time
        $twig->getExtension(CoreExtension::class)->setTimezone($config->timezone);
        $twig->addFunction(new TwigFunction(
            't',
            // php-localization's str_ireplace only accepts string replacements
            static fn (string $key, array $params = []): string
                => (string) $localization->lang($key, array_map('strval', $params)),
        ));
        $twig->addGlobal('TK', new TranslationKeys());

        return $twig;
    }
}
