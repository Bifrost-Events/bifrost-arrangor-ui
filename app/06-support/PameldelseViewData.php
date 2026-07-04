<?php

declare(strict_types=1);

namespace App\Support;

final class PameldelseViewData
{
    /**
     * @param array{ok: bool, data: array<string, mixed>|null} $roster
     * @param array<string, mixed> $competition
     * @return array{
     *   slots: list<array<string, mixed>>,
     *   figures_per_slot: int,
     *   reserved_set: array<string, true>,
     *   occupant_by_key: array<string, array{name: string, participant_id: int}>
     * }
     */
    public static function build(array $roster, array $competition): array
    {
        $data = is_array($roster['data'] ?? null) ? $roster['data'] : [];
        $slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $registrations = is_array($data['registrations'] ?? null) ? $data['registrations'] : [];
        $reserved = is_array($data['reserved'] ?? null) ? $data['reserved'] : [];

        $figuresPerSlot = max(1, (int) (
            $competition['antall_skyttere_per_lag']
            ?? $competition['shooters_per_slot']
            ?? 6
        ));

        $reservedSet = [];
        foreach ($reserved as $rp) {
            if (!is_array($rp)) {
                continue;
            }
            $sn = (int) ($rp['slot_number'] ?? 0);
            $fn = (int) ($rp['figure_number'] ?? 0);
            if ($sn > 0 && $fn > 0) {
                $reservedSet[$sn . '_' . $fn] = true;
            }
        }

        $occupantByKey = [];
        foreach ($registrations as $r) {
            if (!is_array($r)) {
                continue;
            }
            $sid = (int) ($r['slot_id'] ?? 0);
            $fn = (int) ($r['figure_number'] ?? 0);
            $pid = (int) ($r['participant_id'] ?? 0);
            if ($sid < 1 || $fn < 1 || $pid < 1) {
                continue;
            }
            $name = trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''));
            $occupantByKey[$sid . '_' . $fn] = [
                'name' => $name !== '' ? $name : 'Deltaker #' . $pid,
                'participant_id' => $pid,
                'slot_number' => (int) ($r['slot_number'] ?? 0),
                'figure_number' => $fn,
            ];
        }

        return [
            'slots' => $slots,
            'figures_per_slot' => $figuresPerSlot,
            'reserved_set' => $reservedSet,
            'occupant_by_key' => $occupantByKey,
        ];
    }
}
