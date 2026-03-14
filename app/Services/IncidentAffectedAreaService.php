<?php

namespace App\Services;

use App\Models\AffectedArea;
use App\Models\Household;
use App\Models\IncidentReport;
use Illuminate\Support\Facades\DB;

/**
 * IncidentAffectedAreaService
 *
 * Computes which households fall within an incident polygon (ray-casting algorithm),
 * then aggregates the affected counts per barangay and saves to affected_areas.
 */
class IncidentAffectedAreaService
{
    /**
     * Point-in-polygon ray casting algorithm.
     * GeoJSON uses [lng, lat] coordinate order.
     *
     * @param float $lat      Latitude of the point
     * @param float $lng      Longitude of the point
     * @param array $polygon  Array of [lng, lat] coordinate pairs
     * @return bool
     */
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $n = count($polygon);
        if ($n < 3) {
            return false;
        }

        $inside = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            // GeoJSON stores [lng, lat]
            $yi = $polygon[$i][1];
            $xi = $polygon[$i][0];
            $yj = $polygon[$j][1];
            $xj = $polygon[$j][0];

            if (($yi > $lat) !== ($yj > $lat)
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Compute affected areas for an incident polygon.
     * Checks every household with valid GPS coordinates against the polygon.
     *
     * @param string $polygonGeoJson  GeoJSON string of the affected area polygon
     * @return array{by_barangay: array, totals: array, excluded_count: int}
     */
    public static function computeFromGeoJson(string $polygonGeoJson): array
    {
        $geo = json_decode($polygonGeoJson, true);
        if (! $geo) {
            return ['by_barangay' => [], 'totals' => static::emptyTotals(), 'excluded_count' => 0];
        }

        // Extract coordinates from GeoJSON (supports Polygon and Feature wrapping)
        $coords = [];
        if (isset($geo['type'])) {
            if ($geo['type'] === 'Polygon') {
                $coords = $geo['coordinates'][0] ?? [];
            } elseif ($geo['type'] === 'Feature' && isset($geo['geometry'])) {
                $coords = $geo['geometry']['coordinates'][0] ?? [];
            }
        }

        if (empty($coords)) {
            return ['by_barangay' => [], 'totals' => static::emptyTotals(), 'excluded_count' => 0];
        }

        // Get all households with valid GPS
        $households = Household::with('barangay:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->whereBetween('latitude', [12.50, 13.20])
            ->whereBetween('longitude', [120.50, 121.20])
            ->get();

        $totalHouseholds = Household::count();
        $excludedCount   = $totalHouseholds - $households->count();

        $byBarangay = [];
        foreach ($households as $hh) {
            if (! static::pointInPolygon((float) $hh->latitude, (float) $hh->longitude, $coords)) {
                continue;
            }

            $bid = $hh->barangay_id;
            if (! isset($byBarangay[$bid])) {
                $byBarangay[$bid] = [
                    'barangay_id'         => $bid,
                    'barangay_name'       => $hh->barangay->name ?? '',
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

            $byBarangay[$bid]['affected_households']++;
            $byBarangay[$bid]['affected_population'] += (int) $hh->family_members;
            $byBarangay[$bid]['affected_pwd']        += (int) $hh->pwd_count;
            $byBarangay[$bid]['affected_seniors']    += (int) $hh->senior_count;
            $byBarangay[$bid]['affected_infants']    += (int) $hh->infant_count;
            $byBarangay[$bid]['affected_minors']     += (int) $hh->minor_count;
            $byBarangay[$bid]['affected_pregnant']   += (int) $hh->pregnant_count;
            if ($hh->ip_non_ip === 'IP') {
                $byBarangay[$bid]['ip_count']++;
            }
        }

        $totals = static::emptyTotals();
        foreach ($byBarangay as $row) {
            foreach ($totals as $key => &$val) {
                $val += $row[$key];
            }
        }

        return [
            'by_barangay'    => array_values($byBarangay),
            'totals'         => $totals,
            'excluded_count' => $excludedCount,
        ];
    }

    /**
     * Save computed affected areas to the database for the given incident.
     */
    public static function saveForIncident(IncidentReport $incident, array $byBarangay): void
    {
        // Remove old affected area records for this incident
        AffectedArea::where('incident_id', $incident->id)->delete();

        foreach ($byBarangay as $row) {
            AffectedArea::create([
                'incident_id'         => $incident->id,
                'barangay_id'         => $row['barangay_id'],
                'affected_households' => $row['affected_households'],
                'affected_population' => $row['affected_population'],
                'affected_pwd'        => $row['affected_pwd'],
                'affected_seniors'    => $row['affected_seniors'],
                'affected_infants'    => $row['affected_infants'],
                'affected_minors'     => $row['affected_minors'],
                'affected_pregnant'   => $row['affected_pregnant'],
                'ip_count'            => $row['ip_count'],
                'computed_at'         => now(),
            ]);
        }
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
