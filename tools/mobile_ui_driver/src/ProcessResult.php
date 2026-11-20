<?php

declare(strict_types=1);

namespace Tools\MobileUiDriver;

/** Captured result of a foreground process. */
final readonly class ProcessResult
{
    public function __construct(public string $output, public int $exitCode)
    {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
