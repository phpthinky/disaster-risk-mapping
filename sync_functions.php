<?php
/**
 * sync_functions.php — Auto-computation engine
 *
 * GOLDEN RULE: No population number is manually typed.
 * All counts are computed from the households table.
 *
 * Call handle_sync($pdo, $barangay_id) after EVERY
 * household insert / update / delete.
 */

/**
 * Recompute barangay aggregate counts from households.
 */
function sync_barangay(PDO $pdo, int $barangay_id): void
{
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(family_members), 0) AS total_population,
            COUNT(id)                         AS household_count,
            COALESCE(SUM(pwd_count), 0)       AS pwd_count,
            COALESCE(SUM(senior_count), 0)    AS senior_count,
            COALESCE(SUM(minor_count) + SUM(child_count), 0) AS children_count,
            COALESCE(SUM(infant_count), 0)    AS infant_count,
            COALESCE(SUM(pregnant_count), 0)  AS pregnant_count,
            COALESCE(SUM(CASE WHEN ip_non_ip = 'IP' THEN 1 ELSE 0 END), 0) AS ip_count
        FROM households
        WHERE barangay_id = ?
    ");
    $stmt->execute([$barangay_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $update = $pdo->prepare("
        UPDATE barangays SET
            population      = ?,
            household_count = ?,
            pwd_count       = ?,
            senior_count    = ?,
            children_count  = ?,
            infant_count    = ?,
            pregnant_count  = ?,
            ip_count        = ?
        WHERE id = ?
    ");
    $update->execute([
        $row['total_population'],
        $row['household_count'],
        $row['pwd_count'],
        $row['senior_count'],
        $row['children_count'],
        $row['infant_count'],
        $row['pregnant_count'],
        $row['ip_count'],
        $barangay_id
    ]);
}

/**
 * Sync population_data table from computed barangay totals.
 * Archives existing record before overwriting.
 */
function sync_population_data(PDO $pdo, int $barangay_id): void
{
    // Get current computed values from barangays
    $stmt = $pdo->prepare("
        SELECT population, household_count, pwd_count, senior_count,
               children_count, infant_count, pregnant_count, ip_count
        FROM barangays WHERE id = ?
    ");
    $stmt->execute([$barangay_id]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$b) return;

    // Check for existing population_data row
    $existing = $pdo->prepare("
        SELECT * FROM population_data
        WHERE barangay_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $existing->execute([$barangay_id]);
    $old = $existing->fetch(PDO::FETCH_ASSOC);

    // Archive old record if it exists
    if ($old) {
        $archive = $pdo->prepare("
            INSERT INTO population_data_archive
                (original_id, barangay_id, total_population, households,
                 elderly_count, children_count, pwd_count, ips_count,
                 solo_parent_count, widow_count, data_date, archived_by, change_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'system_sync', 'UPDATE')
        ");
        $archive->execute([
            $old['id'],
            $old['barangay_id'],
            $old['total_population'],
            $old['households'],
            $old['elderly_count'],
            $old['children_count'],
            $old['pwd_count'],
            $old['ips_count'] ?? 0,
            $old['solo_parent_count'] ?? 0,
            $old['widow_count'] ?? 0,
            $old['data_date'] ?? date('Y-m-d')
        ]);

        // Update existing row
        $update = $pdo->prepare("
            UPDATE population_data SET
                total_population = ?,
                households       = ?,
                elderly_count    = ?,
                children_count   = ?,
                pwd_count        = ?,
                ips_count        = ?,
                data_date        = CURDATE(),
                entered_by       = 'system_sync'
            WHERE id = ?
        ");
        $update->execute([
            $b['population'],
            $b['household_count'],
            $b['senior_count'],
            $b['children_count'],
            $b['pwd_count'],
            $b['ip_count'],
            $old['id']
        ]);
    } else {
        // Insert new row
        $insert = $pdo->prepare("
            INSERT INTO population_data
                (barangay_id, total_population, households, elderly_count,
                 children_count, pwd_count, ips_count, data_date, entered_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'system_sync')
        ");
        $insert->execute([
            $barangay_id,
            $b['population'],
            $b['household_count'],
            $b['senior_count'],
            $b['children_count'],
            $b['pwd_count'],
            $b['ip_count']
        ]);
    }
}

/**
 * Update hazard_zones.affected_population from barangays.population
 * for all zones linked to the given barangay.
 */
function sync_hazard_zones(PDO $pdo, int $barangay_id): void
{
    $stmt = $pdo->prepare("
        UPDATE hazard_zones
        SET affected_population = (
            SELECT population FROM barangays WHERE id = ?
        ),
        updated_at = NOW()
        WHERE barangay_id = ?
    ");
    $stmt->execute([$barangay_id, $barangay_id]);
}

/**
 * Master sync — call this after every household change.
 * Executes in strict order:
 *   1. sync_barangay
 *   2. sync_population_data
 *   3. sync_hazard_zones
 */
function handle_sync(PDO $pdo, int $barangay_id): void
{
    sync_barangay($pdo, $barangay_id);
    sync_population_data($pdo, $barangay_id);
    sync_hazard_zones($pdo, $barangay_id);
}

/**
 * Point-in-polygon ray casting algorithm.
 * Used by incident reports to determine which households
 * fall inside a drawn polygon.
 *
 * @param float $lat Latitude of the point
 * @param float $lng Longitude of the point
 * @param array $polygon Array of [lng, lat] coordinate pairs (GeoJSON order)
 * @return bool True if point is inside the polygon
 */
function point_in_polygon(float $lat, float $lng, array $polygon): bool
{
    $n = count($polygon);
    if ($n < 3) return false;

    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        // GeoJSON stores [lng, lat]
        $yi = $polygon[$i][1];
        $xi = $polygon[$i][0];
        $yj = $polygon[$j][1];
        $xj = $polygon[$j][0];

        if (($yi > $lat) !== ($yj > $lat)
            && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/**
 * Compute affected areas for an incident report polygon.
 * Checks every household with valid GPS against the polygon.
 * Returns results grouped by barangay.
 *
 * @param PDO $pdo Database connection
 * @param string $polygonGeoJson GeoJSON string of the affected area polygon
 * @return array ['by_barangay' => [...], 'totals' => [...], 'excluded_count' => int]
 */
function compute_affected_areas(PDO $pdo, string $polygonGeoJson): array
{
    $geo = json_decode($polygonGeoJson, true);
    if (!$geo) return ['by_barangay' => [], 'totals' => [], 'excluded_count' => 0];

    // Extract coordinates from GeoJSON (supports Polygon and Feature)
    $coords = [];
    if (isset($geo['type'])) {
        if ($geo['type'] === 'Polygon') {
            $coords = $geo['coordinates'][0] ?? [];
        } elseif ($geo['type'] === 'Feature' && isset($geo['geometry'])) {
            $coords = $geo['geometry']['coordinates'][0] ?? [];
        }
    }
    if (empty($coords)) return ['by_barangay' => [], 'totals' => [], 'excluded_count' => 0];

    // Get all households with valid GPS
    $stmt = $pdo->query("
        SELECT h.*, b.name AS barangay_name
        FROM households h
        JOIN barangays b ON h.barangay_id = b.id
        WHERE h.latitude IS NOT NULL
          AND h.longitude IS NOT NULL
          AND h.latitude != 0
          AND h.longitude != 0
          AND h.latitude BETWEEN 12.50 AND 13.20
          AND h.longitude BETWEEN 120.50 AND 121.20
    ");
    $households = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count excluded households (invalid GPS)
    $totalHouseholds = $pdo->query("SELECT COUNT(*) FROM households")->fetchColumn();
    $excludedCount = $totalHouseholds - count($households);

    // Check each household against the polygon
    $byBarangay = [];
    foreach ($households as $hh) {
        if (!point_in_polygon((float)$hh['latitude'], (float)$hh['longitude'], $coords)) {
            continue;
        }

        $bid = $hh['barangay_id'];
        if (!isset($byBarangay[$bid])) {
            $byBarangay[$bid] = [
                'barangay_id'          => $bid,
                'barangay_name'        => $hh['barangay_name'],
                'affected_households'  => 0,
                'affected_population'  => 0,
                'affected_pwd'         => 0,
                'affected_seniors'     => 0,
                'affected_infants'     => 0,
                'affected_minors'      => 0,
                'affected_pregnant'    => 0,
                'ip_count'             => 0,
            ];
        }

        $byBarangay[$bid]['affected_households']++;
        $byBarangay[$bid]['affected_population'] += (int)$hh['family_members'];
        $byBarangay[$bid]['affected_pwd']        += (int)$hh['pwd_count'];
        $byBarangay[$bid]['affected_seniors']    += (int)$hh['senior_count'];
        $byBarangay[$bid]['affected_infants']    += (int)$hh['infant_count'];
        $byBarangay[$bid]['affected_minors']     += (int)$hh['minor_count'];
        $byBarangay[$bid]['affected_pregnant']   += (int)$hh['pregnant_count'];
        if ($hh['ip_non_ip'] === 'IP') {
            $byBarangay[$bid]['ip_count']++;
        }
    }

    // Compute totals
    $totals = [
        'affected_households' => 0,
        'affected_population' => 0,
        'affected_pwd'        => 0,
        'affected_seniors'    => 0,
        'affected_infants'    => 0,
        'affected_minors'     => 0,
        'affected_pregnant'   => 0,
        'ip_count'            => 0,
    ];
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
