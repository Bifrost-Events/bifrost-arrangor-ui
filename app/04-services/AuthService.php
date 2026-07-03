<?php

declare(strict_types=1);

namespace App\Service;

final class AuthService
{
    /**
     * @param array<string, mixed> $user
     */
    public static function canAccessOrganizer(array $user): bool
    {
        if (($user['can_access_organizer'] ?? false) === true) {
            return true;
        }

        $memberships = $user['organization_memberships'] ?? [];
        if (!is_array($memberships) || $memberships === []) {
            return false;
        }

        foreach ($user['organization_season_access'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (in_array((string) ($row['status'] ?? ''), ['approved', 'pending'], true)) {
                return true;
            }
        }

        return false;
    }
}
