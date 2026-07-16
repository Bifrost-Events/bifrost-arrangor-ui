<?php

declare(strict_types=1);

namespace App\Repository\Api;

use App\Service\EventsApiClient;

final class ApiPortalEventRepository
{
    public function __construct(
        private readonly EventsApiClient $api,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listBySeriesId(int $seriesId, int $orgId = 0): array
    {
        $result = $this->api->listEventsForSeries($orgId, $seriesId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return [];
        }

        return array_values(array_filter($result['data'], 'is_array'));
    }

    /** @return list<array<string, mixed>> */
    public function listBySpaceId(int $spaceId, int $orgId = 0): array
    {
        $result = $this->api->listEventsForSpace($orgId, $spaceId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return [];
        }

        return array_values(array_filter($result['data'], 'is_array'));
    }

    public function findById(int $eventId, int $orgId = 0): ?array
    {
        $result = $this->api->getEvent($orgId, $eventId);
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
        $result = $this->api->createEvent($orgId, $data);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return null;
        }

        return (int) ($result['data']['event_id'] ?? 0) ?: null;
    }

    /** @param array<string, mixed> $data */
    public function update(int $eventId, array $data, ?int $byUserId = null, int $orgId = 0): bool
    {
        unset($byUserId);
        if ($orgId <= 0) {
            $orgId = (int) ($data['owner_org_id'] ?? 0);
        }
        $result = $this->api->updateEvent($orgId, $eventId, $data);

        return (bool) ($result['ok'] ?? false);
    }

    public function archive(int $eventId, int $orgId): bool
    {
        $result = $this->api->archiveEvent($orgId, $eventId);

        return (bool) ($result['ok'] ?? false);
    }
}
