<?php

declare(strict_types=1);

namespace App\Support;

final class StevneAdminViewData
{
    /**
     * @param array{ok: bool, data: array<string, mixed>|null, error: string|null} $stevneAdmin
     * @return array{
     *   competition: array<string, mixed>,
     *   slot_summary: list<array<string, mixed>>,
     *   slot_rows: list<array<string, mixed>>,
     *   selected_slot_number: int,
     *   figures_per_slot: int,
     *   stevneadmin_approved: bool,
     *   stevneadmin_approved_at: string|null,
     *   prev_slot_number: int,
     *   next_slot_number: int,
     *   selected_slot_roster_locked: bool,
     *   selected_slot_results_locked: bool
     * }
     */
    public static function build(array $stevneAdmin, int $selectedSlotNumber = 0): array
    {
        $data = is_array($stevneAdmin['data'] ?? null) ? $stevneAdmin['data'] : [];
        $competition = is_array($data['competition'] ?? null) ? $data['competition'] : [];
        $slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $registrations = is_array($data['registrations'] ?? null) ? $data['registrations'] : [];
        $rawResults = is_array($data['results'] ?? null) ? $data['results'] : [];

        $figuresPerSlot = max(1, (int) ($competition['antall_skyttere_per_lag'] ?? 6));
        $participantClasses = is_array($data['participant_classes'] ?? null) ? $data['participant_classes'] : [];
        $resultRows = self::enrichResults($rawResults, $slots, $registrations, $participantClasses);
        $slotSummary = self::slotSummary($resultRows, $registrations, $slots);
        $slotRows = $selectedSlotNumber > 0
            ? self::rowsForSlot($resultRows, $registrations, $slots, $selectedSlotNumber, $figuresPerSlot, $participantClasses)
            : [];

        $approvedRaw = $competition['stevneadmin_approved'] ?? 0;
        $approvedAtRaw = $competition['stevneadmin_approved_at'] ?? null;
        $approvedAt = is_string($approvedAtRaw) && trim($approvedAtRaw) !== '' ? trim($approvedAtRaw) : null;

        $prevSlot = 0;
        $nextSlot = 0;
        $rosterLocked = false;
        $resultsLocked = false;
        if ($selectedSlotNumber > 0 && $slotSummary !== []) {
            $prev = 0;
            foreach ($slotSummary as $slot) {
                $sn = (int) ($slot['slot_number'] ?? 0);
                if ($sn === $selectedSlotNumber) {
                    $rosterLocked = (bool) ($slot['is_roster_locked'] ?? false);
                    $resultsLocked = (bool) ($slot['is_locked'] ?? false);
                }
                if ($sn < $selectedSlotNumber) {
                    $prev = $sn;
                    continue;
                }
                if ($sn > $selectedSlotNumber) {
                    $nextSlot = $sn;
                    break;
                }
            }
            $prevSlot = $prev;
        }

        return [
            'competition' => $competition,
            'slot_summary' => $slotSummary,
            'slot_rows' => $slotRows,
            'selected_slot_number' => $selectedSlotNumber,
            'figures_per_slot' => $figuresPerSlot,
            'stevneadmin_approved' => (int) $approvedRaw === 1 || $approvedRaw === true,
            'stevneadmin_approved_at' => $approvedAt,
            'prev_slot_number' => $prevSlot,
            'next_slot_number' => $nextSlot,
            'selected_slot_roster_locked' => $rosterLocked,
            'selected_slot_results_locked' => $resultsLocked,
            'stevne_admin_meta' => self::buildMeta($competition, $figuresPerSlot),
        ];
    }

    /**
     * @param array<string, mixed> $competition
     * @return array{
     *   scoring_mode: string,
     *   tiebreaker_figure_order: list<int>,
     *   show_skillefigur: bool,
     *   tiebreaker_field_count: int
     * }
     */
    public static function buildMeta(array $competition, int $figuresPerSlot): array
    {
        $mode = strtolower(trim((string) ($competition['scoring_mode'] ?? 'njff')));
        if (!in_array($mode, ['njff', 'dfs'], true)) {
            $mode = 'njff';
        }
        $orderCapped = self::capTiebreakerFigureOrder($competition['tiebreaker_figure_order'] ?? null, $figuresPerSlot);
        $showTb = $mode !== 'dfs' || $orderCapped !== [];

        return [
            'scoring_mode' => $mode,
            'tiebreaker_figure_order' => $orderCapped,
            'show_skillefigur' => $showTb,
            'tiebreaker_field_count' => $showTb ? ($orderCapped === [] ? 1 : count($orderCapped)) : 0,
        ];
    }

    /**
     * @return list<int>
     */
    public static function capTiebreakerFigureOrder(mixed $raw, int $figuresPerSlot): array
    {
        $list = [];
        if (is_array($raw)) {
            $list = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? $decoded : [];
        }
        $cap = $figuresPerSlot > 0 ? min($figuresPerSlot, 6) : 6;
        $out = [];
        foreach ($list as $n) {
            $x = (int) $n;
            if ($x >= 1 && $x <= 6 && $x <= $cap) {
                $out[] = $x;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function tiebreakerValuesForDisplay(mixed $scoreBreakdown, int $fieldCount): array
    {
        $out = array_fill(0, max(0, $fieldCount), '');
        if ($fieldCount < 1) {
            return $out;
        }
        $decoded = self::decodeBreakdown($scoreBreakdown);
        if ($decoded === null) {
            return $out;
        }
        $tbSrc = $decoded['tiebreaker_poeng'] ?? null;
        if ($tbSrc === null || $tbSrc === '') {
            return $out;
        }
        if (is_array($tbSrc)) {
            foreach ($tbSrc as $ti => $tv) {
                $ix = (int) $ti;
                if ($ix < 0 || $ix >= $fieldCount) {
                    continue;
                }
                if ($tv !== null && $tv !== '') {
                    $out[$ix] = (string) (int) $tv;
                }
            }

            return $out;
        }
        $out[0] = (string) (int) $tbSrc;

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function normalizeTiebreakerFromPost(array $payload, int $slotCount): mixed
    {
        if ($slotCount < 1) {
            return null;
        }
        $raw = $payload['tb'] ?? null;
        $values = [];
        $any = false;
        for ($i = 0; $i < $slotCount; $i++) {
            $cell = is_array($raw) ? ($raw[$i] ?? $raw[(string) $i] ?? null) : ($i === 0 ? $raw : null);
            $s = trim((string) ($cell ?? ''));
            if ($s === '' || !is_numeric($s)) {
                $values[] = null;
                continue;
            }
            $any = true;
            $values[] = (float) min(99, max(0, (int) round((float) $s)));
        }
        if (!$any) {
            return null;
        }
        if ($slotCount === 1) {
            return TiebreakerLexicographic::normalizePayload($values[0]);
        }

        return TiebreakerLexicographic::normalizePayload($values);
    }

    public static function poengFromHoldValues(string $tStr, string $iStr): ?int
    {
        if ($tStr === '' && $iStr === '') {
            return null;
        }
        $treff = max(0, min(6, (int) $tStr));
        $innertreff = max(0, min(6, (int) $iStr));
        $innertreff = min($treff, $innertreff);

        return ($treff * 3) + ($innertreff * 2);
    }

    public static function classLabelForRow(array $row): string
    {
        $participantId = (int) ($row['participant_id'] ?? 0);
        if ($participantId < 1) {
            return '–';
        }
        $classId = (int) ($row['class_id'] ?? 0);
        $className = trim((string) ($row['class_name'] ?? ''));
        if ($classId < 1) {
            return 'Mangler klasse';
        }

        return $className !== '' ? $className : ('#' . $classId);
    }

    /**
     * @param array<int, array{t: string, i: string}> $holds
     * @param list<string> $tbVals
     */
    public static function rowStatus(bool $isFilled, array $holds, array $tbVals, int $tbCount): string
    {
        if (!$isFilled) {
            return '–';
        }
        if (self::rowHasScoringInput(['h' => $holds], $tbCount)) {
            return 'OK';
        }
        foreach ($tbVals as $tv) {
            if (trim((string) $tv) !== '') {
                return 'OK';
            }
        }

        return '–';
    }

    /**
     * @param array<string, mixed>|null $resultRow
     * @param array<string, mixed>|null $registrationRow
     * @param array<int, array{class_id: int, class_name: string}> $participantClasses
     * @return array{class_id: ?int, class_name: ?string}
     */
    private static function effectiveClass(?array $resultRow, ?array $registrationRow, array $participantClasses): array
    {
        $id = null;
        $name = null;
        if (is_array($resultRow)) {
            $rid = isset($resultRow['class_id']) ? (int) $resultRow['class_id'] : 0;
            if ($rid > 0) {
                $id = $rid;
                $label = trim((string) ($resultRow['class_name'] ?? $resultRow['class'] ?? ''));
                $name = $label !== '' ? $label : null;
            }
        }
        if ($id === null) {
            $pid = (int) (is_array($resultRow) ? ($resultRow['participant_id'] ?? 0) : 0);
            if ($pid < 1 && is_array($registrationRow)) {
                $pid = (int) ($registrationRow['participant_id'] ?? 0);
            }
            if ($pid > 0 && isset($participantClasses[$pid])) {
                $entry = $participantClasses[$pid];
                $id = (int) ($entry['class_id'] ?? 0);
                $label = trim((string) ($entry['class_name'] ?? ''));
                $name = $label !== '' ? $label : null;
            }
        }

        return ['class_id' => $id, 'class_name' => $name];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function rowHasScoringInput(array $payload, int $tbSlots = 0): bool
    {
        $raw = $payload['h'] ?? null;
        if (is_array($raw)) {
            for ($idx = 0; $idx < 6; $idx++) {
                $cell = $raw[$idx] ?? $raw[(string) $idx] ?? $raw[$idx + 1] ?? $raw[(string) ($idx + 1)] ?? null;
                if (!is_array($cell)) {
                    continue;
                }
                $tStr = trim((string) ($cell['t'] ?? $cell['treff'] ?? ''));
                $iStr = trim((string) ($cell['i'] ?? $cell['innertreff'] ?? ''));
                if ($tStr !== '' || $iStr !== '') {
                    return true;
                }
            }
        }

        if ($tbSlots < 1) {
            return false;
        }
        $tbRaw = $payload['tb'] ?? null;
        for ($i = 0; $i < $tbSlots; $i++) {
            $cell = is_array($tbRaw) ? ($tbRaw[$i] ?? $tbRaw[(string) $i] ?? null) : ($i === 0 ? $tbRaw : null);
            if (trim((string) ($cell ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{treff: int|null, innertreff: int|null, poeng: int|null}>
     */
    public static function normalizeHoldsForSave(array $payload): array
    {
        $raw = $payload['h'] ?? null;
        $out = [];
        for ($idx = 0; $idx < 6; $idx++) {
            $cell = is_array($raw)
                ? ($raw[$idx] ?? $raw[(string) $idx] ?? $raw[$idx + 1] ?? $raw[(string) ($idx + 1)] ?? null)
                : null;
            $tStr = '';
            $iStr = '';
            if (is_array($cell)) {
                $tStr = trim((string) ($cell['t'] ?? $cell['treff'] ?? ''));
                $iStr = trim((string) ($cell['i'] ?? $cell['innertreff'] ?? ''));
            }
            if ($tStr === '' && $iStr === '') {
                $out[] = ['treff' => null, 'innertreff' => null, 'poeng' => null];
                continue;
            }
            $treff = max(0, min(6, (int) $tStr));
            $innertreff = max(0, min(6, (int) $iStr));
            $innertreff = min($treff, $innertreff);
            $out[] = [
                'treff' => $treff,
                'innertreff' => $innertreff,
                'poeng' => ($treff * 3) + ($innertreff * 2),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{treff: int|null, innertreff: int|null, poeng: int|null}> $holds
     * @return array{hits: int|null, inner_hits: int|null, score: float|null}
     */
    public static function totalsFromHolds(array $holds): array
    {
        $hits = 0;
        $innerHits = 0;
        $score = 0.0;
        foreach ($holds as $h) {
            $tr = $h['treff'] ?? null;
            $inn = $h['innertreff'] ?? null;
            if ($tr === null && $inn === null) {
                continue;
            }
            $ti = $tr !== null ? (int) $tr : 0;
            $ii = $inn !== null ? (int) $inn : 0;
            $hits += $ti;
            $innerHits += $ii;
            $p = $h['poeng'] ?? null;
            $score += $p !== null ? (int) $p : (($ti * 3) + ($ii * 2));
        }

        return [
            'hits' => $hits > 0 ? $hits : null,
            'inner_hits' => $innerHits > 0 ? $innerHits : null,
            'score' => $score > 0 ? $score : null,
        ];
    }

    /**
     * @return array<int, array{t: string, i: string}> 0-indexed holds 0..5
     */
    public static function holdsForDisplay(mixed $scoreBreakdown): array
    {
        $out = [];
        for ($idx = 0; $idx < 6; $idx++) {
            $out[$idx] = ['t' => '', 'i' => ''];
        }
        $decoded = self::decodeBreakdown($scoreBreakdown);
        if ($decoded === null) {
            return $out;
        }
        $holds = $decoded['holds_normalized'] ?? $decoded['holds'] ?? [];
        if (!is_array($holds)) {
            return $out;
        }
        for ($idx = 0; $idx < 6; $idx++) {
            $cell = $holds[$idx] ?? null;
            if (!is_array($cell)) {
                continue;
            }
            $t = $cell['treff'] ?? $cell['t'] ?? null;
            $inn = $cell['innertreff'] ?? $cell['i'] ?? null;
            $out[$idx] = [
                't' => $t !== null && $t !== '' ? (string) (int) $t : '',
                'i' => $inn !== null && $inn !== '' ? (string) (int) $inn : '',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rawResults
     * @param list<array<string, mixed>> $slots
     * @param list<array<string, mixed>> $registrations
     * @param array<int, array{class_id: int, class_name: string}> $participantClasses
     * @return list<array<string, mixed>>
     */
    private static function enrichResults(
        array $rawResults,
        array $slots,
        array $registrations,
        array $participantClasses,
    ): array {
        /** @var array<int, int> $slotIdToNumber */
        $slotIdToNumber = [];
        foreach ($slots as $slot) {
            $slotId = (int) ($slot['id'] ?? 0);
            $slotNumber = (int) ($slot['slot_number'] ?? 0);
            if ($slotId > 0 && $slotNumber > 0) {
                $slotIdToNumber[$slotId] = $slotNumber;
            }
        }

        /** @var array<int, array<string, mixed>> $regByParticipant */
        $regByParticipant = [];
        foreach ($registrations as $reg) {
            $participantId = (int) ($reg['participant_id'] ?? 0);
            if ($participantId > 0) {
                $regByParticipant[$participantId] = $reg;
            }
        }

        $enriched = [];
        foreach ($rawResults as $row) {
            if (!is_array($row)) {
                continue;
            }
            $participantId = (int) ($row['participant_id'] ?? 0);
            if ($participantId < 1) {
                continue;
            }
            $reg = $regByParticipant[$participantId] ?? null;
            $slotId = (int) ($row['slot_id'] ?? (is_array($reg) ? ($reg['slot_id'] ?? 0) : 0));
            $slotNumber = (int) ($row['slot_number'] ?? (is_array($reg) ? ($reg['slot_number'] ?? 0) : 0));
            if ($slotNumber < 1 && $slotId > 0) {
                $slotNumber = $slotIdToNumber[$slotId] ?? 0;
            }
            $figureNumber = (int) ($row['figure_number'] ?? (is_array($reg) ? ($reg['figure_number'] ?? 0) : 0));
            $eff = self::effectiveClass($row, is_array($reg) ? $reg : null, $participantClasses);

            $enriched[] = [
                'participant_id' => $participantId,
                'slot_id' => $slotId,
                'slot_number' => $slotNumber,
                'figure_number' => $figureNumber,
                'first_name' => (string) ($row['first_name'] ?? (is_array($reg) ? ($reg['first_name'] ?? '') : '')),
                'last_name' => (string) ($row['last_name'] ?? (is_array($reg) ? ($reg['last_name'] ?? '') : '')),
                'score_breakdown' => $row['score_breakdown'] ?? null,
                'tiebreaker_poeng' => self::tiebreakerFromBreakdown($row['score_breakdown'] ?? null),
                'score' => self::scoreFromRow($row),
                'has_result_row' => true,
                'class_id' => $eff['class_id'],
                'class_name' => $eff['class_name'],
            ];
        }

        return $enriched;
    }

    /**
     * @param list<array<string, mixed>> $resultRows
     * @param list<array<string, mixed>> $registrations
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private static function slotSummary(array $resultRows, array $registrations, array $slots): array
    {
        /** @var array<int, array<string, mixed>> $summary */
        $summary = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slotNumber = (int) ($slot['slot_number'] ?? 0);
            if ($slotNumber < 1) {
                continue;
            }
            $summary[$slotNumber] = [
                'slot_number' => $slotNumber,
                'start_time' => (string) ($slot['start_time'] ?? ''),
                'participants' => 0,
                'with_score' => 0,
                'is_reserved' => (bool) ($slot['is_reserved'] ?? false),
                'is_roster_locked' => (bool) ($slot['is_roster_locked'] ?? false),
                'is_locked' => (bool) ($slot['is_locked'] ?? false),
            ];
        }

        /** @var array<int, array<string, mixed>> $resultByParticipant */
        $resultByParticipant = [];
        foreach ($resultRows as $row) {
            $participantId = (int) ($row['participant_id'] ?? 0);
            if ($participantId > 0) {
                $resultByParticipant[$participantId] = $row;
            }
        }

        foreach ($registrations as $reg) {
            if (!is_array($reg)) {
                continue;
            }
            $slotNumber = (int) ($reg['slot_number'] ?? 0);
            if ($slotNumber < 1) {
                continue;
            }
            if (!isset($summary[$slotNumber])) {
                $summary[$slotNumber] = [
                    'slot_number' => $slotNumber,
                    'start_time' => (string) ($reg['start_time'] ?? ''),
                    'participants' => 0,
                    'with_score' => 0,
                    'is_reserved' => false,
                    'is_roster_locked' => false,
                    'is_locked' => false,
                ];
            }
            $summary[$slotNumber]['participants']++;
            $participantId = (int) ($reg['participant_id'] ?? 0);
            if ($participantId > 0 && self::resultHasScore($resultByParticipant[$participantId] ?? null)) {
                $summary[$slotNumber]['with_score']++;
            }
        }

        ksort($summary);

        return array_values($summary);
    }

    /**
     * @param list<array<string, mixed>> $resultRows
     * @param list<array<string, mixed>> $registrations
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private static function rowsForSlot(
        array $resultRows,
        array $registrations,
        array $slots,
        int $slotNumber,
        int $figuresPerSlot,
        array $participantClasses = [],
    ): array {
        if ($slotNumber < 1 || $figuresPerSlot < 1) {
            return [];
        }

        $slotIdForNumber = 0;
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            if ((int) ($slot['slot_number'] ?? 0) === $slotNumber) {
                $slotIdForNumber = (int) ($slot['id'] ?? 0);
                break;
            }
        }

        /** @var array<int, array<string, mixed>> $rowsByFigure */
        $rowsByFigure = [];
        foreach ($resultRows as $row) {
            if ((int) ($row['slot_number'] ?? 0) !== $slotNumber) {
                continue;
            }
            $figure = (int) ($row['figure_number'] ?? 0);
            if ($figure >= 1) {
                $rowsByFigure[$figure] = $row;
            }
        }

        foreach ($registrations as $reg) {
            if (!is_array($reg)) {
                continue;
            }
            if ((int) ($reg['slot_number'] ?? 0) !== $slotNumber) {
                continue;
            }
            $figure = (int) ($reg['figure_number'] ?? 0);
            if ($figure < 1 || isset($rowsByFigure[$figure])) {
                continue;
            }
            $eff = self::effectiveClass(null, $reg, $participantClasses);
            $rowsByFigure[$figure] = [
                'slot_id' => (int) ($reg['slot_id'] ?? 0),
                'slot_number' => $slotNumber,
                'figure_number' => $figure,
                'participant_id' => (int) ($reg['participant_id'] ?? 0),
                'first_name' => (string) ($reg['first_name'] ?? ''),
                'last_name' => (string) ($reg['last_name'] ?? ''),
                'score_breakdown' => null,
                'score' => null,
                'has_result_row' => false,
                'class_id' => $eff['class_id'],
                'class_name' => $eff['class_name'],
            ];
        }

        $out = [];
        for ($figure = 1; $figure <= $figuresPerSlot; $figure++) {
            $row = $rowsByFigure[$figure] ?? [
                'slot_id' => $slotIdForNumber,
                'slot_number' => $slotNumber,
                'figure_number' => $figure,
                'participant_id' => 0,
                'first_name' => '',
                'last_name' => '',
                'score_breakdown' => null,
                'score' => null,
                'has_result_row' => false,
                'class_id' => null,
                'class_name' => null,
            ];
            if ((int) ($row['participant_id'] ?? 0) > 0 && !array_key_exists('class_id', $row)) {
                $eff = self::effectiveClass($row, null, $participantClasses);
                $row['class_id'] = $eff['class_id'];
                $row['class_name'] = $eff['class_name'];
            }
            if ((int) ($row['slot_id'] ?? 0) < 1 && $slotIdForNumber > 0) {
                $row['slot_id'] = $slotIdForNumber;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private static function resultHasScore(?array $row): bool
    {
        if ($row === null) {
            return false;
        }
        if (isset($row['score']) && (float) $row['score'] > 0) {
            return true;
        }
        if (isset($row['total_score']) && (float) $row['total_score'] > 0) {
            return true;
        }

        $holds = self::holdsForDisplay($row['score_breakdown'] ?? null);
        foreach ($holds as $cell) {
            if (($cell['t'] ?? '') !== '' || ($cell['i'] ?? '') !== '') {
                return true;
            }
        }
        $decoded = self::decodeBreakdown($row['score_breakdown'] ?? null);
        if ($decoded !== null && array_key_exists('tiebreaker_poeng', $decoded) && $decoded['tiebreaker_poeng'] !== null && $decoded['tiebreaker_poeng'] !== '') {
            return true;
        }

        return false;
    }

    private static function tiebreakerFromBreakdown(mixed $scoreBreakdown): mixed
    {
        $decoded = self::decodeBreakdown($scoreBreakdown);
        if ($decoded === null || !array_key_exists('tiebreaker_poeng', $decoded)) {
            return null;
        }

        return TiebreakerLexicographic::normalizePayload($decoded['tiebreaker_poeng']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function scoreFromRow(array $row): ?float
    {
        if (isset($row['total_score']) && (float) $row['total_score'] > 0) {
            return (float) $row['total_score'];
        }
        if (isset($row['score']) && (float) $row['score'] > 0) {
            return (float) $row['score'];
        }
        $holds = self::holdsForDisplay($row['score_breakdown'] ?? null);
        $totals = self::totalsFromHolds(self::normalizeHoldsForSave(['h' => $holds]));

        return $totals['score'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeBreakdown(mixed $scoreBreakdown): ?array
    {
        if (is_array($scoreBreakdown)) {
            return $scoreBreakdown;
        }
        if (!is_string($scoreBreakdown) || trim($scoreBreakdown) === '') {
            return null;
        }
        $decoded = json_decode($scoreBreakdown, true);

        return is_array($decoded) ? $decoded : null;
    }
}
