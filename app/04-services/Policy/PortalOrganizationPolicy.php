<?php

declare(strict_types=1);

namespace App\Service\Policy;

use App\Repository\Pdo\PdoPortalMembershipRepository;

final class PortalOrganizationPolicy
{
    private const ADMIN_ROLE_KEYS = ['org_owner', 'org_admin'];

    public function __construct(
        private readonly PdoPortalMembershipRepository $memberships,
    ) {
    }

    /** @param list<string> $roleKeys */
    public function hasAdminRole(array $roleKeys): bool
    {
        return array_intersect($roleKeys, self::ADMIN_ROLE_KEYS) !== [];
    }

    public function canAdministerOrganization(int $personId, int $orgId): bool
    {
        return $this->memberships->personIsOrgAdmin($personId, $orgId);
    }
}
