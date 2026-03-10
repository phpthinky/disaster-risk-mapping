<?php
// functions/population_functions.php
// Reusable population sync and computation functions
// Called after every household insert/update/delete and population_data changes

/**
 * Sync barangays.population from SUM of households.family_members
 */
function sync_barangay_population($pdo, $barangay_id) {
    $stmt = $pdo->prepare("
        UPDATE barangays
        SET population = (
            SELECT COALESCE(SUM(family_members), 0)
            FROM households
            WHERE barangay_id = ?
        )
        WHERE id = ?
    ");
    return $stmt->execute([$barangay_id, $barangay_id]);
}

/**
 * Compute and upsert population_data from households table aggregates.
 * Keeps manual entry as fallback only — if no households exist, data stays.
 */
function compute_population_data($pdo, $barangay_id) {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(family_members), 0)   AS total_population,
            COUNT(*)                             AS households_count,
            COALESCE(SUM(senior_count), 0)      AS elderly_count,
            COALESCE(SUM(minor_count), 0)       AS children_count,
            COALESCE(SUM(pwd_count), 0)         AS pwd_count,
            COALESCE(SUM(pregnant_count), 0)    AS pregnant_count,
            COALESCE(SUM(infant_count), 0)      AS infant_count,
            COUNT(CASE WHEN ip_non_ip = 'IP' THEN 1 END) AS ips_count
        FROM households
        WHERE barangay_id = ?
    ");
    $stmt->execute([$barangay_id]);
    $agg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)$agg['households_count'] === 0) {
        // No households yet — skip auto-compute, preserve manual entry
        return false;
    }

    // Check if population_data row already exists for this barangay
    $check = $pdo->prepare("SELECT id FROM population_data WHERE barangay_id = ? LIMIT 1");
    $check->execute([$barangay_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE population_data
            SET total_population = ?,
                households       = ?,
                elderly_count    = ?,
                children_count   = ?,
                pwd_count        = ?,
                ips_count        = ?,
                data_date        = CURDATE(),
                updated_at       = NOW()
            WHERE barangay_id = ?
        ");
        $stmt->execute([
            $agg['total_population'],
            $agg['households_count'],
            $agg['elderly_count'],
            $agg['children_count'],
            $agg['pwd_count'],
            $agg['ips_count'],
            $barangay_id
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO population_data
                (barangay_id, total_population, households, elderly_count, children_count, pwd_count, ips_count, data_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([
            $barangay_id,
            $agg['total_population'],
            $agg['households_count'],
            $agg['elderly_count'],
            $agg['children_count'],
            $agg['pwd_count'],
            $agg['ips_count']
        ]);
    }
    return true;
}

/**
 * Handle full population update propagation:
 * 1. Archives existing population_data before overwrite
 * 2. Syncs barangays.population
 * 3. Updates hazard_zones.affected_population for affected barangay
 */
function handle_population_update($pdo, $barangay_id, $archived_by = 'system') {
    // 1. Fetch current population_data for archive
    $stmt = $pdo->prepare("SELECT * FROM population_data WHERE barangay_id = ? LIMIT 1");
    $stmt->execute([$barangay_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current) {
        // Archive it before overwriting
        $arch = $pdo->prepare("
            INSERT INTO population_data_archive
                (original_id, barangay_id, total_population, households, elderly_count,
                 children_count, pwd_count, ips_count, solo_parent_count, widow_count,
                 data_date, archived_by, change_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UPDATE')
        ");
        $arch->execute([
            $current['id'],
            $current['barangay_id'],
            $current['total_population'],
            $current['households'] ?? 0,
            $current['elderly_count'] ?? 0,
            $current['children_count'] ?? 0,
            $current['pwd_count'] ?? 0,
            $current['ips_count'] ?? 0,
            $current['solo_parent_count'] ?? 0,
            $current['widow_count'] ?? 0,
            $current['data_date'] ?? date('Y-m-d'),
            $archived_by
        ]);
    }

    // 2. Sync barangays.population
    sync_barangay_population($pdo, $barangay_id);

    // 3. Update hazard_zones.affected_population from population_data for that barangay
    $pop_stmt = $pdo->prepare("
        SELECT total_population FROM population_data WHERE barangay_id = ? LIMIT 1
    ");
    $pop_stmt->execute([$barangay_id]);
    $pop_row = $pop_stmt->fetch(PDO::FETCH_ASSOC);

    if ($pop_row) {
        $update_hz = $pdo->prepare("
            UPDATE hazard_zones
            SET affected_population = ?
            WHERE barangay_id = ?
        ");
        $update_hz->execute([$pop_row['total_population'], $barangay_id]);
    }

    return true;
}

/**
 * Point-in-polygon ray casting algorithm (PHP, server-side).
 * Returns true if point ($lat, $lng) is inside the polygon defined by $polygon_points.
 * $polygon_points: array of [lat, lng] pairs (GeoJSON coordinates are [lng, lat])
 */
function point_in_polygon($lat, $lng, $polygon_points) {
    $n = count($polygon_points);
    if ($n < 3) return false;

    $inside = false;
    $j = $n - 1;

    for ($i = 0; $i < $n; $i++) {
        // GeoJSON format: [longitude, latitude]
        $xi = $polygon_points[$i][0]; // longitude
        $yi = $polygon_points[$i][1]; // latitude
        $xj = $polygon_points[$j][0];
        $yj = $polygon_points[$j][1];

        $intersect = (($yi > $lat) != ($yj > $lat))
            && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

        if ($intersect) $inside = !$inside;
        $j = $i;
    }

    return $inside;
}
