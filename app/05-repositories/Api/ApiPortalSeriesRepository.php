<?php

declare(strict_types=1);

namespace App\Repository\Api;

use App\Service\EventsApiClient;

final class ApiPortalSeriesRepository
{
    public function __construct(
        private readonly EventsApiClient $api,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listRootBySpaceId(int $spaceId, int $orgId = 0): array
    {
        $hierarchy = $this->hierarchy($spaceId, $orgId);

        return $hierarchy['roots'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function listChildrenByParentId(int $parentSeriesId, int $orgId = 0, int $spaceId = 0): array
    {
        $hierarchy = $this->hierarchy($spaceId, $orgId);
        $children = $hierarchy['children'] ?? [];

        return is_array($children[$parentSeriesId] ?? null) ? $children[$parentSeriesId] : [];
    }

    public function findById(int $seriesId, int $orgId = 0): ?array
    {
        $result = $this->api->getSeries($orgId, $seriesId);
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
        $result = $this->api->createSeries($orgId, $data);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return null;
        }

        return (int) ($result['data']['series_id'] ?? 0) ?: null;
    }

    /** @param array<string, mixed> $data */
    public function update(int $seriesId, array $data, ?int $byUserId = null, int $orgId = 0): bool
    {
        unset($byUserId);
        if ($orgId <= 0) {
            $orgId = (int) ($data['owner_org_id'] ?? 0);
        }
        $result = $this->api->updateSeries($orgId, $seriesId, $data);

        return (bool) ($result['ok'] ?? false);
    }

    public function archive(int $seriesId, int $orgId): bool
    {
        $result = $this->api->archiveSeries($orgId, $seriesId);

        return (bool) ($result['ok'] ?? false);
    }

    /**
     * @return array{series: array<string, mixed>, event_choices: list<array<string, mixed>>}|null
     */
    public function getCupStandings(int $seriesId, int $orgId): ?array
    {
        $result = $this->api->getCupStandings($orgId, $seriesId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            EventsApiClient::rememberLastListError($result['error'] ?? 'Kunne ikke hente sammenlagt-innstillinger.');

            return null;
        }
        EventsApiClient::rememberLastListError(null);
        $data = $result['data'];

        return [
            'series' => is_array($data['series'] ?? null) ? $data['series'] : [],
            'event_choices' => is_array($data['event_choices'] ?? null) ? $data['event_choices'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, error: string|null}
     */
    public function updateCupStandings(int $seriesId, array $body, int $orgId): array
    {
        $result = $this->api->updateCupStandings($orgId, $seriesId, $body);
        if (!($result['ok'] ?? false)) {
            $err = $result['error'] ?? 'Kunne ikke lagre sammenlagt-innstillinger.';
            EventsApiClient::rememberLastListError($err);

            return ['ok' => false, 'error' => $err];
        }
        EventsApiClient::rememberLastListError(null);

        return ['ok' => true, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSeasonStructureScoring(int $seriesId, int $orgId): ?array
    {
        $result = $this->api->getSeasonStructureScoring($orgId, $seriesId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            EventsApiClient::rememberLastListError($result['error'] ?? 'Kunne ikke hente struktur/sammenlagt.');

            return null;
        }
        EventsApiClient::rememberLastListError(null);

        return is_array($result['data']) ? $result['data'] : null;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, error: string|null, errors?: array<string, string>}
     */
    public function updateSeasonStructure(int $seriesId, array $body, int $orgId): array
    {
        $result = $this->api->updateSeasonStructure($orgId, $seriesId, $body);
        if (!($result['ok'] ?? false)) {
            $err = $result['error'] ?? 'Kunne ikke lagre struktur.';
            EventsApiClient::rememberLastListError($err);

            return ['ok' => false, 'error' => $err, 'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : []];
        }
        EventsApiClient::rememberLastListError(null);

        return ['ok' => true, 'error' => null];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, error: string|null, errors?: array<string, string>}
     */
    public function updateSeasonScoringConfig(int $seriesId, array $body, int $orgId): array
    {
        $result = $this->api->updateSeasonScoringConfig($orgId, $seriesId, $body);
        if (!($result['ok'] ?? false)) {
            $err = $result['error'] ?? 'Kunne ikke lagre sammenlagtregler.';
            EventsApiClient::rememberLastListError($err);

            return ['ok' => false, 'error' => $err, 'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : []];
        }
        EventsApiClient::rememberLastListError(null);

        return ['ok' => true, 'error' => null];
    }

    /** @return array{roots: list<array<string, mixed>>, children: array<int, list<array<string, mixed>>>} */
    private function hierarchy(int $spaceId, int $orgId): array
    {
        $result = $this->api->listSeriesHierarchy($orgId, $spaceId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            return ['roots' => [], 'children' => []];
        }

        return [
            'roots' => is_array($result['data']['roots'] ?? null) ? $result['data']['roots'] : [],
            'children' => is_array($result['data']['children'] ?? null) ? $result['data']['children'] : [],
        ];
    }
}
