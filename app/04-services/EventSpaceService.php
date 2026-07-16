<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Api\ApiPortalSpaceRepository;
use App\Service\Policy\PortalEventSpacePolicy;

final class EventSpaceService
{
    public function __construct(
        private readonly PortalEventSpacePolicy $policy,
        private readonly ApiPortalSpaceRepository $spaces,
        private readonly OrganizationContextService $organizationContext,
        private readonly ?PortalDomainContext $domainContext = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForOrganization(int $personId, int $orgId): array
    {
        $items = $this->spaces->listByOwnerOrgId($orgId);

        return array_values(array_filter(
            $items,
            fn (array $space): bool => $this->policy->canView($personId, $space, $orgId),
        ));
    }

    /**
     * Lister cuper/spaces brukeren kan administrere.
     * Domene (app_domains) begrenser til aktuell applikasjon når host mapper,
     * med mindre $respectDomain er false eller $applicationId er satt eksplisitt.
     *
     * @return list<array<string, mixed>>
     */
    public function listAdministrable(
        int $personId,
        ?int $applicationId = null,
        bool $respectDomain = true,
    ): array {
        if ($applicationId === null && $respectDomain && $this->domainContext !== null) {
            $applicationId = $this->domainContext->applicationIdFromRequest();
        }

        $byId = [];
        foreach ($this->organizationContext->administrableOrganizations($personId) as $org) {
            $orgId = (int) ($org['org_id'] ?? 0);
            if ($orgId <= 0) {
                continue;
            }
            foreach ($this->listForOrganization($personId, $orgId) as $space) {
                $spaceId = (int) ($space['space_id'] ?? 0);
                if ($spaceId <= 0) {
                    continue;
                }
                if ($applicationId !== null && $applicationId > 0
                    && (int) ($space['application_id'] ?? 0) !== $applicationId) {
                    continue;
                }
                $byId[$spaceId] = $space;
            }
        }

        $list = array_values($byId);
        usort($list, static fn (array $a, array $b): int => strcmp(
            (string) ($a['name'] ?? ''),
            (string) ($b['name'] ?? ''),
        ));

        return $list;
    }

    public function findAccessible(int $personId, int $spaceId, int $orgId): ?array
    {
        $space = $this->spaces->findById($spaceId, $orgId);
        if ($space === null || !$this->policy->canView($personId, $space, $orgId)) {
            return null;
        }

        return $space;
    }

    /** Finn space på tvers av admin-orgs og synk aktiv org/space i session. */
    public function findAccessibleForPerson(int $personId, int $spaceId): ?array
    {
        $activeOrgId = $this->organizationContext->activeOrganizationId();
        if ($activeOrgId !== null && $activeOrgId > 0) {
            $space = $this->findAccessible($personId, $spaceId, $activeOrgId);
            if ($space !== null) {
                $this->organizationContext->setActiveSpaceId($spaceId);

                return $space;
            }
        }

        foreach ($this->organizationContext->administrableOrganizations($personId) as $org) {
            $orgId = (int) ($org['org_id'] ?? 0);
            if ($orgId <= 0 || $orgId === $activeOrgId) {
                continue;
            }
            $space = $this->findAccessible($personId, $spaceId, $orgId);
            if ($space === null) {
                continue;
            }

            $this->organizationContext->setActiveOrganization($orgId, $personId, false);
            $this->organizationContext->setActiveSpaceId($spaceId);

            return $space;
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $personId, int $orgId, array $data, ?int $userId = null): ?int
    {
        $ownerOrgId = (int) ($data['owner_org_id'] ?? $orgId);
        if (!$this->policy->canCreate($personId, $ownerOrgId, $orgId)) {
            return null;
        }

        return $this->spaces->create($data, $userId, $orgId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $personId, int $orgId, int $spaceId, array $data, ?int $userId = null): bool
    {
        $space = $this->spaces->findById($spaceId, $orgId);
        if ($space === null || !$this->policy->canEdit($personId, $space, $orgId)) {
            return false;
        }

        $data['owner_org_id'] = (int) ($space['owner_org_id'] ?? $orgId);
        $data['application_id'] = (int) ($space['application_id'] ?? $data['application_id'] ?? 0);

        return $this->spaces->update($spaceId, $data, $userId, $orgId);
    }

    public function archive(int $personId, int $orgId, int $spaceId, ?int $userId = null): bool
    {
        $space = $this->spaces->findById($spaceId, $orgId);
        if ($space === null || !$this->policy->canArchive($personId, $space, $orgId)) {
            return false;
        }

        return $this->spaces->archive($spaceId, $orgId);
    }
}
