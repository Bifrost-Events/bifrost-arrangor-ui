<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\ArrangorView;
use App\Support\Response;
use App\Support\Session;

final class CompetitionsController
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

        return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/list', [
            'competitions' => $competitions,
            'context' => $context,
        ]);
    }

    public function createForm(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);

        return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/form', [
            'competition' => null,
            'form' => $this->emptyForm($context),
            'context' => $context,
            'error' => '',
        ]);
    }

    public function createSubmit(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0) {
            Session::setFlash('error', 'Velg en arrangør først.');

            return Response::redirect('/stevner');
        }

        $form = $this->formFromPost();
        $error = $this->validateForm($form, $context);
        if ($error !== '') {
            return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/form', [
                'competition' => null,
                'form' => $form,
                'context' => $context,
                'error' => $error,
            ]);
        }

        $result = $client->createOrganizerCompetition($orgId, $this->apiPayload($form, $context));
        if (!($result['ok'] ?? false)) {
            return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/form', [
                'competition' => null,
                'form' => $form,
                'context' => $context,
                'error' => (string) ($result['error'] ?? 'Kunne ikke opprette stevne.'),
            ]);
        }

        Session::setFlash('success', 'Stevne opprettet.');

        return Response::redirect('/stevner');
    }

    public function editForm(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        $result = $orgId > 0 ? $client->organizerCompetition($orgId, $id) : ['ok' => false, 'data' => null];
        $competition = is_array($result['data']['competition'] ?? null)
            ? $result['data']['competition']
            : (is_array($result['data'] ?? null) ? $result['data'] : null);

        return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/form', [
            'competition' => $competition,
            'form' => $this->formFromCompetition($competition),
            'context' => $context,
            'error' => ($result['ok'] ?? false) ? '' : (string) ($result['error'] ?? 'Kunne ikke hente stevne.'),
        ]);
    }

    public function updateSubmit(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0) {
            return Response::redirect('/stevner');
        }

        $form = $this->formFromPost();
        $error = $this->validateForm($form, $context);
        if ($error !== '') {
            return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/form', [
                'competition' => ['id' => $id],
                'form' => $form,
                'context' => $context,
                'error' => $error,
            ]);
        }

        $result = $client->updateOrganizerCompetition($orgId, $id, $this->apiPayload($form, $context));
        if (!($result['ok'] ?? false)) {
            return ArrangorView::renderContent('competitions.list', 'arrangor/competitions/form', [
                'competition' => ['id' => $id],
                'form' => $form,
                'context' => $context,
                'error' => (string) ($result['error'] ?? 'Kunne ikke oppdatere stevne.'),
            ]);
        }

        Session::setFlash('success', 'Stevne oppdatert.');

        return Response::redirect('/stevner/' . $id);
    }

    /** @param array<string, mixed> $context @return array<string, string> */
    private function emptyForm(array $context): array
    {
        $rounds = is_array($context['rounds'] ?? null) ? $context['rounds'] : [];
        $defaultRoundId = count($rounds) === 1 ? (string) ((int) ($rounds[0]['id'] ?? 0)) : '';

        return [
            'name' => '',
            'event_date' => '',
            'location' => '',
            'description' => '',
            'round_id' => $defaultRoundId,
        ];
    }

    /** @param array<string, mixed>|null $competition @return array<string, string> */
    private function formFromCompetition(?array $competition): array
    {
        if (!is_array($competition)) {
            return [
                'name' => '',
                'event_date' => '',
                'location' => '',
                'description' => '',
                'round_id' => '',
            ];
        }

        $date = (string) ($competition['competition_date'] ?? $competition['event_date'] ?? '');

        return [
            'name' => (string) ($competition['name'] ?? ''),
            'event_date' => $date,
            'location' => (string) ($competition['location'] ?? ''),
            'description' => (string) ($competition['description'] ?? ''),
            'round_id' => (string) ((int) ($competition['round_id'] ?? 0)),
        ];
    }

    /** @return array<string, string> */
    private function formFromPost(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'event_date' => trim((string) ($_POST['event_date'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'round_id' => trim((string) ($_POST['round_id'] ?? '')),
        ];
    }

    /** @param array<string, string> $form @param array<string, mixed> $context */
    private function validateForm(array $form, array $context): string
    {
        if ($form['name'] === '') {
            return 'Stevnenavn er påkrevd.';
        }
        if ((int) $form['round_id'] <= 0) {
            return 'Velg runde for stevnet.';
        }

        $rounds = is_array($context['rounds'] ?? null) ? $context['rounds'] : [];
        if ($rounds === []) {
            return 'Ingen runder er satt opp for sesongen. Kontakt cup-administrator.';
        }

        $validRound = false;
        foreach ($rounds as $round) {
            if (is_array($round) && (int) ($round['id'] ?? 0) === (int) $form['round_id']) {
                $validRound = true;
                break;
            }
        }
        if (!$validRound) {
            return 'Ugyldig runde valgt.';
        }

        return '';
    }

    /**
     * @param array<string, string> $form
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function apiPayload(array $form, array $context): array
    {
        return [
            'name' => $form['name'],
            'competition_date' => $form['event_date'] !== '' ? $form['event_date'] : null,
            'location' => $form['location'],
            'description' => $form['description'],
            'season_id' => (int) ($context['selected_season_id'] ?? 0),
            'round_id' => (int) $form['round_id'],
        ];
    }
}
