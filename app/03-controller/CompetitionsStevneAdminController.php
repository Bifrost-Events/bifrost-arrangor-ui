<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\ArrangorView;
use App\Support\Response;

final class CompetitionsStevneAdminController
{
    public function index(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);
        $seasonId = (int) ($context['selected_season_id'] ?? 0);

        $competitions = $orgId > 0
            ? $client->organizerCompetitions($orgId, $seasonId)
            : ['ok' => false, 'data' => null, 'error' => 'Ingen arrangør valgt'];

        return ArrangorView::renderContent('competitions.stevneadmin', 'arrangor/competitions/stevneadmin-index', [
            'competitions' => $competitions,
            'context' => $context,
        ]);
    }

    public function show(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        $stevneAdmin = $orgId > 0
            ? $client->organizerStevneAdmin($orgId, $id)
            : ['ok' => false, 'data' => null, 'error' => 'Ingen arrangør valgt'];

        return ArrangorView::renderContent('competitions.stevneadmin', 'arrangor/competitions/stevneadmin', [
            'competition_id' => $id,
            'stevne_admin' => $stevneAdmin,
            'context' => $context,
        ]);
    }
}
