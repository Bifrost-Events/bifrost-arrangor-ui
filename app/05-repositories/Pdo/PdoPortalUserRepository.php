<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use PDO;

final class PdoPortalUserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.user_id, u.person_id, u.email, u.username, u.password_hash, u.status,
                   p.display_name AS person_name, p.first_name, p.last_name
            FROM auth_users u
            INNER JOIN person_people p ON p.person_id = u.person_id
            WHERE LOWER(u.email) = LOWER(?) AND u.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findFirstActive(): ?array
    {
        $stmt = $this->pdo->query('
            SELECT u.user_id, u.person_id, u.email, u.username, u.password_hash, u.status,
                   p.display_name AS person_name, p.first_name, p.last_name
            FROM auth_users u
            INNER JOIN person_people p ON p.person_id = u.person_id
            WHERE u.deleted_at IS NULL AND u.status = \'active\'
            ORDER BY u.user_id
            LIMIT 1
        ');
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
