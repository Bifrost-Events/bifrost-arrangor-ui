<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use App\Support\PortalV3Session;

/**
 * Etablerer ekte BIFROSTADMIN-sesjon på admin-core via HTTP.
 * Lokal AdminSessionBridge deler ikke session-filer med annen webroot i sky.
 */
final class AdminApiSession
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public static function establish(string $email, string $password): array
    {
        $baseUrl = rtrim((string) Config::get('events.api_base_url', ''), '/');
        if ($baseUrl === '') {
            return ['ok' => false, 'error' => 'Events API base URL mangler (BACKEND_URL / EVENTS_URL).'];
        }

        if (Config::get('events.use_internal_dispatch', false)) {
            // Intern dispatch bruker portal-sesjon direkte — ingen remote cookie nødvendig.
            PortalV3Session::clearAdminApiCookie();

            return ['ok' => true];
        }

        try {
            $transport = self::postLogin($baseUrl . '/api/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Kunne ikke kontakte admin API: ' . $e->getMessage()];
        }

        $status = $transport['status'];
        if ($status < 200 || $status >= 300) {
            $msg = 'Admin-innlogging feilet.';
            try {
                $decoded = json_decode((string) $transport['body'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $err = $decoded['error'] ?? null;
                    if (is_string($err) && $err !== '') {
                        $msg = $err;
                    } elseif (is_array($err) && isset($err['message'])) {
                        $msg = (string) $err['message'];
                    }
                }
            } catch (\JsonException) {
            }

            return ['ok' => false, 'error' => $msg];
        }

        $cookie = self::extractAdminCookie($transport['headers']);
        if ($cookie === null) {
            return ['ok' => false, 'error' => 'Admin API returnerte ingen BIFROSTADMIN-sesjon.'];
        }

        PortalV3Session::setAdminApiCookie($cookie);

        return ['ok' => true];
    }

    public static function clear(): void
    {
        PortalV3Session::clearAdminApiCookie();
    }

    /**
     * @param array<string, mixed> $body
     * @return array{status: int, body: string, headers: list<string>}
     */
    private static function postLogin(string $url, array $body): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('curl er ikke tilgjengelig');
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init feilet');
        }

        $headers = [];
        $verifySsl = ($_ENV['EVENTS_SSL_VERIFY'] ?? 'true') !== 'false';
        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$headers) {
                $trimmed = trim($headerLine);
                if ($trimmed !== '') {
                    $headers[] = $trimmed;
                }

                return strlen($headerLine);
            },
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($err !== '' ? $err : 'curl_exec feilet');
        }
        curl_close($ch);

        return [
            'status' => $status,
            'body' => (string) $responseBody,
            'headers' => $headers,
        ];
    }

    /** @param list<string> $headers */
    private static function extractAdminCookie(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (!str_starts_with(strtolower($header), 'set-cookie:')) {
                continue;
            }
            if (!preg_match('/^Set-Cookie:\s*(BIFROSTADMIN)=([^;]+)/i', $header, $m)) {
                continue;
            }

            return $m[1] . '=' . rawurldecode(trim($m[2]));
        }

        return null;
    }
}
