<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\BackendApiClient;

final class ArrangorView
{
    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function render(string $pageId): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        $page = ArrangorMenu::findById($pageId);
        if ($page === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        $user = Auth::user();
        $client = new BackendApiClient();
        $organizerContext = self::resolveOrganizerContext($client);

        if ($pageId === 'overview') {
            $content = Response::partial('arrangor/dashboard', [
                'health' => $client->health(),
                'context' => $organizerContext,
                'user' => $user,
            ]);
        } else {
            $content = Response::partial('arrangor/placeholder', [
                'title' => (string) ($page['title'] ?? ''),
                'description' => (string) ($page['description'] ?? ''),
            ]);
        }

        return self::layout($pageId, $page, $content, $user, $organizerContext, Session::pullFlash());
    }

    /**
     * @param array<string, mixed> $contentData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function renderContent(string $pageId, string $partial, array $contentData = [], bool $requireOrganizer = true): array
    {
        if ($requireOrganizer) {
            if ($redirect = Auth::requireOrganizer()) {
                return $redirect;
            }
        } elseif ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        $page = ArrangorMenu::findById($pageId);
        if ($page === null) {
            $page = [
                'id' => $pageId,
                'title' => $contentData['title'] ?? 'Arrangør',
                'description' => $contentData['description'] ?? '',
            ];
        }

        $user = Auth::user();
        $client = new BackendApiClient();
        $organizerContext = self::resolveOrganizerContext($client);

        $flash = Session::pullFlash();
        $contentData['flash'] = $flash;
        $contentData['page'] = $page;
        $contentData['user'] = $user;
        $contentData['organizer_context'] = $organizerContext;
        $content = Response::partial($partial, $contentData);

        return self::layout($pageId, $page, $content, $user, $organizerContext, $flash);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $organizerContext
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private static function layout(string $pageId, array $page, string $content, ?array $user, array $organizerContext, ?array $flash = null): array
    {
        return Response::view('arrangor/layout', [
            'title' => (string) ($page['title'] ?? 'Arrangør'),
            'content' => $content,
            'active_nav' => $pageId,
            'user' => $user,
            'menu_sections' => ArrangorMenu::sections(),
            'menu_overview' => ArrangorMenu::overview(),
            'organizer_context' => $organizerContext,
            'flash' => $flash,
            'public_register_url' => (string) Config::get('app.public_register_url', ''),
        ]);
    }

    /**
     * @return array{
     *   selected_organization_id: int|null,
     *   selected_organization: array<string, mixed>|null,
     *   selected_season_id: int|null,
     *   selected_season: array<string, mixed>|null,
     *   selectable_organizations: list<array<string, mixed>>,
     *   tenant: array<string, mixed>|null,
     *   tenant_name: string,
     *   rounds: list<array<string, mixed>>,
     *   approval: array<string, mixed>|null,
     *   can_write: bool
     * }
     */
    public static function resolveOrganizerContext(BackendApiClient $client): array
    {
        if (isset($_GET['organization_id'])) {
            $raw = (string) $_GET['organization_id'];
            if ($raw === '' || $raw === '0') {
                Session::setSelectedOrganizationId(null);
            } else {
                Session::setSelectedOrganizationId((int) $raw);
            }
        }
        if (isset($_GET['season_id'])) {
            $raw = (string) $_GET['season_id'];
            if ($raw === '' || $raw === '0') {
                Session::setSelectedSeasonId(null);
            } else {
                Session::setSelectedSeasonId((int) $raw);
            }
        }

        $orgId = Session::getSelectedOrganizationId() ?? 0;
        $seasonId = Session::getSelectedSeasonId() ?? 0;

        $portalTenant = TenantContext::current();
        $portalTenantId = (int) ($portalTenant['tenant_id'] ?? 0);
        $portalHost = (string) ($portalTenant['host'] ?? '');

        $response = $client->organizerContext($orgId, $seasonId, $portalTenantId, $portalHost);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        if ($response['ok']) {
            $resolvedOrgId = (int) ($data['organization_id'] ?? 0);
            $resolvedSeasonId = (int) ($data['season_id'] ?? 0);
            if ($resolvedOrgId > 0) {
                Session::setSelectedOrganizationId($resolvedOrgId);
            }
            if ($resolvedSeasonId > 0) {
                Session::setSelectedSeasonId($resolvedSeasonId);
            }
        }

        $organizations = [];
        foreach ($data['organizations'] ?? [] as $org) {
            if (is_array($org)) {
                $organizations[] = $org;
            }
        }

        $selectedOrg = is_array($data['organization'] ?? null) ? $data['organization'] : null;
        $selectedSeason = is_array($data['season'] ?? null) ? $data['season'] : null;
        $approval = is_array($data['approval'] ?? null) ? $data['approval'] : null;

        return [
            'selected_organization_id' => (int) ($data['organization_id'] ?? 0) ?: null,
            'selected_organization' => $selectedOrg,
            'selected_season_id' => (int) ($data['season_id'] ?? 0) ?: null,
            'selected_season' => $selectedSeason,
            'selectable_organizations' => $organizations,
            'tenant' => is_array($data['tenant'] ?? null) ? $data['tenant'] : null,
            'tenant_name' => trim((string) (
                is_array($data['tenant'] ?? null)
                    ? ($data['tenant']['name'] ?? '')
                    : ($selectedOrg['tenant_name'] ?? '')
            )),
            'rounds' => is_array($data['rounds'] ?? null) ? $data['rounds'] : [],
            'approval' => $approval,
            'can_write' => ($data['can_write'] ?? false) === true,
        ];
    }
}
