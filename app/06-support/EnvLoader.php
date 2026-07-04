<?php

declare(strict_types=1);

namespace App\Support;

final class EnvLoader
{
    /**
     * Laster miljøvariabler.
     *
     * - BIFROST_DOTENV satt (CLI/Apache): kun den filen (f.eks. .env.local-quality for Playwright)
     * - Ellers: .env, deretter .env.local som overlay (daglig utvikling)
     */
    public static function load(string $basePath): void
    {
        $basePath = rtrim($basePath, '/\\');
        $requested = trim((string) (getenv('BIFROST_DOTENV') ?: ($_SERVER['BIFROST_DOTENV'] ?? '')));

        if ($requested !== '') {
            self::loadFile($basePath, basename($requested));

            return;
        }

        self::loadFile($basePath, '.env');
        self::loadFile($basePath, '.env.local');
    }

    private static function loadFile(string $basePath, string $fileName): void
    {
        if (!self::isAllowedEnvFileName($fileName)) {
            return;
        }

        $envFile = $basePath . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private static function isAllowedEnvFileName(string $fileName): bool
    {
        return (bool) preg_match('/^\.env(\.[a-z0-9-]+)?$/', $fileName);
    }
}
