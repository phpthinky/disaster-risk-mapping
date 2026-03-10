<?php
// core/sync.php — Master sync functions. Include after config.php.
// Call handle_sync($pdo, $barangay_id) after every household insert/update/delete.

/**
 * STEP 1: Sync all computed columns on barangays from households table.
 */
function sync_barangay($pdo, $barangay_id) {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(family_members), 0)                          AS population,
            COUNT(*)                                                   AS household_count,
            COALESCE(SUM(pwd_count), 0)                               AS pwd_count,
            COALESCE(SUM(senior_count), 0)                            AS senior_count,
            COALESCE(SUM(minor_count) + SUM(child_count), 0)          AS children_count,
            COALESCE(SUM(infant_count), 0)                            AS infant_count,
            COALESCE(SUM(pregnant_count), 0)                          AS pregnant_count,
            COUNT(CASE WHEN ip_non_ip = 'IP' THEN 1 END)              AS ip_count
        FROM households
        WHERE barangay_id = ?
    ");
    $stmt->execute([$barangay_id]);
    $agg = $stmt->fetch(PDO::FETCH_ASSOC);

    $upd = $pdo->prepare("
        UPDATE barangays SET
            population     = :population,
            household_count= :household_count,
            pwd_count      = :pwd_count,
            senior_count   = :senior_count,
            children_count = :children_count,
            infant_count   = :infant_count,
            pregnant_count = :pregnant_count,
            ip_count       = :ip_count
        WHERE id = :id
    ");
    $upd->execute([
        ':population'     => $agg['population'],
        ':household_count'=> $agg['household_count'],
        ':pwd_count'      => $agg['pwd_count'],
        ':senior_count'   => $agg['senior_count'],
        ':children_count' => $agg['children_count'],
        ':infant_count'   => $agg['infant_count'],
        ':pregnant_count' => $agg['pregnant_count'],
        ':ip_count'       => $agg['ip_count'],
        ':id'             => $barangay_id,
    ]);
}

/**
 * STEP 2: Archive old population_data then upsert computed summary from households.
 */
function sync_population_data($pdo, $barangay_id) {
    // Fetch current row for archive
    $cur = $pdo->prepare("SELECT * FROM population_data WHERE barangay_id = ? LIMIT 1");
    $cur->execute([$barangay_id]);
    $existing = $cur->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $arch = $pdo->prepare("
            INSERT INTO population_data_archive
                (original_id, barangay_id, total_population, households, elderly_count,
                 children_count, pwd_count, ips_count, solo_parent_count, widow_count,
                 data_date, archived_by, change_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'system', 'UPDATE')
        ");
        $arch->execute([
            $existing['id'],
            $existing['barangay_id'],
            $existing['total_population'] ?? 0,
            $existing['households'] ?? 0,
            $existing['elderly_count'] ?? 0,
            $existing['children_count'] ?? 0,
            $existing['pwd_count'] ?? 0,
            $existing['ips_count'] ?? 0,
            $existing['solo_parent_count'] ?? 0,
            $existing['widow_count'] ?? 0,
            $existing['data_date'] ?? date('Y-m-d'),
        ]);
    }

    // Compute aggregates from households
    $agg_stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(family_members), 0)                    AS total_population,
            COUNT(*)                                             AS households_count,
            COALESCE(SUM(senior_count), 0)                      AS elderly_count,
            COALESCE(SUM(minor_count) + SUM(child_count), 0)    AS children_count,
            COALESCE(SUM(pwd_count), 0)                         AS pwd_count,
            COUNT(CASE WHEN ip_non_ip = 'IP' THEN 1 END)        AS ips_count
        FROM households
        WHERE barangay_id = ?
    ");
    $agg_stmt->execute([$barangay_id]);
    $agg = $agg_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare("
            UPDATE population_data SET
                total_population = ?,
                households       = ?,
                elderly_count    = ?,
                children_count   = ?,
                pwd_count        = ?,
                ips_count        = ?,
                data_date        = CURDATE()
            WHERE barangay_id = ?
        ");
        $upd->execute([
            $agg['total_population'], $agg['households_count'],
            $agg['elderly_count'],    $agg['children_count'],
            $agg['pwd_count'],        $agg['ips_count'],
            $barangay_id,
        ]);
    } else {
        $ins = $pdo->prepare("
            INSERT INTO population_data
                (barangay_id, total_population, households, elderly_count, children_count, pwd_count, ips_count, data_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $ins->execute([
            $barangay_id,
            $agg['total_population'], $agg['households_count'],
            $agg['elderly_count'],    $agg['children_count'],
            $agg['pwd_count'],        $agg['ips_count'],
        ]);
    }
}

/**
 * STEP 3: Update hazard_zones.affected_population from barangays.population.
 */
function sync_hazard_zones($pdo, $barangay_id) {
    $pop = $pdo->prepare("SELECT population FROM barangays WHERE id = ? LIMIT 1");
    $pop->execute([$barangay_id]);
    $row = $pop->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    $upd = $pdo->prepare("
        UPDATE hazard_zones SET affected_population = ?
        WHERE barangay_id = ?
    ");
    $upd->execute([$row['population'], $barangay_id]);
}

/**
 * MASTER: Call this after every household insert/update/delete.
 * Runs all 3 steps in order.
 */
function handle_sync($pdo, $barangay_id) {
    sync_barangay($pdo, $barangay_id);
    sync_population_data($pdo, $barangay_id);
    sync_hazard_zones($pdo, $barangay_id);
}

/**
 * Point-in-polygon ray casting (PHP, server-side).
 * GeoJSON polygon coordinates are [longitude, latitude].
 */
function point_in_polygon($lat, $lng, $polygon_points) {
    $n = count($polygon_points);
    if ($n < 3) return false;

    $inside = false;
    $j = $n - 1;
    for ($i = 0; $i < $n; $i++) {
        $xi = $polygon_points[$i][0]; // lng
        $yi = $polygon_points[$i][1]; // lat
        $xj = $polygon_points[$j][0];
        $yj = $polygon_points[$j][1];

        if ((($yi > $lat) !== ($yj > $lat)) &&
            ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
            $inside = !$inside;
        }
        $j = $i;
    }
    return $inside;
}

/**
 * Validate GPS coordinates are within system bounds.
 */
function is_valid_gps($lat, $lng) {
    if (!defined('GPS_LAT_MIN')) return false;
    $lat = (float)$lat;
    $lng = (float)$lng;
    return ($lat >= GPS_LAT_MIN && $lat <= GPS_LAT_MAX &&
            $lng >= GPS_LNG_MIN && $lng <= GPS_LNG_MAX);
}

/**
 * Require login. Pass allowed roles array or empty for any logged-in user.
 */
function require_auth($allowed_roles = []) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_PATH . '/../login.php');
        exit;
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        die('<h3>Access Denied</h3>');
    }
}
