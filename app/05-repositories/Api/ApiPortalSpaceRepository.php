<?php

declare(strict_types=1);

namespace App\Repository\Api;

use App\Service\EventsApiClient;

final class ApiPortalSpaceRepository
{
    public function __construct(
        private readonly EventsApiClient $api,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listByOwnerOrgId(int $orgId): array
    {
        $result = $this->api->listEventSpaces($orgId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            EventsApiClient::rememberLastListError($result['error'] ?? 'Kunne ikke hente Event Spaces fra API.');

            return [];
        }

        EventsApiClient::rememberLastListError(null);

        return array_values(array_filter($result['data'], 'is_array'));
    }

    public function findById(int $spaceId, int $orgId = 0): ?array
    {
        $result = $this->api->getEventSpace($orgId, $spaceId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return null;
        }

        return $result['data'];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?int $byUserId = null, int $orgId = 0): ?int
    {
        unset($byUserId);
        if ($orgId <= 0) {
            $orgId = (int) ($data['owner_org_id'] ?? 0);
        }
        $result = $this->api->createEventSpace($orgId, $data);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return null;
        }

        return (int) ($result['data']['space_id'] ?? 0) ?: null;
    }

    /** @param array<string, mixed> $data */
    public function update(int $spaceId, array $data, ?int $byUserId = null, int $orgId = 0): bool
    {
        unset($byUserId);
        if ($orgId <= 0) {
            $orgId = (int) ($data['owner_org_id'] ?? 0);
        }
        $result = $this->api->updateEventSpace($orgId, $spaceId, $data);
        if (!($result['ok'] ?? false)) {
            EventsApiClient::rememberLastListError($result['error'] ?? 'Kunne ikke lagre Event Space.');

            return false;
        }

        EventsApiClient::rememberLastListError(null);

        return true;
    }

    public function archive(int $spaceId, int $orgId): bool
    {
        $result = $this->api->archiveEventSpace($orgId, $spaceId);

        return (bool) ($result['ok'] ?? false);
    }
}
