<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Api\ApiPortalEventRepository;
use App\Repository\Api\ApiPortalSeriesRepository;
use App\Service\Policy\PortalEventPolicy;
use App\Service\Policy\PortalSeriesPolicy;

final class EventService
{
    public function __construct(
        private readonly PortalEventPolicy $policy,
        private readonly PortalSeriesPolicy $seriesPolicy,
        private readonly ApiPortalEventRepository $events,
        private readonly ApiPortalSeriesRepository $series,
        private readonly OrganizationContextService $organizationContext,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForSeries(int $personId, int $seriesId, int $orgId): array
    {
        $series = $this->series->findById($seriesId, $orgId);
        if ($series === null || !$this->seriesPolicy->canViewEvents($personId, $series, $orgId)) {
            return [];
        }

        return array_values(array_filter(
            $this->events->listBySeriesId($seriesId, $orgId),
            fn (array $event): bool => $this->policy->canView($personId, $event, $orgId, $series),
        ));
    }

    /** @return list<array<string, mixed>> */
    public function listForSpace(int $personId, int $spaceId, int $orgId): array
    {
        $items = $this->events->listBySpaceId($spaceId, $orgId);

        return array_values(array_filter($items, function (array $event) use ($personId, $orgId): bool {
            $seriesId = (int) ($event['series_id'] ?? 0);
            $series = $seriesId > 0 ? $this->series->findById($seriesId, $orgId) : null;

            return $this->policy->canView($personId, $event, $orgId, $series);
        }));
    }

    public function findAccessible(int $personId, int $eventId, int $orgId): ?array
    {
        $event = $this->events->findById($eventId, $orgId);
        if ($event === null) {
            return null;
        }
        $seriesId = (int) ($event['series_id'] ?? 0);
        $series = $seriesId > 0 ? $this->series->findById($seriesId, $orgId) : null;
        if (!$this->policy->canView($personId, $event, $orgId, $series)) {
            return null;
        }

        return $event;
    }

    /**
     * Finn arrangement på tvers av admin-orgs (session synkes ikke her — bruk PortalWorkContext).
     *
     * @return array{event: array<string, mixed>, org_id: int}|null
     */
    public function findAccessibleForPerson(int $personId, int $eventId): ?array
    {
        $activeOrgId = $this->organizationContext->activeOrganizationId();
        if ($activeOrgId !== null && $activeOrgId > 0) {
            $event = $this->findAccessible($personId, $eventId, $activeOrgId);
            if ($event !== null) {
                return ['event' => $event, 'org_id' => $activeOrgId];
            }
        }

        foreach ($this->organizationContext->administrableOrganizations($personId) as $org) {
            $orgId = (int) ($org['org_id'] ?? 0);
            if ($orgId <= 0 || $orgId === $activeOrgId) {
                continue;
            }
            $event = $this->findAccessible($personId, $eventId, $orgId);
            if ($event !== null) {
                return ['event' => $event, 'org_id' => $orgId];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $series
     * @param array<string, mixed>|null $space
     */
    public function create(
        int $personId,
        int $orgId,
        array $data,
        ?int $userId = null,
        ?array $series = null,
        ?array $space = null,
    ): ?int {
        $ownerOrgId = (int) ($data['owner_org_id'] ?? $orgId);
        $allowed = $series !== null && $space !== null
            ? $this->policy->canCreateInSpace($personId, $ownerOrgId, $orgId, $series, $space)
            : $this->policy->canCreate($personId, $ownerOrgId, $orgId);
        if (!$allowed) {
            return null;
        }

        // Org-kontekst mot API er aktiv cup-/serie-org (ikke nødvendigvis stevnearrangør).
        $newId = $this->events->create($data, $userId, $orgId);
        if ($newId === null || $newId === 0) {
            return null;
        }

        return $newId;
    }

    /** @param array<string, mixed> $data */
    public function update(int $personId, int $orgId, int $eventId, array $data, ?int $userId = null): bool
    {
        $event = $this->events->findById($eventId, $orgId);
        if ($event === null || !$this->policy->canEdit($personId, $event, $orgId)) {
            return false;
        }

        $data['owner_org_id'] = (int) ($event['owner_org_id'] ?? $orgId);

        return $this->events->update($eventId, $data, $userId, $orgId);
    }

    public function archive(int $personId, int $orgId, int $eventId, ?int $userId = null): bool
    {
        $event = $this->events->findById($eventId, $orgId);
        if ($event === null || !$this->policy->canArchive($personId, $event, $orgId)) {
            return false;
        }

        return $this->events->archive($eventId, $orgId);
    }
}
