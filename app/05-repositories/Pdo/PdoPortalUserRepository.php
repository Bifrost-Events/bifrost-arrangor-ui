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

    /**
     * Opprett person + auth_user atomisk. Returnerer user_id.
     *
     * @param array{first_name: string, last_name: string, email: string, phone?: string|null, password: string} $data
     */
    public function createWithPerson(array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            $first = trim((string) ($data['first_name'] ?? ''));
            $last = trim((string) ($data['last_name'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));
            $phone = isset($data['phone']) ? trim((string) $data['phone']) : '';
            $password = (string) ($data['password'] ?? '');

            $insP = $this->pdo->prepare('
                INSERT INTO person_people (
                    first_name, last_name, display_name, email, phone, status
                ) VALUES (?, ?, ?, ?, ?, \'active\')
            ');
            $insP->execute([
                $first,
                $last,
                trim($first . ' ' . $last),
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
            ]);
            $personId = (int) $this->pdo->lastInsertId();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insU = $this->pdo->prepare('
                INSERT INTO auth_users (person_id, email, password_hash, status)
                VALUES (?, ?, ?, \'active\')
            ');
            $insU->execute([$personId, $email, $hash]);
            $userId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare('UPDATE person_people SET created_by_user_id = ? WHERE person_id = ?')
                ->execute([$userId, $personId]);
            $this->pdo->prepare('UPDATE auth_users SET created_by_user_id = ? WHERE user_id = ?')
                ->execute([$userId, $userId]);

            $this->pdo->commit();

            return $userId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
