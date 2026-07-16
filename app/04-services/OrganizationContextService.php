<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Pdo\PdoPortalMembershipRepository;
use App\Support\PortalV3Session;
use PDO;

final class OrganizationContextService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?PdoPortalMembershipRepository $memberships = null,
    ) {
    }

    private function memberships(): PdoPortalMembershipRepository
    {
        return $this->memberships ?? new PdoPortalMembershipRepository($this->pdo);
    }

    /** @return list<array<string, mixed>> */
    public function administrableOrganizations(int $personId): array
    {
        if ($personId <= 0) {
            return [];
        }

        return $this->memberships()->listAdministrableOrganizationsForPerson($personId);
    }

    public function syncFromRequest(): void
    {
        if (isset($_GET['organization_id'])) {
            $raw = (string) $_GET['organization_id'];
            PortalV3Session::setOrganizationId($raw === '' || $raw === '0' ? null : (int) $raw);
        }
        if (isset($_GET['space_id'])) {
            $raw = (string) $_GET['space_id'];
            PortalV3Session::setSpaceId($raw === '' || $raw === '0' ? null : (int) $raw);
        }
    }

    public function activeOrganizationId(): ?int
    {
        return PortalV3Session::getOrganizationId();
    }

    public function activeSpaceId(): ?int
    {
        return PortalV3Session::getSpaceId();
    }

    public function resolveActiveOrganization(int $personId): ?array
    {
        $orgs = $this->administrableOrganizations($personId);
        if ($orgs === []) {
            return null;
        }

        $activeId = $this->activeOrganizationId();
        if ($activeId !== null) {
            foreach ($orgs as $org) {
                if ((int) ($org['org_id'] ?? 0) === $activeId) {
                    return $org;
                }
            }
        }

        if (count($orgs) === 1) {
            $only = $orgs[0];
            PortalV3Session::setOrganizationId((int) $only['org_id']);

            return $only;
        }

        return null;
    }

    public function setActiveOrganization(int $orgId, int $personId, bool $clearSpace = true): bool
    {
        foreach ($this->administrableOrganizations($personId) as $org) {
            if ((int) ($org['org_id'] ?? 0) === $orgId) {
                PortalV3Session::setOrganizationId($orgId);
                if ($clearSpace) {
                    PortalV3Session::setSpaceId(null);
                }

                return true;
            }
        }

        return false;
    }

    public function setActiveSpaceId(?int $spaceId): void
    {
        PortalV3Session::setSpaceId($spaceId);
    }
}
