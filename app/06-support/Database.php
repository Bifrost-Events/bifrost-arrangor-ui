<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = (string) Config::get('database.dsn', '');
        if ($dsn === '') {
            throw new \RuntimeException('Database DSN mangler — sett DB_DSN eller bruk standard local default.');
        }

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('database.user', ''),
                (string) Config::get('database.pass', ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Database-tilkobling feilet: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }
}
