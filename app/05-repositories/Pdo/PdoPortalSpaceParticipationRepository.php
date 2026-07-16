<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use PDO;

/** Leser event_*-tabeller for tilgang (samme DB som Events API). */
final class PdoPortalSpaceParticipationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function orgHostsEventsInSpace(int $orgId, int $spaceId): bool
    {
        if ($orgId <= 0 || $spaceId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM event_events e
            INNER JOIN event_series s ON s.series_id = e.series_id AND s.deleted_at IS NULL
            WHERE s.space_id = ? AND e.owner_org_id = ? AND e.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$spaceId, $orgId]);

        return (bool) $stmt->fetchColumn();
    }

    public function orgHostsEventsInSeries(int $orgId, int $seriesId): bool
    {
        if ($orgId <= 0 || $seriesId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM event_events
            WHERE series_id = ? AND owner_org_id = ? AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$seriesId, $orgId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Unike organisasjoner som eier stevner i cupen (valgfritt begrenset til sesongens serie-tre).
     *
     * @param list<int> $seriesIds tom = hele cupen
     * @return list<array{org_id: int, name: string, event_count: int}>
     */
    public function listHostOrganizationsInSpace(int $spaceId, array $seriesIds = []): array
    {
        if ($spaceId <= 0) {
            return [];
        }

        $sql = '
            SELECT o.org_id, o.name, COUNT(e.event_id) AS event_count
            FROM event_events e
            INNER JOIN event_series s ON s.series_id = e.series_id AND s.deleted_at IS NULL
            INNER JOIN org_organizations o ON o.org_id = e.owner_org_id AND o.deleted_at IS NULL
            WHERE s.space_id = ? AND e.deleted_at IS NULL
        ';
        $bind = [$spaceId];
        if ($seriesIds !== []) {
            $seriesIds = array_values(array_filter(array_map('intval', $seriesIds), static fn (int $id): bool => $id > 0));
            if ($seriesIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($seriesIds), '?'));
            $sql .= ' AND e.series_id IN (' . $placeholders . ')';
            $bind = array_merge($bind, $seriesIds);
        }
        $sql .= ' GROUP BY o.org_id, o.name ORDER BY o.name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'org_id' => (int) ($row['org_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'event_count' => (int) ($row['event_count'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Unike (owner_org_id, series_id) for stevner i cupen.
     *
     * @return list<array{org_id: int, series_id: int}>
     */
    public function listOwnerSeriesInSpace(int $spaceId): array
    {
        if ($spaceId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare('
            SELECT DISTINCT e.owner_org_id AS org_id, e.series_id
            FROM event_events e
            INNER JOIN event_series s ON s.series_id = e.series_id AND s.deleted_at IS NULL
            WHERE s.space_id = ? AND e.deleted_at IS NULL AND e.owner_org_id > 0 AND e.series_id > 0
        ');
        $stmt->execute([$spaceId]);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'org_id' => (int) ($row['org_id'] ?? 0),
                'series_id' => (int) ($row['series_id'] ?? 0),
            ];
        }

        return $rows;
    }
}
