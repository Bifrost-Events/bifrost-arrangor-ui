<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Api\ApiPortalSeriesRepository;
use App\Service\Policy\PortalSeriesPolicy;

final class SeriesService
{
    public function __construct(
        private readonly PortalSeriesPolicy $policy,
        private readonly ApiPortalSeriesRepository $series,
    ) {
    }

    /**
     * @return array{roots: list<array<string, mixed>>, children: array<int, list<array<string, mixed>>>}
     */
    public function hierarchyForSpace(int $personId, int $spaceId, int $orgId): array
    {
        $roots = [];
        $children = [];
        foreach ($this->series->listRootBySpaceId($spaceId, $orgId) as $root) {
            if (!$this->policy->canView($personId, $root, $orgId)) {
                continue;
            }
            $roots[] = $root;
            $sid = (int) ($root['series_id'] ?? 0);
            $children[$sid] = array_values(array_filter(
                $this->series->listChildrenByParentId($sid, $orgId, $spaceId),
                fn (array $child): bool => $this->policy->canView($personId, $child, $orgId),
            ));
        }

        return ['roots' => $roots, 'children' => $children];
    }

    public function findAccessible(int $personId, int $seriesId, int $orgId): ?array
    {
        $series = $this->series->findById($seriesId, $orgId);
        if ($series === null || !$this->policy->canView($personId, $series, $orgId)) {
            return null;
        }

        return $series;
    }

    /** @param array<string, mixed> $data */
    public function create(int $personId, int $orgId, array $data, ?int $userId = null): ?int
    {
        $ownerOrgId = (int) ($data['owner_org_id'] ?? $orgId);
        if (!$this->policy->canCreate($personId, $ownerOrgId, $orgId)) {
            return null;
        }

        $parentId = (int) ($data['parent_series_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $this->series->findById($parentId, $orgId);
            if ($parent === null || !$this->policy->canCreateChildSeries($personId, $parent, $orgId)) {
                return null;
            }
        }

        return $this->series->create($data, $userId, $orgId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $personId, int $orgId, int $seriesId, array $data, ?int $userId = null): bool
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canEdit($personId, $existing, $orgId)) {
            return false;
        }

        $data['owner_org_id'] = (int) ($existing['owner_org_id'] ?? $orgId);
        $data['space_id'] = (int) ($existing['space_id'] ?? $data['space_id'] ?? 0);

        return $this->series->update($seriesId, $data, $userId, $orgId);
    }

    public function archive(int $personId, int $orgId, int $seriesId, ?int $userId = null): bool
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canArchive($personId, $existing, $orgId)) {
            return false;
        }

        return $this->series->archive($seriesId, $orgId);
    }

    /**
     * @return array{series: array<string, mixed>, event_choices: list<array<string, mixed>>}|null
     */
    public function getCupStandings(int $personId, int $orgId, int $seriesId): ?array
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canView($personId, $existing, $orgId)) {
            return null;
        }

        return $this->series->getCupStandings($seriesId, $orgId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, error: string|null}
     */
    public function updateCupStandings(int $personId, int $orgId, int $seriesId, array $data): array
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canEdit($personId, $existing, $orgId)) {
            return ['ok' => false, 'error' => 'Ingen tilgang.'];
        }

        return $this->series->updateCupStandings($seriesId, $data, $orgId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSeasonStructureScoring(int $personId, int $orgId, int $seriesId): ?array
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canView($personId, $existing, $orgId)) {
            return null;
        }

        return $this->series->getSeasonStructureScoring($seriesId, $orgId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, error: string|null, errors?: array<string, string>}
     */
    public function updateSeasonStructure(int $personId, int $orgId, int $seriesId, array $data): array
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canEdit($personId, $existing, $orgId)) {
            return ['ok' => false, 'error' => 'Ingen tilgang.'];
        }

        return $this->series->updateSeasonStructure($seriesId, $data, $orgId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, error: string|null, errors?: array<string, string>}
     */
    public function updateSeasonScoringConfig(int $personId, int $orgId, int $seriesId, array $data): array
    {
        $existing = $this->series->findById($seriesId, $orgId);
        if ($existing === null || !$this->policy->canEdit($personId, $existing, $orgId)) {
            return ['ok' => false, 'error' => 'Ingen tilgang.'];
        }

        return $this->series->updateSeasonScoringConfig($seriesId, $data, $orgId);
    }
}
