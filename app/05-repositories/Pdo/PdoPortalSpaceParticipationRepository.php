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
     * Aktiv seriearrangør for serien eller en forfedre-serie (sesong → runde).
     */
    public function orgIsSeriesOrganizer(int $orgId, int $seriesId): bool
    {
        if ($orgId <= 0 || $seriesId <= 0) {
            return false;
        }

        $currentId = $seriesId;
        $guard = 0;
        while ($currentId > 0 && $guard < 32) {
            ++$guard;
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM event_series_organizations
                WHERE series_id = ?
                  AND org_id = ?
                  AND relationship_type = 'organizer'
                  AND status = 'active'
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$currentId, $orgId]);
            if ((bool) $stmt->fetchColumn()) {
                return true;
            }

            $parentStmt = $this->pdo->prepare('
                SELECT parent_series_id
                FROM event_series
                WHERE series_id = ? AND deleted_at IS NULL
                LIMIT 1
            ');
            $parentStmt->execute([$currentId]);
            $parent = $parentStmt->fetchColumn();
            $currentId = $parent !== false && $parent !== null ? (int) $parent : 0;
        }

        return false;
    }

    public function orgIsSeriesOrganizerInSpace(int $orgId, int $spaceId): bool
    {
        if ($orgId <= 0 || $spaceId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM event_series_organizations so
            INNER JOIN event_series s ON s.series_id = so.series_id AND s.deleted_at IS NULL
            WHERE s.space_id = ?
              AND so.org_id = ?
              AND so.relationship_type = 'organizer'
              AND so.status = 'active'
              AND so.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$spaceId, $orgId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Organisasjoner som er aktive seriearrangører i cupen (uten krav om eksisterende stevne).
     *
     * @return list<array{org_id: int, name: string}>
     */
    public function listSeriesOrganizerOrganizationsInSpace(int $spaceId): array
    {
        if ($spaceId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT o.org_id, o.name
            FROM event_series_organizations so
            INNER JOIN event_series s ON s.series_id = so.series_id AND s.deleted_at IS NULL
            INNER JOIN org_organizations o ON o.org_id = so.org_id AND o.deleted_at IS NULL
            WHERE s.space_id = ?
              AND so.relationship_type = 'organizer'
              AND so.status = 'active'
              AND so.deleted_at IS NULL
            ORDER BY o.name
        ");
        $stmt->execute([$spaceId]);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'org_id' => (int) ($row['org_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Sesongrøtter der org er aktiv seriearrangør i cupen.
     *
     * @return list<array{series_id: int, name: string, season_label: string, structure_type: string}>
     */
    public function listOrganizerRootSeriesInSpace(int $orgId, int $spaceId): array
    {
        if ($orgId <= 0 || $spaceId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT s.series_id, s.parent_series_id, s.name, s.season_label, s.structure_type
            FROM event_series_organizations so
            INNER JOIN event_series s ON s.series_id = so.series_id AND s.deleted_at IS NULL
            WHERE s.space_id = ?
              AND so.org_id = ?
              AND so.relationship_type = 'organizer'
              AND so.status = 'active'
              AND so.deleted_at IS NULL
        ");
        $stmt->execute([$spaceId, $orgId]);
        $linked = $stmt->fetchAll() ?: [];
        if ($linked === []) {
            return [];
        }

        $parentStmt = $this->pdo->prepare('
            SELECT series_id, parent_series_id, name, season_label, structure_type
            FROM event_series
            WHERE series_id = ? AND deleted_at IS NULL
            LIMIT 1
        ');

        $byRoot = [];
        foreach ($linked as $row) {
            $current = $row;
            $guard = 0;
            while ($guard < 32) {
                ++$guard;
                $parentId = isset($current['parent_series_id']) && $current['parent_series_id'] !== null
                    ? (int) $current['parent_series_id']
                    : 0;
                if ($parentId <= 0) {
                    break;
                }
                $parentStmt->execute([$parentId]);
                $parent = $parentStmt->fetch();
                if (!is_array($parent)) {
                    break;
                }
                $current = $parent;
            }
            $rootId = (int) ($current['series_id'] ?? 0);
            if ($rootId > 0) {
                $byRoot[$rootId] = [
                    'series_id' => $rootId,
                    'name' => (string) ($current['name'] ?? ''),
                    'season_label' => (string) ($current['season_label'] ?? ''),
                    'structure_type' => (string) ($current['structure_type'] ?? ''),
                ];
            }
        }

        $list = array_values($byRoot);
        usort($list, static fn (array $a, array $b): int => strcmp(
            (string) ($a['name'] ?? $a['season_label'] ?? ''),
            (string) ($b['name'] ?? $b['season_label'] ?? ''),
        ));

        return $list;
    }

    /**
     * Direkte barn-serier (runder) under en sesongrot.
     *
     * @return list<array{series_id: int, name: string, season_label: string, structure_type: string, parent_series_id: int}>
     */
    public function listChildSeriesByParentId(int $parentSeriesId): array
    {
        if ($parentSeriesId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare('
            SELECT series_id, parent_series_id, name, season_label, structure_type, starts_at, ends_at
            FROM event_series
            WHERE parent_series_id = ? AND deleted_at IS NULL
            ORDER BY sort_order, series_id
        ');
        $stmt->execute([$parentSeriesId]);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'series_id' => (int) ($row['series_id'] ?? 0),
                'parent_series_id' => (int) ($row['parent_series_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'season_label' => (string) ($row['season_label'] ?? ''),
                'structure_type' => (string) ($row['structure_type'] ?? ''),
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
            ];
        }

        return $rows;
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
