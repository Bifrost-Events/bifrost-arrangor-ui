<?php

declare(strict_types=1);

namespace App\Service;

final class PortalEventTerminology
{
    /** @param array<string, array{singular: string, plural: string}> $labels */
    public function __construct(private readonly array $labels)
    {
    }

    public function singular(string $key): string
    {
        return (string) ($this->labels[$key]['singular'] ?? $key);
    }

    public function plural(string $key): string
    {
        return (string) ($this->labels[$key]['plural'] ?? $key);
    }

    /** @return array<string, array{singular: string, plural: string}> */
    public function all(): array
    {
        return $this->labels;
    }
}
