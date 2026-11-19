<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/** Missing or invalid configuration; the app refuses to start rather than render half-configured. */
final class ConfigException extends RuntimeException
{
}
