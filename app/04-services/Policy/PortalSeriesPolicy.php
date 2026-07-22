<?php

declare(strict_types=1);

namespace App\Service\Policy;

use App\Repository\Pdo\PdoPortalSpaceParticipationRepository;

final class PortalSeriesPolicy
{
    public function __construct(
        private readonly PortalOrganizationPolicy $organizationPolicy,
        private readonly PdoPortalSpaceParticipationRepository $participation,
    ) {
    }

    /**
     * Lesetilgang: serieeier eller aktiv seriearrangør (inkl. underordnede runder).
     */
    public function canView(int $personId, array $series, int $activeOrgId): bool
    {
        if (!$this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId)) {
            return false;
        }

        $ownerOrgId = (int) ($series['owner_org_id'] ?? 0);
        if ($ownerOrgId === $activeOrgId) {
            return true;
        }

        $seriesId = (int) ($series['series_id'] ?? 0);

        return $seriesId > 0 && $this->participation->orgIsSeriesOrganizer($activeOrgId, $seriesId);
    }

    public function canCreate(int $personId, int $ownerOrgId, int $activeOrgId): bool
    {
        return $ownerOrgId === $activeOrgId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    public function canEdit(int $personId, array $series, int $activeOrgId): bool
    {
        $ownerOrgId = (int) ($series['owner_org_id'] ?? 0);

        return $ownerOrgId === $activeOrgId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    public function canArchive(int $personId, array $series, int $activeOrgId): bool
    {
        return $this->canEdit($personId, $series, $activeOrgId);
    }

    public function canCreateChildSeries(int $personId, array $parentSeries, int $activeOrgId): bool
    {
        return $this->canEdit($personId, $parentSeries, $activeOrgId);
    }

    public function canViewEvents(int $personId, array $series, int $activeOrgId): bool
    {
        if ($this->canView($personId, $series, $activeOrgId)) {
            return true;
        }

        if (!$this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId)) {
            return false;
        }

        $seriesId = (int) ($series['series_id'] ?? 0);

        return $seriesId > 0 && $this->participation->orgHostsEventsInSeries($activeOrgId, $seriesId);
    }
}
