<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use PDO;

final class PdoPortalMembershipRepository
{
    private const ADMIN_ROLE_KEYS = ['org_owner', 'org_admin'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listAdministrableOrganizationsForPerson(int $personId): array
    {
        $placeholders = implode(',', array_fill(0, count(self::ADMIN_ROLE_KEYS), '?'));
        // GROUP BY org_id — flere roller/medlemskap skal ikke gi duplikater i UI.
        $stmt = $this->pdo->prepare("
            SELECT o.org_id, o.name, o.short_name, o.status
            FROM org_memberships m
            INNER JOIN org_organizations o ON o.org_id = m.org_id
            INNER JOIN org_membership_roles omr ON omr.membership_id = m.membership_id
            INNER JOIN auth_roles r ON r.role_id = omr.role_id
            WHERE m.person_id = ?
              AND m.deleted_at IS NULL
              AND m.status = 'active'
              AND omr.deleted_at IS NULL
              AND omr.status = 'active'
              AND r.role_key IN ($placeholders)
              AND o.deleted_at IS NULL
            GROUP BY o.org_id, o.name, o.short_name, o.status
            ORDER BY o.name
        ");
        $stmt->execute(array_merge([$personId], self::ADMIN_ROLE_KEYS));

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<string> */
    public function roleKeysForPersonInOrganization(int $personId, int $orgId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT r.role_key
            FROM org_memberships m
            INNER JOIN org_membership_roles omr ON omr.membership_id = m.membership_id
            INNER JOIN auth_roles r ON r.role_id = omr.role_id
            WHERE m.person_id = ? AND m.org_id = ?
              AND m.deleted_at IS NULL AND m.status = \'active\'
              AND omr.deleted_at IS NULL AND omr.status = \'active\'
        ');
        $stmt->execute([$personId, $orgId]);
        $keys = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $keys[] = (string) ($row['role_key'] ?? '');
        }

        return $keys;
    }

    public function personIsOrgAdmin(int $personId, int $orgId): bool
    {
        $keys = $this->roleKeysForPersonInOrganization($personId, $orgId);

        return array_intersect($keys, self::ADMIN_ROLE_KEYS) !== [];
    }
}
