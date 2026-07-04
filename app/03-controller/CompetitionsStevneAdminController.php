<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\ArrangorView;
use App\Support\PameldelseViewData;
use App\Support\Response;
use App\Support\Session;
use App\Support\StevneAdminViewData;

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
        $slotNumber = (int) ($_GET['lag'] ?? 0);

        return $this->renderShow($id, $slotNumber);
    }

    public function generateSlots(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang til å generere lag.');

            return Response::redirect($this->stevneAdminUrl($id, 'pameldelse'));
        }

        $result = $client->generateOrganizerCompetitionSlots($orgId, $id, []);
        if ($result['ok'] ?? false) {
            Session::setFlash('success', 'Lag og skiver er generert.');
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke generere lag.'));
        }

        return Response::redirect($this->stevneAdminUrl($id, 'pameldelse'));
    }

    public function approval(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang til å godkjenne stevnet.');

            return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor'));
        }

        $approved = isset($_POST['approve_competition']) && !isset($_POST['unapprove_competition']);
        $result = $client->approveOrganizerCompetition($orgId, $id, $approved);
        if ($result['ok'] ?? false) {
            Session::setFlash('success', $approved ? 'Stevnet er godkjent.' : 'Godkjenning er opphevet.');
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke oppdatere godkjenning.'));
        }

        $slotNumber = (int) ($_POST['lag'] ?? 0);

        return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $slotNumber));
    }

    public function saveSlot(int $id, int $slot): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang til å lagre resultater.');

            return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $slot));
        }

        $rowsPost = $_POST['rows'] ?? null;
        if (!is_array($rowsPost)) {
            Session::setFlash('error', 'Ingen resultater å lagre.');

            return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $slot));
        }

        $competitionResult = $client->organizerCompetition($orgId, $id);
        $competition = is_array($competitionResult['data']['competition'] ?? null)
            ? $competitionResult['data']['competition']
            : (is_array($competitionResult['data'] ?? null) ? $competitionResult['data'] : []);
        $figuresPerSlot = max(1, (int) ($competition['antall_skyttere_per_lag'] ?? 6));
        $meta = StevneAdminViewData::buildMeta($competition, $figuresPerSlot);
        $tbSlots = (int) ($meta['tiebreaker_field_count'] ?? 0);

        $saved = 0;
        $lastError = '';
        foreach ($rowsPost as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $participantId = (int) ($payload['participant_id'] ?? 0);
            if ($participantId < 1) {
                continue;
            }
            if ((int) ($payload['slot_number'] ?? 0) !== $slot) {
                continue;
            }
            if (!StevneAdminViewData::rowHasScoringInput($payload, $tbSlots)) {
                continue;
            }

            $holds = StevneAdminViewData::normalizeHoldsForSave($payload);
            $totals = StevneAdminViewData::totalsFromHolds($holds);
            $tbNorm = StevneAdminViewData::normalizeTiebreakerFromPost($payload, $tbSlots);
            $scores = [
                'slot_id' => (int) ($payload['slot_id'] ?? 0),
                'figure_number' => (int) ($payload['figure_number'] ?? 0),
                'holds_normalized' => $holds,
                'hits' => $totals['hits'],
                'inner_hits' => $totals['inner_hits'],
                'score' => $totals['score'],
            ];
            if ($tbNorm !== null) {
                $scores['tiebreaker_poeng'] = $tbNorm;
            }
            $result = $client->saveOrganizerCompetitionResults($orgId, $id, $slot, [
                'participant_id' => $participantId,
                'scores' => $scores,
            ]);
            if ($result['ok'] ?? false) {
                $saved++;
            } else {
                $lastError = (string) ($result['error'] ?? 'Lagring feilet');
            }
        }

        if ($saved > 0) {
            Session::setFlash('success', 'Resultater lagret for lag ' . $slot . '.');
        } elseif ($lastError !== '') {
            Session::setFlash('error', $lastError);
        }

        $nextSlot = (int) ($_POST['next_slot_number'] ?? 0);

        return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $nextSlot > 0 ? $nextSlot : $slot));
    }

    public function lockSlot(int $id, int $slot): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang til å endre lås.');

            return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $slot));
        }

        $body = [];
        if (isset($_POST['lock_roster'])) {
            $body['lock_roster'] = true;
        } elseif (isset($_POST['unlock_roster'])) {
            $body['unlock_roster'] = true;
        } elseif (isset($_POST['lock_results'])) {
            $body['lock_results'] = true;
        } elseif (isset($_POST['unlock_results'])) {
            $body['unlock_results'] = true;
        } else {
            Session::setFlash('error', 'Ugyldig låsehandling.');

            return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $slot));
        }

        $result = $client->setOrganizerCompetitionSlotLock($orgId, $id, $slot, $body);
        if ($result['ok'] ?? false) {
            $message = (string) ($result['data']['message'] ?? 'Lås oppdatert.');
            Session::setFlash('success', $message);
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke oppdatere lås.'));
        }

        return Response::redirect($this->stevneAdminUrl($id, 'gjennomfor', $slot));
    }

    public function assign(int $id, int $slot): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang til å tilordne deltaker.');

            return $this->redirectAfterRoster($id, $slot);
        }

        $result = $client->assignOrganizerCompetitionParticipant($orgId, $id, [
            'participant_id' => (int) ($_POST['participant_id'] ?? 0),
            'slot_id' => (int) ($_POST['slot_id'] ?? 0),
            'figure_number' => (int) ($_POST['figure_number'] ?? 0),
        ]);
        if ($result['ok'] ?? false) {
            Session::setFlash('success', 'Deltaker er tilordnet skiven.');
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke tilordne deltaker.'));
        }

        return $this->redirectAfterRoster($id, $slot);
    }

    public function remove(int $id, int $slot): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang til å fjerne deltaker.');

            return $this->redirectAfterRoster($id, $slot);
        }

        $figure = (int) ($_POST['figure_number'] ?? 0);
        $result = $client->removeOrganizerCompetitionRegistration($orgId, $id, $slot, $figure);
        if ($result['ok'] ?? false) {
            Session::setFlash('success', 'Deltaker er fjernet fra skiven.');
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke fjerne deltaker.'));
        }

        return $this->redirectAfterRoster($id, $slot);
    }

    public function searchParticipants(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            return Response::json(['items' => []], 403);
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            return Response::json(['items' => []]);
        }

        $result = $client->organizerParticipantSearch($orgId, $id, $query);
        $items = [];
        if ($result['ok'] ?? false) {
            $participants = is_array($result['data']['participants'] ?? null)
                ? $result['data']['participants']
                : [];
            foreach ($participants as $participant) {
                if (!is_array($participant)) {
                    continue;
                }
                $participantId = (int) ($participant['id'] ?? 0);
                if ($participantId < 1) {
                    continue;
                }
                $items[] = [
                    'id' => $participantId,
                    'first_name' => (string) ($participant['first_name'] ?? ''),
                    'last_name' => (string) ($participant['last_name'] ?? ''),
                ];
            }
        }

        return Response::json(['items' => $items]);
    }

    public function reserveSlot(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect($this->stevneAdminUrl($id, 'pameldelse'));
        }

        $reserve = isset($_POST['reserve']) && !isset($_POST['unreserve']);
        $result = $client->reserveOrganizerCompetitionSlot($orgId, $id, [
            'slot_id' => (int) ($_POST['slot_id'] ?? 0),
            'reserve' => $reserve,
        ]);
        if ($result['ok'] ?? false) {
            Session::setFlash('success', $reserve ? 'Hele laget er reservert.' : 'Lag er frigitt.');
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke oppdatere reservasjon.'));
        }

        return Response::redirect($this->stevneAdminUrl($id, 'pameldelse'));
    }

    public function reserveFigure(int $id): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0 || !($context['can_write'] ?? false)) {
            Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect($this->stevneAdminUrl($id, 'pameldelse'));
        }

        $reserve = isset($_POST['reserve']) && !isset($_POST['unreserve']);
        $result = $client->reserveOrganizerCompetitionFigure($orgId, $id, [
            'slot_id' => (int) ($_POST['slot_id'] ?? 0),
            'figure_number' => (int) ($_POST['figure_number'] ?? 0),
            'reserve' => $reserve,
        ]);
        if ($result['ok'] ?? false) {
            Session::setFlash('success', $reserve ? 'Skive er reservert.' : 'Reservasjon er fjernet.');
        } else {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke oppdatere reservasjon.'));
        }

        return Response::redirect($this->stevneAdminUrl($id, 'pameldelse'));
    }

    private function activeView(): string
    {
        $view = trim((string) ($_GET['vis'] ?? 'pameldelse'));

        return in_array($view, ['pameldelse', 'gjennomfor'], true) ? $view : 'pameldelse';
    }

    private function stevneAdminUrl(int $id, string $view = 'pameldelse', int $slot = 0): string
    {
        $url = '/stevner/' . $id . '/stevneadmin?vis=' . rawurlencode($view);
        if ($view === 'gjennomfor' && $slot > 0) {
            $url .= '&lag=' . $slot;
        }

        return $url;
    }

    private function redirectAfterRoster(int $id, int $slot): array
    {
        $view = trim((string) ($_POST['return_vis'] ?? 'pameldelse'));
        if (!in_array($view, ['pameldelse', 'gjennomfor'], true)) {
            $view = 'pameldelse';
        }

        return Response::redirect($this->stevneAdminUrl($id, $view, $view === 'gjennomfor' ? $slot : 0));
    }

    private function renderShow(int $id, int $slotNumber): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);
        $activeView = $this->activeView();

        $competitionResult = $orgId > 0 ? $client->organizerCompetition($orgId, $id) : ['ok' => false, 'data' => null];
        $competition = is_array($competitionResult['data']['competition'] ?? null)
            ? $competitionResult['data']['competition']
            : (is_array($competitionResult['data'] ?? null) ? $competitionResult['data'] : []);

        $stevneAdmin = $orgId > 0
            ? $client->organizerStevneAdmin($orgId, $id)
            : ['ok' => false, 'data' => null, 'error' => 'Ingen arrangør valgt'];

        if ($competition === [] && ($stevneAdmin['ok'] ?? false)) {
            $saData = is_array($stevneAdmin['data'] ?? null) ? $stevneAdmin['data'] : [];
            $competition = is_array($saData['competition'] ?? null) ? $saData['competition'] : [];
        }

        $roster = $orgId > 0
            ? $client->organizerCompetitionRoster($orgId, $id)
            : ['ok' => false, 'data' => null];

        $participants = $orgId > 0
            ? $client->organizerParticipants($orgId)
            : ['ok' => false, 'data' => null];

        $participantItems = [];
        if (($participants['ok'] ?? false) && is_array($participants['data']['participants'] ?? null)) {
            $participantItems = $participants['data']['participants'];
        }

        $gjennomforData = ($stevneAdmin['ok'] ?? false)
            ? StevneAdminViewData::build($stevneAdmin, $slotNumber)
            : null;

        $pameldelseData = ($roster['ok'] ?? false)
            ? PameldelseViewData::build($roster, $competition)
            : null;

        return ArrangorView::renderContent('competitions.stevneadmin', 'arrangor/competitions/stevneadmin', [
            'competition_id' => $id,
            'competition' => $competition,
            'active_view' => $activeView,
            'stevne_admin' => $stevneAdmin,
            'roster' => $roster,
            'view_data' => $gjennomforData,
            'pameldelse_data' => $pameldelseData,
            'participants' => $participantItems,
            'context' => $context,
        ]);
    }
}
