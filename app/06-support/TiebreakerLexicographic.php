<?php

declare(strict_types=1);

namespace App\Support;

/** Normalisering og leksikografisk sammenligning av skillefigur-poeng. */
final class TiebreakerLexicographic
{
    public static function normalizePayload(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $x) {
                if ($x === null || $x === '') {
                    $out[] = null;
                    continue;
                }
                if (!is_numeric($x)) {
                    $out[] = null;
                    continue;
                }
                $out[] = (float) min(99, max(0, (int) round((float) $x)));
            }

            return $out === [] ? null : array_values($out);
        }
        if (!is_numeric($raw)) {
            return null;
        }

        return (float) min(99, max(0, (int) round((float) $raw)));
    }

    public static function compareDescending(mixed $a, mixed $b): int
    {
        $la = self::toComparableList($a);
        $lb = self::toComparableList($b);
        $n = max(count($la), count($lb));
        for ($i = 0; $i < $n; $i++) {
            $va = $la[$i] ?? null;
            $vb = $lb[$i] ?? null;
            $aNull = $va === null || $va === '';
            $bNull = $vb === null || $vb === '';
            if ($aNull && $bNull) {
                continue;
            }
            if ($aNull) {
                return 1;
            }
            if ($bNull) {
                return -1;
            }
            $c = ((float) $vb <=> (float) $va);
            if ($c !== 0) {
                return $c;
            }
        }

        return 0;
    }

    /** @return list<float|null> */
    private static function toComparableList(mixed $v): array
    {
        if ($v === null || $v === '') {
            return [];
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $x) {
                if ($x === null || $x === '') {
                    $out[] = null;
                } elseif (is_numeric($x)) {
                    $out[] = (float) $x;
                } else {
                    $out[] = null;
                }
            }

            return $out;
        }
        if (is_numeric($v)) {
            return [(float) $v];
        }

        return [];
    }
}
