<?php

declare(strict_types=1);

namespace App\Service\Policy;

final class PortalEventPolicy
{
    public function __construct(
        private readonly PortalOrganizationPolicy $organizationPolicy,
    ) {
    }

    public function canEdit(int $personId, array $event, int $activeOrgId): bool
    {
        $ownerOrgId = (int) ($event['owner_org_id'] ?? 0);

        return $ownerOrgId === $activeOrgId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    public function canCreate(int $personId, int $ownerOrgId, int $activeOrgId): bool
    {
        return $ownerOrgId === $activeOrgId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    /**
     * Cupadmin kan opprette stevne i cupens serie for valgfri arrangørorganisasjon.
     *
     * @param array<string, mixed> $series
     * @param array<string, mixed> $space
     */
    public function canCreateInSpace(
        int $personId,
        int $ownerOrgId,
        int $activeOrgId,
        array $series,
        array $space,
    ): bool {
        if ($this->canCreate($personId, $ownerOrgId, $activeOrgId)) {
            return true;
        }
        if ($ownerOrgId <= 0) {
            return false;
        }
        $spaceId = (int) ($space['space_id'] ?? 0);
        $spaceOwner = (int) ($space['owner_org_id'] ?? 0);
        $seriesSpace = (int) ($series['space_id'] ?? 0);

        return $spaceId > 0
            && $spaceOwner === $activeOrgId
            && $seriesSpace === $spaceId
            && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId);
    }

    /**
     * Serieeier kan se arrangementer i serien selv om annen org eier dem.
     */
    public function canView(int $personId, array $event, int $activeOrgId, ?array $series = null): bool
    {
        if ($this->canEdit($personId, $event, $activeOrgId)) {
            return true;
        }

        if ($series !== null) {
            $seriesOwnerOrgId = (int) ($series['owner_org_id'] ?? 0);
            if ($seriesOwnerOrgId === $activeOrgId
                && $this->organizationPolicy->canAdministerOrganization($personId, $activeOrgId)) {
                return true;
            }
        }

        return false;
    }

    public function canArchive(int $personId, array $event, int $activeOrgId): bool
    {
        return $this->canEdit($personId, $event, $activeOrgId);
    }

    public function canPublish(int $personId, array $event, int $activeOrgId): bool
    {
        return $this->canEdit($personId, $event, $activeOrgId);
    }

    public function canManageRegistrations(int $personId, array $event, int $activeOrgId): bool
    {
        return false;
    }

    public function canManageResults(int $personId, array $event, int $activeOrgId): bool
    {
        return false;
    }
}
