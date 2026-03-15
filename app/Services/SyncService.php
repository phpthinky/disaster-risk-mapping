<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HazardZone;
use App\Models\PopulationData;
use App\Models\PopulationDataArchive;
use App\Services\GeoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SyncService — Auto-computation engine
 *
 * GOLDEN RULE: No population number is manually typed.
 * All counts are computed from the households table.
 *
 * Call handleSync($barangay_id) after EVERY household insert / update / delete.
 */
class SyncService
{
    /**
     * Master sync — call this after every household change.
     * Executes in strict order:
     *   1. syncBarangay
     *   2. syncPopulationData
     *   3. syncHazardZones
     */
    public static function handleSync(int $barangayId): void
    {
        static::syncBarangay($barangayId);
        static::syncPopulationData($barangayId);
        static::syncHazardZones($barangayId);
    }

    /**
     * Recompute household family composition counts from household_members.
     * Called after every member add/edit/delete.
     *
     * Age-based classification:
     *   Infant: 0-2, Child: 3-5, Minor: 3-12, Adolescent: 13-17,
     *   Young Adult: 18-24, Adult: 25-44, Middle Aged: 45-59, Senior: 60+
     *
     * @return int The barangay_id of the household (for chaining to handleSync)
     */
    public static function recomputeHousehold(int $householdId): int
    {
        $household = Household::find($householdId);
        if (! $household) {
            return 0;
        }

        $members = HouseholdMember::where('household_id', $householdId)->get();

        // Total = household head + all members
        $familyMembers = 1 + $members->count();

        $pwdCount       = 0;
        $pregnantCount  = 0;
        $seniorCount    = 0;   // 60+
        $infantCount    = 0;   // 0-2
        $minorCount     = 0;   // 3-12
        $childCount     = 0;   // 3-5
        $adolescentCount= 0;   // 13-17
        $youngAdultCount= 0;   // 18-24
        $adultCount     = 0;   // 25-44
        $middleAgedCount= 0;   // 45-59
        $ipCount        = 0;   // indigenous people (head + members with is_ip)

        foreach ($members as $m) {
            $age = (int) $m->age;

            if ($m->is_pwd) $pwdCount++;
            if ($m->is_pregnant) $pregnantCount++;
            if ($m->is_ip) $ipCount++;

            if ($age <= 2) {
                $infantCount++;
            } elseif ($age <= 5) {
                $childCount++;
                $minorCount++;
            } elseif ($age <= 12) {
                $minorCount++;
            } elseif ($age <= 17) {
                $adolescentCount++;
            } elseif ($age <= 24) {
                $youngAdultCount++;
            } elseif ($age <= 44) {
                $adultCount++;
            } elseif ($age <= 59) {
                $middleAgedCount++;
            } else {
                $seniorCount++;
            }
        }

        // Count head as IP if household is classified IP
        if ($household->ip_non_ip === 'IP') {
            $ipCount++;
        }

        // Also classify the household head by age
        $headAge = (int) $household->age;
        if ($headAge <= 2) $infantCount++;
        elseif ($headAge <= 5) { $childCount++; $minorCount++; }
        elseif ($headAge <= 12) $minorCount++;
        elseif ($headAge <= 17) $adolescentCount++;
        elseif ($headAge <= 24) $youngAdultCount++;
        elseif ($headAge <= 44) $adultCount++;
        elseif ($headAge <= 59) $middleAgedCount++;
        else $seniorCount++;

        // Update household without triggering observer loop
        Household::withoutEvents(function () use (
            $household, $familyMembers, $pwdCount, $pregnantCount, $seniorCount,
            $infantCount, $minorCount, $childCount, $adolescentCount,
            $youngAdultCount, $adultCount, $middleAgedCount, $ipCount
        ) {
            $household->update([
                'family_members'    => $familyMembers,
                'pwd_count'         => $pwdCount,
                'pregnant_count'    => $pregnantCount,
                'senior_count'      => $seniorCount,
                'infant_count'      => $infantCount,
                'minor_count'       => $minorCount,
                'child_count'       => $childCount,
                'adolescent_count'  => $adolescentCount,
                'young_adult_count' => $youngAdultCount,
                'adult_count'       => $adultCount,
                'middle_aged_count' => $middleAgedCount,
                'ip_count'          => $ipCount,
            ]);
        });

        return (int) $household->barangay_id;
    }

    /**
     * Recompute barangay aggregate counts from all its households.
     */
    public static function syncBarangay(int $barangayId): void
    {
        $row = DB::selectOne("
            SELECT
                COALESCE(SUM(family_members), 0) AS total_population,
                COUNT(id)                         AS household_count,
                COALESCE(SUM(pwd_count), 0)       AS pwd_count,
                COALESCE(SUM(senior_count), 0)    AS senior_count,
                COALESCE(SUM(minor_count) + SUM(child_count), 0) AS children_count,
                COALESCE(SUM(infant_count), 0)    AS infant_count,
                COALESCE(SUM(pregnant_count), 0)  AS pregnant_count,
                COALESCE(SUM(ip_count), 0)                AS ip_count
            FROM households
            WHERE barangay_id = ?
        ", [$barangayId]);

        Barangay::where('id', $barangayId)->update([
            'population'     => $row->total_population,
            'household_count'=> $row->household_count,
            'pwd_count'      => $row->pwd_count,
            'senior_count'   => $row->senior_count,
            'children_count' => $row->children_count,
            'infant_count'   => $row->infant_count,
            'pregnant_count' => $row->pregnant_count,
            'ip_count'       => $row->ip_count,
        ]);
    }

    /**
     * Sync population_data table from computed barangay totals.
     * Archives the existing record before overwriting.
     */
    public static function syncPopulationData(int $barangayId): void
    {
        $barangay = Barangay::find($barangayId);
        if (! $barangay) {
            return;
        }

        $archivedBy = Auth::check() ? Auth::user()->username : 'system_sync';

        $existing = PopulationData::where('barangay_id', $barangayId)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            // Archive old record
            PopulationDataArchive::create([
                'original_id'       => $existing->id,
                'barangay_id'       => $existing->barangay_id,
                'total_population'  => $existing->total_population ?? 0,
                'households'        => $existing->households ?? 0,
                'elderly_count'     => $existing->elderly_count ?? 0,
                'children_count'    => $existing->children_count ?? 0,
                'pwd_count'         => $existing->pwd_count ?? 0,
                'ips_count'         => $existing->ips_count ?? 0,
                'solo_parent_count' => $existing->solo_parent_count ?? 0,
                'widow_count'       => $existing->widow_count ?? 0,
                'data_date'         => $existing->data_date ?? now()->toDateString(),
                'archived_by'       => $archivedBy,
                'change_type'       => 'UPDATE',
                'snapshot_type'     => 'auto',
            ]);

            $existing->update([
                'total_population' => $barangay->population,
                'households'       => $barangay->household_count,
                'elderly_count'    => $barangay->senior_count,
                'children_count'   => $barangay->children_count,
                'pwd_count'        => $barangay->pwd_count,
                'ips_count'        => $barangay->ip_count,
                'data_date'        => now()->toDateString(),
                'entered_by'       => 'system_sync',
            ]);
        } else {
            PopulationData::create([
                'barangay_id'      => $barangayId,
                'total_population' => $barangay->population,
                'households'       => $barangay->household_count,
                'elderly_count'    => $barangay->senior_count,
                'children_count'   => $barangay->children_count,
                'pwd_count'        => $barangay->pwd_count,
                'ips_count'        => $barangay->ip_count,
                'data_date'        => now()->toDateString(),
                'entered_by'       => 'system_sync',
            ]);
        }
    }

    /**
     * Recompute hazard_zones.affected_population for all zones in a barangay.
     *
     * For each zone that has a GeoJSON polygon in the `coordinates` column,
     * count only the households whose GPS point falls inside that polygon.
     * Zones without a polygon are set to 0.
     *
     * This replaces the old approach of copying barangay.population to every
     * zone regardless of spatial overlap.
     */
    public static function syncHazardZones(int $barangayId): void
    {
        $zones = HazardZone::where('barangay_id', $barangayId)->get();

        if ($zones->isEmpty()) {
            return;
        }

        // Load all geo-tagged households for this barangay once
        $households = Household::where('barangay_id', $barangayId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude', 'family_members']);

        foreach ($zones as $zone) {
            $affected = 0;
            $ring     = GeoService::outerRing($zone->coordinates);

            if ($ring !== null) {
                foreach ($households as $h) {
                    if (GeoService::pointInPolygon(
                        (float) $h->latitude,
                        (float) $h->longitude,
                        $ring
                    )) {
                        $affected += (int) ($h->family_members ?? 1);
                    }
                }
            }

            $zone->affected_population = $affected;
            $zone->save();
        }
    }
}
