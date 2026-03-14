<?php
// get_households.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$hazardId = $data['hazard_id'] ?? 0;
$hazardLat = $data['hazard_lat'] ?? 0;
$hazardLng = $data['hazard_lng'] ?? 0;
$radius = $data['radius'] ?? 1000; // meters (default)
$areaKm2 = $data['area_km2'] ?? 0;
$barangayName = $data['barangay'] ?? '';

if (!$hazardLat || !$hazardLng) {
    echo json_encode(['success' => false, 'message' => 'Invalid hazard coordinates']);
    exit;
}

// Calculate more accurate radius based on area shape
// If area is provided, calculate radius assuming circular area
// But note: actual hazard zones may be irregular shapes
if ($areaKm2 > 0) {
    // For a circle: Area = π * r²
    // So r = √(Area/π)
    $radius = sqrt($areaKm2 / M_PI) * 1000; // Convert to meters
    
    // Add a buffer of 10% to account for irregular shapes
    $radius = $radius * 1.1;
}

// Get barangay ID
$stmt = $pdo->prepare("SELECT id, name, coordinates FROM barangays WHERE name = ?");
$stmt->execute([$barangayName]);
$barangay = $stmt->fetch();

if (!$barangay) {
    echo json_encode(['success' => false, 'message' => 'Barangay not found']);
    exit;
}

$barangayId = $barangay['id'];

// First, get all households in the barangay with valid coordinates
$checkQuery = "SELECT COUNT(*) as total FROM households WHERE barangay_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0";
$checkStmt = $pdo->prepare($checkQuery);
$checkStmt->execute([$barangayId]);
$totalHouseholds = $checkStmt->fetch(PDO::FETCH_ASSOC);

// Find households within the hazard area using Haversine formula
// Convert radius from meters to kilometers for the calculation
$radiusKm = $radius / 1000;

$query = "
    SELECT 
        id,
        household_head,
        barangay_id,
        sex,
        age,
        gender,
        house_type,
        family_members,
        pwd_count,
        pregnant_count,
        senior_count,
        infant_count,
        minor_count,
        latitude,
        longitude,
        created_at,
        updated_at,
        (
            6371 * ACOS(
                COS(RADIANS(?)) * 
                COS(RADIANS(latitude)) * 
                COS(RADIANS(longitude) - RADIANS(?)) + 
                SIN(RADIANS(?)) * 
                SIN(RADIANS(latitude))
            )
        ) AS distance_km
    FROM households 
    WHERE barangay_id = ? 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
        AND latitude != 0 
        AND longitude != 0
    HAVING distance_km <= ?
    ORDER BY distance_km
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hazardLat, $hazardLng, $hazardLat, $barangayId, $radiusKm]);
    $households = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $summary = [
        'total_members' => 0,
        'total_pwd' => 0,
        'total_pregnant' => 0,
        'total_senior' => 0,
        'total_infant' => 0,
        'total_minor' => 0
    ];

    foreach ($households as $h) {
        $summary['total_members'] += intval($h['family_members'] ?? 0);
        $summary['total_pwd'] += intval($h['pwd_count'] ?? 0);
        $summary['total_pregnant'] += intval($h['pregnant_count'] ?? 0);
        $summary['total_senior'] += intval($h['senior_count'] ?? 0);
        $summary['total_infant'] += intval($h['infant_count'] ?? 0);
        $summary['total_minor'] += intval($h['minor_count'] ?? 0);
    }

    // Calculate the bounding box for debugging/information
    $bounds = [
        'min_lat' => $hazardLat - ($radiusKm / 111),
        'max_lat' => $hazardLat + ($radiusKm / 111),
        'min_lng' => $hazardLng - ($radiusKm / (111 * cos(deg2rad($hazardLat)))),
        'max_lng' => $hazardLng + ($radiusKm / (111 * cos(deg2rad($hazardLat))))
    ];

    echo json_encode([
        'success' => true,
        'households' => $households,
        'summary' => $summary,
        'total' => count($households),
        'radius_used' => round($radius, 2) . ' meters',
        'radius_km' => round($radiusKm, 2) . ' km',
        'bounds' => $bounds,
        'total_households_in_barangay' => $totalHouseholds['total'],
        'message' => count($households) . ' households found within ' . round($radiusKm, 2) . 'km radius'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>