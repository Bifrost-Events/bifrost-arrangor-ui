<?php

declare(strict_types=1);

namespace App\Service\Policy;

use App\Repository\Pdo\PdoPortalSpaceParticipationRepository;

final class PortalEventSpacePolicy
{
    public function __construct(
        private readonly PortalOrganizationPolicy $organizationPolicy,
        private readonly PdoPortalSpaceParticipationRepository $participation,
    ) {
    }

    public function canAdministerCup(int $personId, array $space, int $activeOrgId): bool
    {
        $ownerOrgId = (int) ($space['owner_org_id'] ?? 0);

        return $ownerOrgId > 0
            && $ownerOrgId === $activeOrgId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    public function canView(int $personId, array $space, int $activeOrgId): bool
    {
        if ($this->canAdministerCup($personId, $space, $activeOrgId)) {
            return true;
        }

        if (!$this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId)) {
            return false;
        }

        $spaceId = (int) ($space['space_id'] ?? 0);

        return $spaceId > 0 && $this->participation->orgHostsEventsInSpace($activeOrgId, $spaceId);
    }

    public function canCreate(int $personId, int $ownerOrgId, int $activeOrgId): bool
    {
        return $ownerOrgId === $activeOrgId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    public function canEdit(int $personId, array $space, int $activeOrgId): bool
    {
        return $this->canAdministerCup($personId, $space, $activeOrgId);
    }

    public function canArchive(int $personId, array $space, int $activeOrgId): bool
    {
        return $this->canEdit($personId, $space, $activeOrgId);
    }

    public function canManageSeries(int $personId, array $space, int $activeOrgId): bool
    {
        return $this->canEdit($personId, $space, $activeOrgId);
    }
}
