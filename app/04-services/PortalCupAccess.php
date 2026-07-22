<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Rolleperspektiv i aktiv cup — uten nye role_keys.
 * - cup_admin: admin på event_spaces.owner_org_id
 * - arranger_admin: admin på org som eier stevne ELLER er aktiv seriearrangør i cupen
 */
final class PortalCupAccess
{
    public function __construct(
        private readonly PortalV3Services $services,
    ) {
    }

    /**
     * @param array<string, mixed> $space
     * @return array{
     *   is_cup_admin: bool,
     *   is_arranger_admin: bool,
     *   can_manage_cup: bool,
     *   can_view_arrangers: bool,
     *   can_view_all_events: bool,
     *   admin_org_ids: list<int>,
     *   arranger_org_ids: list<int>
     * }
     */
    public function forSpace(int $personId, array $space): array
    {
        $spaceOwnerOrgId = (int) ($space['owner_org_id'] ?? 0);
        $adminOrgs = $this->services->organizationContext->administrableOrganizations($personId);
        $adminOrgIds = [];
        foreach ($adminOrgs as $org) {
            $id = (int) ($org['org_id'] ?? 0);
            if ($id > 0) {
                $adminOrgIds[] = $id;
            }
        }
        $adminOrgIds = array_values(array_unique($adminOrgIds));

        $isCupAdmin = $spaceOwnerOrgId > 0
            && in_array($spaceOwnerOrgId, $adminOrgIds, true)
            && $this->services->organizationPolicy->canAdministerOrganization($personId, $spaceOwnerOrgId);

        $arrangerCandidateOrgIds = [];
        $spaceId = (int) ($space['space_id'] ?? 0);
        if ($spaceId > 0) {
            foreach ($this->services->spaceParticipation->listHostOrganizationsInSpace($spaceId) as $host) {
                $arrangerCandidateOrgIds[] = (int) ($host['org_id'] ?? 0);
            }
            foreach ($this->services->spaceParticipation->listSeriesOrganizerOrganizationsInSpace($spaceId) as $org) {
                $arrangerCandidateOrgIds[] = (int) ($org['org_id'] ?? 0);
            }
        }
        $arrangerCandidateOrgIds = array_values(array_unique(array_filter($arrangerCandidateOrgIds)));

        $arrangerOrgIds = array_values(array_intersect($adminOrgIds, $arrangerCandidateOrgIds));
        $isArrangerAdmin = !$isCupAdmin && $arrangerOrgIds !== [];

        return [
            'is_cup_admin' => $isCupAdmin,
            'is_arranger_admin' => $isArrangerAdmin,
            'can_manage_cup' => $isCupAdmin,
            'can_view_arrangers' => $isCupAdmin,
            'can_view_all_events' => $isCupAdmin,
            'admin_org_ids' => $adminOrgIds,
            'arranger_org_ids' => $arrangerOrgIds,
        ];
    }
}
