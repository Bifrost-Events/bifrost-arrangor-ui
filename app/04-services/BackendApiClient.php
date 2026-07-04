<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use App\Support\Session;

final class BackendApiClient
{
    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function health(): array
    {
        return $this->get('/api/health');
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function publicCalendar(string $host): array
    {
        return $this->get('/api/public/calendar?host=' . rawurlencode($host));
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function resolveTenant(string $host): array
    {
        return $this->get('/api/tenant/resolve?host=' . rawurlencode($host));
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function participantLogin(string $email, string $password): array
    {
        $result = $this->request('POST', '/api/auth/participant/login', [
            'email' => $email,
            'password' => $password,
        ]);
        if ($result['ok'] ?? false) {
            $this->storeBackendSessionFromLoginResponse($result['data'] ?? []);
            $this->captureSessionCookieFromLastResponse();
        }

        return $result;
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function logout(): array
    {
        $result = $this->post('/api/auth/logout', []);
        Session::clearBackendCookie();

        return $result;
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function me(): array
    {
        return $this->get('/api/auth/me');
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerContext(int $organizationId = 0, int $seasonId = 0, int $tenantId = 0, string $portalHost = ''): array
    {
        $query = [];
        if ($organizationId > 0) {
            $query[] = 'organization_id=' . $organizationId;
        }
        if ($seasonId > 0) {
            $query[] = 'season_id=' . $seasonId;
        }
        if ($tenantId > 0) {
            $query[] = 'tenant_id=' . $tenantId;
        }
        if ($portalHost !== '') {
            $query[] = 'portal_host=' . rawurlencode($portalHost);
        }
        $path = '/api/organizer/context';
        if ($query !== []) {
            $path .= '?' . implode('&', $query);
        }

        return $this->get($path);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerOrganizations(): array
    {
        return $this->get('/api/organizer/organizations');
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function registerOrganizerOrganization(array $body): array
    {
        return $this->post('/api/organizer/organizations', $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function updateOrganizerProfile(array $body): array
    {
        return $this->put('/api/organizer/profile', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerCompetitions(int $organizationId, int $seasonId = 0): array
    {
        $query = ['organization_id=' . $organizationId];
        if ($seasonId > 0) {
            $query[] = 'season_id=' . $seasonId;
        }

        return $this->get('/api/organizer/competitions?' . implode('&', $query));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function createOrganizerCompetition(int $organizationId, array $body): array
    {
        $body['organization_id'] = $organizationId;

        return $this->post('/api/organizer/competitions', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerCompetition(int $organizationId, int $competitionId): array
    {
        return $this->get(
            '/api/organizer/competitions/' . $competitionId . '?organization_id=' . $organizationId
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function updateOrganizerCompetition(int $organizationId, int $competitionId, array $body): array
    {
        $body['organization_id'] = $organizationId;

        return $this->put('/api/organizer/competitions/' . $competitionId, $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function generateOrganizerCompetitionSlots(int $organizationId, int $competitionId, array $body = []): array
    {
        $body['organization_id'] = $organizationId;

        return $this->post('/api/organizer/competitions/' . $competitionId . '/generate-slots', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerCompetitionRoster(int $organizationId, int $competitionId): array
    {
        return $this->get(
            '/api/organizer/competitions/' . $competitionId . '/roster?organization_id=' . $organizationId
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function assignOrganizerCompetitionParticipant(int $organizationId, int $competitionId, array $body): array
    {
        $body['organization_id'] = $organizationId;

        return $this->post('/api/organizer/competitions/' . $competitionId . '/assign', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function removeOrganizerCompetitionRegistration(
        int $organizationId,
        int $competitionId,
        int $slot,
        int $figure,
    ): array {
        return $this->delete(
            '/api/organizer/competitions/' . $competitionId
            . '/slots/' . $slot . '/figures/' . $figure
            . '?organization_id=' . $organizationId
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function reserveOrganizerCompetitionSlot(int $organizationId, int $competitionId, array $body): array
    {
        $body['organization_id'] = $organizationId;

        return $this->post('/api/organizer/competitions/' . $competitionId . '/reserve-slot', $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function reserveOrganizerCompetitionFigure(int $organizationId, int $competitionId, array $body): array
    {
        $body['organization_id'] = $organizationId;

        return $this->post('/api/organizer/competitions/' . $competitionId . '/reserve-figure', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerParticipants(int $organizationId): array
    {
        return $this->get('/api/organizer/participants?organization_id=' . $organizationId);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function createOrganizerParticipant(int $organizationId, array $body): array
    {
        $body['organization_id'] = $organizationId;

        return $this->post('/api/organizer/participants', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerStevneAdmin(int $organizationId, int $competitionId): array
    {
        return $this->get(
            '/api/organizer/competitions/' . $competitionId . '/stevneadmin?organization_id=' . $organizationId
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function saveOrganizerCompetitionResults(
        int $organizationId,
        int $competitionId,
        int $slot,
        array $body,
    ): array {
        $body['organization_id'] = $organizationId;

        return $this->post(
            '/api/organizer/competitions/' . $competitionId . '/slots/' . $slot . '/results',
            $body
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function setOrganizerCompetitionSlotLock(
        int $organizationId,
        int $competitionId,
        int $slot,
        array $body,
    ): array {
        $body['organization_id'] = $organizationId;

        return $this->post(
            '/api/organizer/competitions/' . $competitionId . '/slots/' . $slot . '/lock',
            $body
        );
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function approveOrganizerCompetition(int $organizationId, int $competitionId, bool $approved = true): array
    {
        return $this->post('/api/organizer/competitions/' . $competitionId . '/approval', [
            'organization_id' => $organizationId,
            'approved' => $approved,
        ]);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerParticipantSearch(int $organizationId, int $competitionId, string $query): array
    {
        return $this->get(
            '/api/organizer/competitions/' . $competitionId
            . '/participant-search?organization_id=' . $organizationId
            . '&q=' . rawurlencode($query)
        );
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function organizerMembers(int $organizationId): array
    {
        return $this->get('/api/organizer/organizations/' . $organizationId . '/members');
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function inviteOrganizerMember(int $organizationId, array $body): array
    {
        return $this->post('/api/organizer/organizations/' . $organizationId . '/invitations', $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    public function acceptOrganizerInvitation(array $body): array
    {
        return $this->post('/api/organizer/invitations/accept', $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    private function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, $body);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    private function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /** @var list<string>|null */
    private static ?array $lastResponseHeaders = null;

    /**
     * @param array<string, mixed>|null $body
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            $baseUrl = rtrim((string) Config::get('backend.api_base_url', ''), '/');
            if ($baseUrl === '') {
                return [
                    'ok' => false,
                    'status' => 0,
                    'data' => null,
                    'error' => 'BACKEND_URL is not configured',
                ];
            }

            $url = $baseUrl . $path;
            $cookie = Session::getBackendCookie();

            if (function_exists('curl_init')) {
                return $this->decodeResponse($this->requestViaCurl($url, $method, $body, $cookie));
            }

            return $this->decodeResponse($this->requestViaStream($url, $method, $body, $cookie));
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => 'Backend request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: string|false, headers: list<string>}
     */
    private function requestViaCurl(string $url, string $method, ?array $body, string $cookie): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        $headerLines = ['Accept: application/json'];
        if ($cookie !== '') {
            $headerLines[] = 'Cookie: ' . $cookie;
        }

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $headerLines[] = 'Content-Type: application/json';
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders) {
                $trimmed = trim($headerLine);
                if ($trimmed !== '') {
                    $responseHeaders[] = $trimmed;
                }

                return strlen($headerLine);
            },
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($responseBody === false) {
            $err = curl_error($ch);
            throw new \RuntimeException($err !== '' ? $err : 'curl_exec failed');
        }

        self::$lastResponseHeaders = $responseHeaders;

        return [
            'status' => $status,
            'body' => $responseBody,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: string|false, headers: list<string>}
     */
    private function requestViaStream(string $url, string $method, ?array $body, string $cookie): array
    {
        $headers = "Accept: application/json\r\n";
        if ($cookie !== '') {
            $headers .= 'Cookie: ' . $cookie . "\r\n";
        }

        $options = [
            'method' => $method,
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => $headers,
        ];

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $options['header'] .= "Content-Type: application/json\r\n";
            $options['content'] = $payload;
        }

        $context = stream_context_create([
            'http' => $options,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        self::$lastResponseHeaders = null;
        $responseBody = @file_get_contents($url, false, $context);
        /** @var list<string> $rawHeaders */
        if (function_exists('http_get_last_response_headers')) {
            $headers = http_get_last_response_headers();
            $rawHeaders = is_array($headers) ? $headers : [];
        } else {
            /** @var list<string> $rawHeaders */
            $rawHeaders = include __DIR__ . '/legacy_stream_headers.inc.php';
        }
        self::$lastResponseHeaders = $rawHeaders;

        $status = 0;
        if (isset(self::$lastResponseHeaders[0]) && preg_match('#\s(\d{3})\s#', self::$lastResponseHeaders[0], $m)) {
            $status = (int) $m[1];
        }

        if ($responseBody === false) {
            throw new \RuntimeException('Could not reach backend at ' . $url);
        }

        return [
            'status' => $status,
            'body' => $responseBody,
            'headers' => self::$lastResponseHeaders,
        ];
    }

    /**
     * @param array{status: int, body: string|false, headers: list<string>} $transport
     * @return array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>}
     */
    private function decodeResponse(array $transport): array
    {
        $status = $transport['status'];
        $responseBody = $transport['body'];
        if (!is_string($responseBody) || $responseBody === '') {
            return [
                'ok' => false,
                'status' => $status,
                'data' => null,
                'error' => 'Empty response from backend',
            ];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'ok' => false,
                'status' => $status,
                'data' => null,
                'error' => 'Invalid JSON from backend',
            ];
        }

        $ok = $status >= 200 && $status < 300;
        $result = [
            'ok' => $ok,
            'status' => $status,
            'data' => $decoded,
            'error' => $ok ? null : (string) ($decoded['error'] ?? 'HTTP ' . $status),
        ];

        if (!$ok && is_array($decoded['errors'] ?? null)) {
            /** @var array<string, string> $fieldErrors */
            $fieldErrors = $decoded['errors'];
            $result['errors'] = $fieldErrors;
            $first = reset($fieldErrors);
            if (is_string($first) && $first !== '') {
                $result['error'] = $first;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private function storeBackendSessionFromLoginResponse(array $data): void
    {
        $session = $data['session'] ?? null;
        if (!is_array($session)) {
            return;
        }

        $name = trim((string) ($session['name'] ?? ''));
        $id = trim((string) ($session['id'] ?? ''));
        if ($name !== '' && $id !== '') {
            Session::setBackendCookie($name . '=' . $id);
        }
    }

    private function captureSessionCookieFromLastResponse(): void
    {
        $headers = self::$lastResponseHeaders ?? [];
        foreach ($headers as $header) {
            if (!str_starts_with(strtolower($header), 'set-cookie:')) {
                continue;
            }
            if (!preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $header, $m)) {
                continue;
            }
            $name = trim($m[1]);
            if ($name === 'BIFROSTSESSID') {
                Session::setBackendCookie($name . '=' . trim($m[2]));
                break;
            }
        }
    }
}
