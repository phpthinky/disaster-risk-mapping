<?php
// get_household_hazards.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$householdId = $data['household_id'] ?? 0;
$latitude = $data['latitude'] ?? 0;
$longitude = $data['longitude'] ?? 0;
$barangayId = $data['barangay_id'] ?? 0;

if (!$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Household has no coordinates']);
    exit;
}

// Get barangay name
$barangayStmt = $pdo->prepare("SELECT name FROM barangays WHERE id = ?");
$barangayStmt->execute([$barangayId]);
$barangayName = $barangayStmt->fetchColumn();

// Get all hazard zones in the same barangay
$hazardQuery = "
    SELECT 
        hz.*,
        ht.name as hazard_name,
        b.name as barangay_name,
        (
            6371 * ACOS(
                COS(RADIANS(?)) * 
                COS(RADIANS(SUBSTRING_INDEX(hz.coordinates, ',', 1))) * 
                COS(RADIANS(SUBSTRING_INDEX(hz.coordinates, ',', -1)) - RADIANS(?)) + 
                SIN(RADIANS(?)) * 
                SIN(RADIANS(SUBSTRING_INDEX(hz.coordinates, ',', 1)))
            )
        ) AS distance_km,
        SQRT(hz.area_km2 / PI()) AS radius_km
    FROM hazard_zones hz
    JOIN hazard_types ht ON hz.hazard_type_id = ht.id
    JOIN barangays b ON hz.barangay_id = b.id
    WHERE hz.barangay_id = ?
        AND hz.coordinates IS NOT NULL
        AND hz.coordinates != ''
    ORDER BY distance_km
";

$stmt = $pdo->prepare($hazardQuery);
$stmt->execute([$latitude, $longitude, $latitude, $barangayId]);
$hazards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$summary = [
    'total_hazards' => count($hazards),
    'high_risk_count' => 0,
    'within_hazard_count' => 0,
    'closest_hazard' => null
];

$closestDistance = PHP_FLOAT_MAX;
$overallRisk = ['level' => 'Low', 'color' => 'success'];

foreach ($hazards as &$hazard) {
    // Check if within hazard zone (distance <= radius)
    $hazard['within_zone'] = $hazard['distance_km'] <= $hazard['radius_km'];
    
    if ($hazard['within_zone']) {
        $summary['within_hazard_count']++;
    }
    
    // Count high risk hazards
    if (strpos($hazard['risk_level'], 'High') !== false || 
        $hazard['risk_level'] == 'Prone' ||
        strpos($hazard['risk_level'], 'PEIS VIII') !== false) {
        $summary['high_risk_count']++;
    }
    
    // Track closest hazard
    if ($hazard['distance_km'] < $closestDistance) {
        $closestDistance = $hazard['distance_km'];
        $summary['closest_hazard'] = $hazard['hazard_name'];
    }
}
unset($hazard);

// Determine overall risk level
if ($summary['high_risk_count'] > 0 && $summary['within_hazard_count'] > 0) {
    $overallRisk = ['level' => 'High Risk', 'color' => 'danger'];
} elseif ($summary['within_hazard_count'] > 0) {
    $overallRisk = ['level' => 'Moderate Risk', 'color' => 'warning'];
} elseif ($summary['high_risk_count'] > 0) {
    $overallRisk = ['level' => 'Low Risk', 'color' => 'info'];
}

echo json_encode([
    'success' => true,
    'hazards' => $hazards,
    'summary' => $summary,
    'overall_risk' => $overallRisk,
    'barangay_name' => $barangayName,
    'closest_distance' => $closestDistance < PHP_FLOAT_MAX ? round($closestDistance, 2) . ' km' : 'N/A',
    'message' => count($hazards) . ' hazards found in barangay'
]);
?>