<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Api\ApiPortalSpaceRepository;

final class PortalEventLabelResolver
{
    public function __construct(
        private readonly ApiPortalSpaceRepository $spaces,
    ) {
    }

    public function resolveForSpace(?array $space): PortalEventTerminology
    {
        if (is_array($space['labels'] ?? null)) {
            return new PortalEventTerminology($space['labels']);
        }

        $defaults = require dirname(__DIR__, 2) . '/config/terminology-defaults.php';
        if (!is_array($defaults)) {
            $defaults = [];
        }

        $merged = $defaults;
        if ($space !== null) {
            $appLabels = $this->decodeLabels($space['application_ui_labels_json'] ?? null);
            $spaceLabels = $this->decodeLabels($space['ui_labels_json'] ?? null);
            $merged = $this->mergeLabels($merged, $appLabels);
            $merged = $this->mergeLabels($merged, $spaceLabels);
        }

        return new PortalEventTerminology($merged);
    }

    public function resolveBySpaceId(int $spaceId, int $orgId = 0): PortalEventTerminology
    {
        return $this->resolveForSpace($this->spaces->findById($spaceId, $orgId));
    }

    /** @return array<string, array{singular: string, plural: string}> */
    private function decodeLabels(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array{singular: string, plural: string}> $base
     * @param array<string, mixed> $overrides
     * @return array<string, array{singular: string, plural: string}>
     */
    private function mergeLabels(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $base[$key] = [
                'singular' => (string) ($value['singular'] ?? $base[$key]['singular'] ?? $key),
                'plural' => (string) ($value['plural'] ?? $base[$key]['plural'] ?? $key),
            ];
        }

        return $base;
    }
}
