<?php

namespace App\Services;

use App\Models\Household;

/**
 * Computes which households fall inside a drawn polygon and aggregates
 * the results per barangay using a ray-casting point-in-polygon check.
 */
class AffectedAreaService
{
    /**
     * Compute affected households for a given GeoJSON polygon string.
     *
     * Returns:
     *   [
     *     'by_barangay'    => [ ['barangay_id'=>int, 'barangay_name'=>string, 'affected_households'=>int, ...], ... ],
     *     'totals'         => [ 'affected_households'=>int, 'affected_population'=>int, ... ],
     *     'excluded_count' => int,   // households with no valid GPS
     *   ]
     */
    public static function compute(string $polygonGeoJson): array
    {
        $empty = [
            'by_barangay'    => [],
            'totals'         => self::emptyTotals(),
            'excluded_count' => 0,
        ];

        $ring = GeoService::outerRing($polygonGeoJson);
        if ($ring === null) {
            return $empty;
        }

        // All households with valid GPS within Sablayan bounds
        $validHouseholds = Household::with('barangay')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude',  '!=', 0)
            ->where('longitude', '!=', 0)
            ->whereBetween('latitude',  [12.50, 13.20])
            ->whereBetween('longitude', [120.50, 121.20])
            ->get();

        $totalAll    = Household::count();
        $excludedCount = $totalAll - $validHouseholds->count();

        $byBarangay = [];

        foreach ($validHouseholds as $hh) {
            if (! GeoService::pointInPolygon((float) $hh->latitude, (float) $hh->longitude, $ring)) {
                continue;
            }

            $bid = $hh->barangay_id;
            if (! isset($byBarangay[$bid])) {
                $byBarangay[$bid] = array_merge(
                    ['barangay_id' => $bid, 'barangay_name' => $hh->barangay?->name ?? '—'],
                    self::emptyTotals()
                );
            }

            $byBarangay[$bid]['affected_households']++;
            $byBarangay[$bid]['affected_population'] += (int) $hh->family_members;
            $byBarangay[$bid]['affected_pwd']        += (int) $hh->pwd_count;
            $byBarangay[$bid]['affected_seniors']    += (int) $hh->senior_count;
            $byBarangay[$bid]['affected_infants']    += (int) $hh->infant_count;
            $byBarangay[$bid]['affected_minors']     += (int) $hh->children_count;
            $byBarangay[$bid]['affected_pregnant']   += (int) $hh->pregnant_count;
            $byBarangay[$bid]['ip_count']            += (int) $hh->ip_count;
        }

        $rows = array_values($byBarangay);

        // Aggregate totals
        $totals = self::emptyTotals();
        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key] ?? 0;
            }
        }

        return [
            'by_barangay'    => $rows,
            'totals'         => $totals,
            'excluded_count' => $excludedCount,
        ];
    }

    private static function emptyTotals(): array
    {
        return [
            'affected_households' => 0,
            'affected_population' => 0,
            'affected_pwd'        => 0,
            'affected_seniors'    => 0,
            'affected_infants'    => 0,
            'affected_minors'     => 0,
            'affected_pregnant'   => 0,
            'ip_count'            => 0,
        ];
    }
}
