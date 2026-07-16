<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use App\Support\EnvLoader;
use App\Support\PortalV3Auth;
use App\Support\Router;
use App\Support\Session;

/**
 * Kaller organizer API in-process (lokal dev) — unngår HTTP-timeout og proc_open under Apache/Windows.
 */
final class EventsApiInternalDispatch
{
    private static bool $adminBootstrapped = false;

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: string|false, headers: list<string>}
     */
    public static function request(string $method, string $path, ?array $body = null): array
    {
        $adminCore = (string) Config::get('events.admin_core_path', '');
        if ($adminCore === '' || !is_dir($adminCore)) {
            throw new \RuntimeException('Mangler admin-core: ' . $adminCore);
        }

        $portalUser = PortalV3Auth::user();
        if (!is_array($portalUser) || (int) ($portalUser['person_id'] ?? 0) <= 0) {
            throw new \RuntimeException('Mangler innlogget portal-bruker for intern API-kall');
        }

        $savedGet = $_GET;
        $savedMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $hadBridgeAuth = array_key_exists(Session::ADMIN_AUTH_BRIDGE_KEY, $_SESSION);

        try {
            self::ensureAdminCoreBootstrapped($adminCore);
            self::injectOrganizerAuth($portalUser);

            [$requestUri, $query] = self::parsePath($path);
            $_GET = $query;
            $_SERVER['REQUEST_METHOD'] = strtoupper($method);

            if ($body !== null) {
                \App\Support\InternalJsonBody::set($body);
            }

            $response = self::organizerRouter($adminCore)->dispatch(strtoupper($method), $requestUri);

            return [
                'status' => (int) ($response['status'] ?? 500),
                'body' => (string) ($response['body'] ?? ''),
                'headers' => [],
            ];
        } finally {
            if (class_exists(\App\Support\InternalJsonBody::class, false)) {
                \App\Support\InternalJsonBody::clear();
            }
            $_GET = $savedGet;
            $_SERVER['REQUEST_METHOD'] = $savedMethod;
            if (!$hadBridgeAuth) {
                unset($_SESSION[Session::ADMIN_AUTH_BRIDGE_KEY]);
            }
        }
    }

    private static function ensureAdminCoreBootstrapped(string $adminCore): void
    {
        if (self::$adminBootstrapped) {
            return;
        }

        EnvLoader::load($adminCore);
        self::requireAdminCoreSupportFile($adminCore, 'ModuleRegistry.php');
        self::requireAdminCoreSupportFile($adminCore, 'ModuleRouteLoader.php');
        self::requireAdminCoreSupportFile($adminCore, 'ModuleAutoloader.php');
        \App\Support\ModuleAutoloader::register();

        // Prefetch InternalJsonBody so PATCH/POST bodies work in-process.
        if (!class_exists(\App\Support\InternalJsonBody::class, false)) {
            class_exists(\App\Support\InternalJsonBody::class, true);
        }

        self::$adminBootstrapped = true;
    }

    private static function organizerRouter(string $adminCore): Router
    {
        unset($adminCore);

        $router = new Router();
        \App\Support\ModuleRouteLoader::registerApi($router);

        return $router;
    }

    private static function requireAdminCoreSupportFile(string $adminCore, string $file): void
    {
        $path = $adminCore . '/app/06-support/' . $file;
        if (!is_file($path)) {
            throw new \RuntimeException('Mangler admin-core fil: ' . $path);
        }

        require_once $path;
    }

    /** @param array<string, mixed> $portalUser */
    private static function injectOrganizerAuth(array $portalUser): void
    {
        Session::startRequired();
        $_SESSION[Session::ADMIN_AUTH_BRIDGE_KEY] = [
            'user_id' => (int) ($portalUser['user_id'] ?? 0),
            'person_id' => (int) ($portalUser['person_id'] ?? 0),
            'email' => (string) ($portalUser['email'] ?? ''),
            'name' => (string) ($portalUser['name'] ?? $portalUser['display_name'] ?? ''),
            'username' => (string) ($portalUser['username'] ?? ''),
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function parsePath(string $path): array
    {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $qPos = strpos($path, '?');
        $requestUri = $qPos === false ? $path : substr($path, 0, $qPos);
        $query = [];

        if ($qPos !== false) {
            parse_str(substr($path, $qPos + 1), $parsed);
            if (is_array($parsed)) {
                foreach ($parsed as $key => $value) {
                    if (is_scalar($value)) {
                        $query[(string) $key] = (string) $value;
                    }
                }
            }
        }

        return [$requestUri, $query];
    }
}
