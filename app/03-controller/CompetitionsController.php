<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\ArrangorView;
use App\Support\CompetitionLimits;
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

        $competitionId = (int) (
            $result['data']['competition']['id']
            ?? $result['data']['id']
            ?? 0
        );
        if ($competitionId > 0) {
            $slotResult = $client->generateOrganizerCompetitionSlots($orgId, $competitionId, [
                'slot_count' => (int) $form['slot_count'],
                'shooters_per_slot' => (int) $form['shooters_per_slot'],
                'minutes_between_slots' => (int) $form['minutes_between_slots'],
                'first_start_time' => $form['first_start_time'],
            ]);
            if (!($slotResult['ok'] ?? false)) {
                Session::setFlash(
                    'info',
                    'Stevne opprettet, men lag og skiver ble ikke generert: '
                    . (string) ($slotResult['error'] ?? 'ukjent feil')
                );

                return Response::redirect('/stevner/' . $competitionId);
            }
        }

        Session::setFlash('success', 'Stevne opprettet med lagoppsett.');

        return Response::redirect($competitionId > 0 ? '/stevner/' . $competitionId . '/stevneadmin?vis=pameldelse' : '/stevner');
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

        if (!empty($form['regenerate_slots'])) {
            $slotResult = $client->generateOrganizerCompetitionSlots($orgId, $id, [
                'slot_count' => (int) $form['slot_count'],
                'shooters_per_slot' => (int) $form['shooters_per_slot'],
                'minutes_between_slots' => (int) $form['minutes_between_slots'],
                'first_start_time' => $form['first_start_time'],
            ]);
            if (!($slotResult['ok'] ?? false)) {
                Session::setFlash(
                    'info',
                    'Stevne lagret, men lag og skiver ble ikke regenerert: '
                    . (string) ($slotResult['error'] ?? 'ukjent feil')
                );

                return Response::redirect('/stevner/' . $id);
            }
            Session::setFlash('success', 'Stevne og lagoppsett oppdatert.');

            return Response::redirect('/stevner/' . $id);
        }

        Session::setFlash('success', 'Stevne oppdatert.');

        return Response::redirect('/stevner/' . $id);
    }

    /** @param array<string, mixed> $context @return array<string, string> */
    private function emptyForm(array $context): array
    {
        $rounds = is_array($context['rounds'] ?? null) ? $context['rounds'] : [];
        $defaultRoundId = count($rounds) === 1 ? (string) ((int) ($rounds[0]['id'] ?? 0)) : '';

        return array_merge($this->defaultSetupFields(), [
            'name' => '',
            'event_date' => '',
            'location' => '',
            'description' => '',
            'round_id' => $defaultRoundId,
            'regenerate_slots' => '',
        ]);
    }

    /** @return array<string, string> */
    private function defaultSetupFields(): array
    {
        return [
            'scoring_mode' => 'njff',
            'invitation_text' => '',
            'advance_registration_enabled' => '1',
            'registration_start' => '',
            'registration_end' => '',
            'is_published' => '',
            'shooters_per_slot' => '6',
            'slot_count' => '4',
            'first_start_time' => '09:00',
            'minutes_between_slots' => '60',
            'tiebreaker_figure_order' => '[]',
        ];
    }

    /** @param array<string, mixed>|null $competition @return array<string, string> */
    private function formFromCompetition(?array $competition): array
    {
        if (!is_array($competition)) {
            return array_merge($this->defaultSetupFields(), [
                'name' => '',
                'event_date' => '',
                'location' => '',
                'description' => '',
                'round_id' => '',
                'regenerate_slots' => '',
            ]);
        }

        $date = (string) ($competition['competition_date'] ?? $competition['event_date'] ?? '');
        $tiebreaker = $competition['tiebreaker_figure_order'] ?? '[]';
        if (is_array($tiebreaker)) {
            $tiebreaker = json_encode(array_values($tiebreaker), JSON_THROW_ON_ERROR);
        }

        return array_merge($this->defaultSetupFields(), [
            'name' => (string) ($competition['name'] ?? ''),
            'event_date' => $date,
            'location' => (string) ($competition['location'] ?? ''),
            'description' => (string) ($competition['description'] ?? ''),
            'round_id' => (string) ((int) ($competition['round_id'] ?? 0)),
            'scoring_mode' => (string) ($competition['scoring_mode'] ?? 'njff'),
            'invitation_text' => (string) ($competition['invitation_text'] ?? ''),
            'advance_registration_enabled' => !empty($competition['advance_registration_enabled']) ? '1' : '',
            'registration_start' => (string) ($competition['registration_start'] ?? ''),
            'registration_end' => (string) ($competition['registration_end'] ?? ''),
            'is_published' => !empty($competition['is_published']) ? '1' : '',
            'shooters_per_slot' => (string) ((int) (
                $competition['antall_skyttere_per_lag']
                ?? $competition['shooters_per_slot']
                ?? 6
            )),
            'slot_count' => (string) ((int) (
                $competition['antall_lag']
                ?? $competition['slot_count']
                ?? 4
            )),
            'minutes_between_slots' => (string) ((int) (
                $competition['minutter_mellom_lag']
                ?? $competition['minutes_between_slots']
                ?? 60
            )),
            'tiebreaker_figure_order' => (string) $tiebreaker,
            'regenerate_slots' => '',
        ]);
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
            'scoring_mode' => trim((string) ($_POST['scoring_mode'] ?? 'njff')),
            'invitation_text' => trim((string) ($_POST['invitation_text'] ?? '')),
            'advance_registration_enabled' => isset($_POST['advance_registration_enabled']) ? '1' : '',
            'registration_start' => trim((string) ($_POST['registration_start'] ?? '')),
            'registration_end' => trim((string) ($_POST['registration_end'] ?? '')),
            'is_published' => isset($_POST['is_published']) ? '1' : '',
            'shooters_per_slot' => trim((string) ($_POST['shooters_per_slot'] ?? '6')),
            'slot_count' => trim((string) ($_POST['slot_count'] ?? '4')),
            'first_start_time' => trim((string) ($_POST['first_start_time'] ?? '09:00')),
            'minutes_between_slots' => trim((string) ($_POST['minutes_between_slots'] ?? '60')),
            'tiebreaker_figure_order' => trim((string) ($_POST['tiebreaker_figure_order'] ?? '[]')),
            'regenerate_slots' => isset($_POST['regenerate_slots']) ? '1' : '',
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

        $shooters = (int) $form['shooters_per_slot'];
        if ($shooters < 1 || $shooters > 20) {
            return 'Skyttere per lag må være mellom 1 og 20.';
        }

        $slotCount = (int) $form['slot_count'];
        if ($slotCount < 1 || $slotCount > CompetitionLimits::MAX_ANTALL_LAG) {
            return 'Antall lag må være mellom 1 og ' . CompetitionLimits::MAX_ANTALL_LAG . '.';
        }

        $minutes = (int) $form['minutes_between_slots'];
        if ($minutes < 5 || $minutes > 180) {
            return 'Tid mellom lag må være mellom 5 og 180 minutter.';
        }

        if (!in_array($form['scoring_mode'], ['njff', 'dfs'], true)) {
            return 'Ugyldig resultatformat.';
        }

        $tiebreakerError = $this->validateTiebreakerOrder($form['tiebreaker_figure_order']);
        if ($tiebreakerError !== '') {
            return $tiebreakerError;
        }

        return '';
    }

    private function validateTiebreakerOrder(string $raw): string
    {
        if ($raw === '' || $raw === '[]') {
            return '';
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'Ugyldig skillefigur-rekkefølge.';
        }
        if (!is_array($decoded)) {
            return 'Ugyldig skillefigur-rekkefølge.';
        }
        $seen = [];
        foreach ($decoded as $value) {
            $figure = (int) $value;
            if ($figure < 1 || $figure > CompetitionLimits::MAX_SKILLEFIGUR_SKIVE_NR) {
                return 'Skillefigur må være skive 1–' . CompetitionLimits::MAX_SKILLEFIGUR_SKIVE_NR . '.';
            }
            if (isset($seen[$figure])) {
                return 'Skillefigur kan ikke brukes flere ganger.';
            }
            $seen[$figure] = true;
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
            'scoring_mode' => $form['scoring_mode'],
            'invitation_text' => $form['invitation_text'] !== '' ? $form['invitation_text'] : null,
            'advance_registration_enabled' => $form['advance_registration_enabled'] === '1',
            'registration_start' => $form['registration_start'] !== '' ? $form['registration_start'] : null,
            'registration_end' => $form['registration_end'] !== '' ? $form['registration_end'] : null,
            'is_published' => $form['is_published'] === '1',
            'shooters_per_slot' => (int) $form['shooters_per_slot'],
            'slot_count' => (int) $form['slot_count'],
            'minutes_between_slots' => (int) $form['minutes_between_slots'],
            'tiebreaker_figure_order' => $form['tiebreaker_figure_order'],
        ];
    }
}
