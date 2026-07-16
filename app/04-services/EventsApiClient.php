<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;

final class EventsApiClient
{
    /** @var list<string>|null */
    private static ?array $lastResponseHeaders = null;

    private static ?string $lastListError = null;

    public static function lastListError(): ?string
    {
        return self::$lastListError;
    }

    public static function rememberLastListError(?string $message): void
    {
        self::$lastListError = $message;
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function listOrganizations(): array
    {
        return $this->unwrapData($this->get('/api/organizer/organizations'));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function listEventSpaces(int $orgId): array
    {
        return $this->unwrapData($this->get('/api/organizer/event-spaces', ['org_id' => $orgId]));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getEventSpace(int $orgId, int $spaceId): array
    {
        return $this->unwrapData($this->get('/api/organizer/event-spaces/' . $spaceId, ['org_id' => $orgId]));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function listSeriesHierarchy(int $orgId, int $spaceId): array
    {
        return $this->unwrapData($this->get('/api/organizer/event-spaces/' . $spaceId . '/series', ['org_id' => $orgId]));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getSeries(int $orgId, int $seriesId): array
    {
        return $this->unwrapData($this->get('/api/organizer/series/' . $seriesId, ['org_id' => $orgId]));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function listEventsForSeries(int $orgId, int $seriesId): array
    {
        return $this->unwrapData($this->get('/api/organizer/series/' . $seriesId . '/events', ['org_id' => $orgId]));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getEvent(int $orgId, int $eventId): array
    {
        return $this->unwrapData($this->get('/api/organizer/events/' . $eventId, ['org_id' => $orgId]));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function listEventsForSpace(int $orgId, int $spaceId): array
    {
        return $this->unwrapData($this->get('/api/organizer/event-spaces/' . $spaceId . '/events', ['org_id' => $orgId]));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function createEventSpace(int $orgId, array $body): array
    {
        return $this->unwrapData($this->post('/api/organizer/event-spaces?org_id=' . $orgId, $body));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateEventSpace(int $orgId, int $spaceId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/event-spaces/' . $spaceId . '?org_id=' . $orgId, $body));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function archiveEventSpace(int $orgId, int $spaceId): array
    {
        return $this->unwrapData($this->post('/api/organizer/event-spaces/' . $spaceId . '/archive?org_id=' . $orgId, []));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function createSeries(int $orgId, array $body): array
    {
        return $this->unwrapData($this->post('/api/organizer/series?org_id=' . $orgId, $body));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateSeries(int $orgId, int $seriesId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/series/' . $seriesId . '?org_id=' . $orgId, $body));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function archiveSeries(int $orgId, int $seriesId): array
    {
        return $this->unwrapData($this->post('/api/organizer/series/' . $seriesId . '/archive?org_id=' . $orgId, []));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getCupStandings(int $orgId, int $seriesId): array
    {
        return $this->unwrapData($this->get('/api/organizer/series/' . $seriesId . '/cup-standings', ['org_id' => $orgId]));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateCupStandings(int $orgId, int $seriesId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/series/' . $seriesId . '/cup-standings?org_id=' . $orgId, $body));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getSeasonStructureScoring(int $orgId, int $seriesId): array
    {
        return $this->unwrapData($this->get('/api/organizer/series/' . $seriesId . '/structure-scoring', ['org_id' => $orgId]));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateSeasonStructure(int $orgId, int $seriesId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/series/' . $seriesId . '/structure?org_id=' . $orgId, $body));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateSeasonScoringConfig(int $orgId, int $seriesId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/series/' . $seriesId . '/scoring-config?org_id=' . $orgId, $body));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function createEvent(int $orgId, array $body): array
    {
        return $this->unwrapData($this->post('/api/organizer/events?org_id=' . $orgId, $body));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateEvent(int $orgId, int $eventId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/events/' . $eventId . '?org_id=' . $orgId, $body));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function archiveEvent(int $orgId, int $eventId): array
    {
        return $this->unwrapData($this->post('/api/organizer/events/' . $eventId . '/archive?org_id=' . $orgId, []));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getJaktfeltSlotGrid(int $orgId, int $eventId): array
    {
        return $this->unwrapData($this->get('/api/organizer/jaktfelt/events/' . $eventId . '/slot-grid', ['org_id' => $orgId]));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function putJaktfeltSlotGrid(int $orgId, int $eventId, array $body): array
    {
        return $this->unwrapData($this->put('/api/organizer/jaktfelt/events/' . $eventId . '/slot-grid?org_id=' . $orgId, $body));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function moveJaktfeltRegistration(int $orgId, int $registrationId, array $body): array
    {
        return $this->unwrapData($this->post(
            '/api/organizer/jaktfelt/registrations/' . $registrationId . '/move?org_id=' . $orgId,
            $body
        ));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function createJaktfeltOrganizerRegistration(int $orgId, int $eventId, array $body): array
    {
        return $this->unwrapData($this->post(
            '/api/organizer/jaktfelt/events/' . $eventId . '/registrations?org_id=' . $orgId,
            $body
        ));
    }

    /**
     * @param array<string, mixed> $query
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function listEventRegistrations(int $orgId, int $eventId, array $query = []): array
    {
        $query['org_id'] = $orgId;

        return $this->unwrapData($this->get('/api/organizer/events/' . $eventId . '/registrations', $query));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>} */
    public function getRegistration(int $orgId, int $registrationId): array
    {
        return $this->unwrapData($this->get('/api/organizer/registrations/' . $registrationId, ['org_id' => $orgId]));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>, candidates?: list<array<string, mixed>>}
     */
    public function createEventRegistration(int $orgId, int $eventId, array $body): array
    {
        $raw = $this->request('POST', '/api/organizer/events/' . $eventId . '/registrations?org_id=' . $orgId, $body);
        $unwrapped = $this->unwrapData($raw);
        if (!($unwrapped['ok'] ?? false)) {
            $payload = $raw['data'] ?? null;
            $candidates = null;
            if (is_array($payload)) {
                $candidates = $payload['error']['candidates'] ?? null;
            }
            if (is_array($candidates)) {
                $unwrapped['candidates'] = $candidates;
            }
        }

        return $unwrapped;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    public function updateRegistration(int $orgId, int $registrationId, array $body): array
    {
        return $this->unwrapData($this->patch('/api/organizer/registrations/' . $registrationId . '?org_id=' . $orgId, $body));
    }

    /** @return array{ok: bool, status: int, data: mixed, error: string|null, filename?: string} */
    public function exportEventRegistrations(int $orgId, int $eventId): array
    {
        return $this->unwrapData(
            $this->get('/api/organizer/events/' . $eventId . '/registrations/export', [
                'org_id' => $orgId,
                'format' => 'json',
            ])
        );
    }

    /** @param array<string, scalar|null> $query */
    private function get(string $path, array $query = []): array
    {
        if ($query !== []) {
            $path .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
        }

        return $this->request('GET', $path);
    }

    /** @param array<string, mixed> $body */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    /** @param array<string, mixed> $body */
    private function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, $body);
    }

    /** @param array<string, mixed> $body */
    private function patch(string $path, array $body): array
    {
        return $this->request('PATCH', $path, $body);
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            if (Config::get('events.use_internal_dispatch', false)) {
                return $this->decodeTransport(EventsApiInternalDispatch::request($method, $path, $body));
            }

            $baseUrl = (string) Config::get('events.api_base_url', '');
            if ($baseUrl === '') {
                return [
                    'ok' => false,
                    'status' => 0,
                    'data' => null,
                    'error' => 'Events API base URL could not be resolved (set EVENTS_URL or BACKEND_URL)',
                ];
            }

            $url = $baseUrl . $path;
            $cookie = $this->buildCookieHeader();
            $hostHeader = Config::get('events.api_host_header');

            if (function_exists('curl_init')) {
                return $this->decodeTransport($this->requestViaCurl($url, $method, $body, $cookie, is_string($hostHeader) ? $hostHeader : null));
            }

            return $this->decodeTransport($this->requestViaStream($url, $method, $body, $cookie, is_string($hostHeader) ? $hostHeader : null));
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => 'Events API request failed: ' . $e->getMessage(),
            ];
        }
    }

    private function buildCookieHeader(): string
    {
        $parts = [];
        if (!empty($_COOKIE['BIFROSTADMIN'])) {
            $parts[] = 'BIFROSTADMIN=' . $_COOKIE['BIFROSTADMIN'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: string|false, headers: list<string>}
     */
    private function requestViaCurl(string $url, string $method, ?array $body, string $cookie, ?string $hostHeader = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        $headerLines = ['Accept: application/json'];
        if ($hostHeader !== null && $hostHeader !== '') {
            $headerLines[] = 'Host: ' . $hostHeader;
        }
        if ($cookie !== '') {
            $headerLines[] = 'Cookie: ' . $cookie;
        }

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $headerLines[] = 'Content-Type: application/json';
        }

        $responseHeaders = [];
        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders) {
                $trimmed = trim($headerLine);
                if ($trimmed !== '') {
                    $responseHeaders[] = $trimmed;
                }

                return strlen($headerLine);
            },
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $curlOptions);

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

        return ['status' => $status, 'body' => $responseBody, 'headers' => $responseHeaders];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: string|false, headers: list<string>}
     */
    private function requestViaStream(string $url, string $method, ?array $body, string $cookie, ?string $hostHeader = null): array
    {
        $headers = "Accept: application/json\r\n";
        if ($hostHeader !== null && $hostHeader !== '') {
            $headers .= 'Host: ' . $hostHeader . "\r\n";
        }
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

        $context = stream_context_create(['http' => $options]);
        $responseBody = @file_get_contents($url, false, $context);

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($responseBody === false) {
            throw new \RuntimeException('Could not reach events API at ' . $url);
        }

        return ['status' => $status, 'body' => $responseBody, 'headers' => $http_response_header ?? []];
    }

    /**
     * @param array{status: int, body: string|false, headers: list<string>} $transport
     * @return array{ok: bool, status: int, data: array<string, mixed>|string|null, error: string|null, errors?: array<string, string>}
     */
    private function decodeTransport(array $transport): array
    {
        $status = $transport['status'];
        $responseBody = $transport['body'];
        if (!is_string($responseBody) || $responseBody === '') {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Empty response from events API'];
        }

        $contentType = '';
        foreach ($transport['headers'] as $headerLine) {
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = strtolower(trim(substr($headerLine, strlen('Content-Type:'))));
                break;
            }
        }
        if (str_contains($contentType, 'text/csv')) {
            $ok = $status >= 200 && $status < 300;

            return [
                'ok' => $ok,
                'status' => $status,
                'data' => $ok ? $responseBody : null,
                'error' => $ok ? null : ('HTTP ' . $status),
            ];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Invalid JSON from events API'];
        }

        $ok = $status >= 200 && $status < 300;
        $result = [
            'ok' => $ok,
            'status' => $status,
            'data' => $decoded,
            'error' => null,
        ];

        if (!$ok) {
            $error = $decoded['error'] ?? null;
            if (is_array($error)) {
                $result['error'] = (string) ($error['message'] ?? $error['code'] ?? 'HTTP ' . $status);
                if (is_array($error['fields'] ?? null)) {
                    /** @var array<string, string> $fields */
                    $fields = $error['fields'];
                    $result['errors'] = $fields;
                }
                if (is_array($error['candidates'] ?? null)) {
                    $result['data'] = ['error' => $error];
                }
            } else {
                $result['error'] = is_string($error) ? $error : 'HTTP ' . $status;
            }
        }

        return $result;
    }

    /**
     * @param array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null, errors?: array<string, string>} $response
     * @return array{ok: bool, status: int, data: mixed, error: string|null, errors?: array<string, string>}
     */
    private function unwrapData(array $response): array
    {
        if (!($response['ok'] ?? false)) {
            $out = [
                'ok' => false,
                'status' => (int) ($response['status'] ?? 0),
                'data' => null,
                'error' => $response['error'] ?? null,
            ];
            if (isset($response['errors']) && is_array($response['errors'])) {
                $out['errors'] = $response['errors'];
            }

            return $out;
        }

        $payload = $response['data'];

        return [
            'ok' => true,
            'status' => (int) ($response['status'] ?? 200),
            'data' => is_array($payload) ? ($payload['data'] ?? $payload) : $payload,
            'error' => null,
        ];
    }
}
