<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use PDO;

/** Enkel org-oppslag for cupadmin-skjema (ikke medlemskapsliste). */
final class PdoPortalOrganizationLookupRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{org_id: int, name: string}> */
    public function listActive(int $limit = 500): array
    {
        $limit = max(1, min(1000, $limit));
        $stmt = $this->pdo->query('
            SELECT org_id, name
            FROM org_organizations
            WHERE deleted_at IS NULL
              AND status = \'active\'
            ORDER BY name
            LIMIT ' . $limit
        );
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'org_id' => (int) ($row['org_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $rows;
    }

    public function exists(int $orgId): bool
    {
        if ($orgId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM org_organizations
            WHERE org_id = ? AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$orgId]);

        return (bool) $stmt->fetchColumn();
    }
}
