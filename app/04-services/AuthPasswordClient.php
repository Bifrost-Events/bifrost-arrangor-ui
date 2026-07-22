<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;

/** HTTP-klient for glemt/tilbakestill passord mot admin-core (uten sesjon). */
final class AuthPasswordClient
{
    /**
     * @return array{ok: bool, message?: string, error?: string, status?: int}
     */
    public function forgotPassword(string $email, string $resetUrl): array
    {
        return $this->postJson('/api/auth/forgot-password', [
            'email' => $email,
            'reset_url' => $resetUrl,
        ]);
    }

    /**
     * @return array{ok: bool, message?: string, error?: string, status?: int}
     */
    public function resetPassword(string $token, string $password, string $passwordConfirm): array
    {
        return $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'password' => $password,
            'password_confirm' => $passwordConfirm,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, message?: string, error?: string, status?: int}
     */
    private function postJson(string $path, array $body): array
    {
        $baseUrl = rtrim((string) Config::get('events.api_base_url', ''), '/');
        if ($baseUrl === '') {
            return ['ok' => false, 'error' => 'BACKEND_URL mangler.', 'status' => 500];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl er ikke tilgjengelig.', 'status' => 500];
        }

        $url = $baseUrl . $path;
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init feilet.', 'status' => 500];
        }

        $verifySsl = ($_ENV['EVENTS_SSL_VERIFY'] ?? 'true') !== 'false';
        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($responseBody === false) {
            $err = curl_error($ch);

            return ['ok' => false, 'error' => $err !== '' ? $err : 'Nettverksfeil.', 'status' => 502];
        }

        $decoded = json_decode((string) $responseBody, true);
        $data = is_array($decoded) ? $decoded : [];

        if ($status >= 200 && $status < 300) {
            $message = '';
            if (isset($data['data']['message']) && is_string($data['data']['message'])) {
                $message = $data['data']['message'];
            }

            return ['ok' => true, 'message' => $message, 'status' => $status];
        }

        $error = 'Forespørselen feilet.';
        $errNode = $data['error'] ?? null;
        if (is_string($errNode) && $errNode !== '') {
            $error = $errNode;
        } elseif (is_array($errNode) && isset($errNode['message'])) {
            $error = (string) $errNode['message'];
        }

        return ['ok' => false, 'error' => $error, 'status' => $status];
    }
}
