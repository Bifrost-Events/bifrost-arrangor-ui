<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\PortalV3Session;

/**
 * Arbeidskontekst (hat) — navigasjon/filtrering, ikke sikkerhet alene.
 * Tilgang styres fortsatt av policy + canAdministerOrganization.
 */
final class PortalWorkContext
{
    public function __construct(
        private readonly PortalV3Services $services,
    ) {
    }

    /**
     * @param array<string, mixed> $space
     * @param array<string, mixed> $access PortalCupAccess::forSpace
     * @return list<array{key: string, mode: string, org_id: int, label: string, detail: string}>
     */
    public function options(array $space, array $access, int $personId): array
    {
        $options = [];
        $cupName = trim((string) ($space['name'] ?? 'Cup'));
        $cupOwner = (int) ($space['owner_org_id'] ?? 0);
        $orgNames = $this->orgNames($personId);

        if ($access['can_manage_cup'] ?? false) {
            $options[] = [
                'key' => 'cup',
                'mode' => PortalV3Session::WORK_MODE_CUP,
                'org_id' => $cupOwner,
                'label' => 'Cupadministrasjon',
                'detail' => $cupName,
            ];
        }

        $arrangerIds = $access['arranger_org_ids'] ?? [];
        // Cupadmin kan også «ta på seg» arrangør-hatt for orgs de adminer som arrangerer i cupen
        if ($access['can_manage_cup'] ?? false) {
            $candidateIds = [];
            $spaceId = (int) ($space['space_id'] ?? 0);
            if ($spaceId > 0) {
                foreach ($this->services->spaceParticipation->listHostOrganizationsInSpace($spaceId) as $host) {
                    $hid = (int) ($host['org_id'] ?? 0);
                    if ($hid > 0 && $hid !== $cupOwner) {
                        $candidateIds[] = $hid;
                    }
                }
                foreach ($this->services->spaceParticipation->listSeriesOrganizerOrganizationsInSpace($spaceId) as $org) {
                    $oid = (int) ($org['org_id'] ?? 0);
                    if ($oid > 0 && $oid !== $cupOwner) {
                        $candidateIds[] = $oid;
                    }
                }
            }
            $adminIds = $access['admin_org_ids'] ?? [];
            $arrangerIds = array_values(array_unique(array_merge(
                $arrangerIds,
                array_intersect($adminIds, $candidateIds),
            )));
        }

        $arrangerOptions = [];
        foreach ($arrangerIds as $oid) {
            $oid = (int) $oid;
            if ($oid <= 0 || $oid === $cupOwner) {
                continue;
            }
            $arrangerOptions[] = [
                'key' => 'arranger:' . $oid,
                'mode' => PortalV3Session::WORK_MODE_ARRANGER,
                'org_id' => $oid,
                'label' => 'Arrangør',
                'detail' => $orgNames[$oid] ?? ('Org #' . $oid),
            ];
        }

        // Samme organisasjonsnavn kan finnes på flere org_id (testdata) — vis id når navn kolliderer.
        $nameCounts = [];
        foreach ($arrangerOptions as $opt) {
            $n = (string) $opt['detail'];
            $nameCounts[$n] = ($nameCounts[$n] ?? 0) + 1;
        }
        foreach ($arrangerOptions as &$opt) {
            $n = (string) $opt['detail'];
            if (($nameCounts[$n] ?? 0) > 1) {
                $opt['detail'] = $n . ' (#' . (int) $opt['org_id'] . ')';
            }
        }
        unset($opt);

        return array_merge($options, $arrangerOptions);
    }

    /**
     * Synk ugyldig lagret hatt og returnér aktiv kontekst.
     *
     * @param array<string, mixed> $space
     * @param array<string, mixed> $access
     * @return array{
     *   mode: string,
     *   org_id: int,
     *   label: string,
     *   detail: string,
     *   key: string,
     *   options: list<array{key: string, mode: string, org_id: int, label: string, detail: string}>
     * }
     */
    public function resolve(int $personId, array $space, array $access): array
    {
        $options = $this->options($space, $access, $personId);
        if ($options === []) {
            return [
                'mode' => PortalV3Session::WORK_MODE_ARRANGER,
                'org_id' => (int) ($this->services->organizationContext->activeOrganizationId() ?? 0),
                'label' => 'Arbeidsområde',
                'detail' => '',
                'key' => '',
                'options' => [],
            ];
        }

        $byKey = [];
        foreach ($options as $opt) {
            $byKey[$opt['key']] = $opt;
        }

        $mode = PortalV3Session::getWorkMode();
        $orgId = (int) ($this->services->organizationContext->activeOrganizationId() ?? 0);
        $selected = null;

        if ($mode === PortalV3Session::WORK_MODE_CUP && isset($byKey['cup'])) {
            $selected = $byKey['cup'];
        } elseif ($mode === PortalV3Session::WORK_MODE_ARRANGER && $orgId > 0) {
            $key = 'arranger:' . $orgId;
            $selected = $byKey[$key] ?? null;
        }

        if ($selected === null) {
            // Prefer cup hvis tilgjengelig, ellers første arrangør
            $selected = $byKey['cup'] ?? $options[0];
        }

        $this->apply($personId, (string) $selected['mode'], (int) $selected['org_id'], false);

        return [
            'mode' => (string) $selected['mode'],
            'org_id' => (int) $selected['org_id'],
            'label' => (string) $selected['label'],
            'detail' => (string) $selected['detail'],
            'key' => (string) $selected['key'],
            'options' => $options,
        ];
    }

    public function apply(int $personId, string $mode, int $orgId, bool $validate = true): bool
    {
        if ($mode !== PortalV3Session::WORK_MODE_CUP && $mode !== PortalV3Session::WORK_MODE_ARRANGER) {
            return false;
        }
        if ($orgId <= 0) {
            return false;
        }
        if ($validate) {
            $orgs = $this->services->organizationContext->administrableOrganizations($personId);
            $ok = false;
            foreach ($orgs as $org) {
                if ((int) ($org['org_id'] ?? 0) === $orgId) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
        }

        // Behold space + season — kun hatt/org byttes.
        PortalV3Session::setWorkMode($mode);
        PortalV3Session::setOrganizationId($orgId);

        return true;
    }

    /**
     * Meny-/filterflags ut fra valgt hatt (capabilities begrenses i UI, ikke i policy).
     *
     * @param array<string, mixed> $access
     * @param array<string, mixed> $work
     * @return array<string, mixed>
     */
    public function menuAccess(array $access, array $work): array
    {
        $out = $access;
        if (($work['mode'] ?? '') === PortalV3Session::WORK_MODE_ARRANGER) {
            $out['can_manage_cup'] = false;
            $out['can_view_arrangers'] = false;
            $out['can_view_all_events'] = false;
            $filterOrg = (int) ($work['org_id'] ?? 0);
            $out['filter_org_ids'] = $filterOrg > 0 ? [$filterOrg] : [];
        } else {
            $out['filter_org_ids'] = [];
        }

        return $out;
    }

    /**
     * Org-id for Events API-list (cupadmin henter via cup-eier; filtrering skjer etterpå).
     *
     * @param array<string, mixed> $space
     * @param array<string, mixed> $access rå capability-access
     * @param array<string, mixed> $work
     */
    public function listOrgId(array $space, array $access, array $work): int
    {
        $cupOwner = (int) ($space['owner_org_id'] ?? 0);
        if (($access['can_manage_cup'] ?? false) && $cupOwner > 0) {
            return $cupOwner;
        }

        $workOrg = (int) ($work['org_id'] ?? 0);
        if ($workOrg > 0) {
            return $workOrg;
        }

        return (int) ($this->services->organizationContext->activeOrganizationId() ?? $cupOwner);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param array<string, mixed> $menuAccess
     * @param array<string, mixed> $access
     * @return list<array<string, mixed>>
     */
    public function filterEventsForWork(array $events, array $menuAccess, array $access): array
    {
        if ($menuAccess['can_view_all_events'] ?? false) {
            return $events;
        }

        $allowed = $menuAccess['filter_org_ids'] ?? [];
        if ($allowed === []) {
            $allowed = $access['arranger_org_ids'] ?? [];
        }
        $allowed = array_values(array_filter(array_map('intval', $allowed)));
        if ($allowed === []) {
            return [];
        }

        return array_values(array_filter(
            $events,
            static fn (array $e): bool => in_array((int) ($e['owner_org_id'] ?? 0), $allowed, true),
        ));
    }

    /**
     * Ved direkte åpning av stevne: sett hatt hvis entydig.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $space
     * @param array<string, mixed> $access
     */
    public function syncFromEvent(int $personId, array $event, array $space, array $access): void
    {
        $ownerOrgId = (int) ($event['owner_org_id'] ?? 0);
        $cupOwner = (int) ($space['owner_org_id'] ?? 0);
        $options = $this->options($space, $access, $personId);
        $arrangerKeys = array_values(array_filter(
            $options,
            static fn (array $o): bool => ($o['mode'] ?? '') === PortalV3Session::WORK_MODE_ARRANGER
                && (int) ($o['org_id'] ?? 0) === $ownerOrgId,
        ));

        if (count($arrangerKeys) === 1) {
            $this->apply($personId, PortalV3Session::WORK_MODE_ARRANGER, $ownerOrgId, false);

            return;
        }

        if (($access['can_manage_cup'] ?? false) && $ownerOrgId === $cupOwner) {
            $this->apply($personId, PortalV3Session::WORK_MODE_CUP, $cupOwner, false);
        }
    }

    /** @return array<int, string> */
    private function orgNames(int $personId): array
    {
        $names = [];
        foreach ($this->services->organizationContext->administrableOrganizations($personId) as $org) {
            $id = (int) ($org['org_id'] ?? 0);
            if ($id > 0) {
                $names[$id] = (string) ($org['name'] ?? ('#' . $id));
            }
        }

        return $names;
    }
}
