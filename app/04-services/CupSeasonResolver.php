<?php

declare(strict_types=1);

namespace App\Service;

/** Velger aktiv sesong (root series) fra hierarki i en cup. */
final class CupSeasonResolver
{
    /**
     * @param list<array<string, mixed>> $roots hierarchy roots for space
     * @return array<string, mixed>|null
     */
    public function resolveActiveRoot(array $roots, ?int $preferredSeriesId = null): ?array
    {
        if ($roots === []) {
            return null;
        }

        if ($preferredSeriesId !== null && $preferredSeriesId > 0) {
            foreach ($roots as $root) {
                if ((int) ($root['series_id'] ?? 0) === $preferredSeriesId) {
                    return $root;
                }
            }
        }

        $year = (int) date('Y');
        $scored = [];
        foreach ($roots as $root) {
            $label = (string) ($root['season_label'] ?? $root['name'] ?? '');
            $type = (string) ($root['series_type'] ?? '');
            $score = 0;
            if ($type === 'season') {
                $score += 10;
            }
            if (str_contains($label, (string) $year)) {
                $score += 50;
            }
            if (preg_match('/(19|20)\d{2}/', $label, $m)) {
                $score += max(0, 20 - abs($year - (int) $m[0]));
            }
            $scored[] = ['score' => $score, 'root' => $root];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored[0]['root'] ?? $roots[0];
    }

    /**
     * Alle series_id i et undertre (root inkludert).
     *
     * @param array<string, mixed> $root
     * @param array<int, list<array<string, mixed>>> $children parent_id => children
     * @return list<int>
     */
    public function collectSeriesIds(array $root, array $children): array
    {
        $ids = [];
        $queue = [(int) ($root['series_id'] ?? 0)];
        while ($queue !== []) {
            $id = array_shift($queue);
            if ($id <= 0 || in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = (int) ($child['series_id'] ?? 0);
            }
        }

        return $ids;
    }

    /**
     * series_id → sesongnavn (root) for hele hierarkiet.
     *
     * @param list<array<string, mixed>> $roots
     * @param array<int, list<array<string, mixed>>> $children
     * @return array<int, string>
     */
    public function seasonLabelsBySeriesId(array $roots, array $children): array
    {
        $map = [];
        foreach ($roots as $root) {
            $label = trim((string) ($root['name'] ?? $root['season_label'] ?? ''));
            if ($label === '') {
                $label = 'Sesong #' . (int) ($root['series_id'] ?? 0);
            }
            foreach ($this->collectSeriesIds($root, $children) as $sid) {
                $map[$sid] = $label;
            }
        }

        return $map;
    }
}
