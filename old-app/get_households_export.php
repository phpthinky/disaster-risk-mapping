<?php
// get_households_export.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Build query conditions
$conditions = ["1=1"];
$params = [];

// Barangay filter
if (!empty($data['barangayFilter'])) {
    $conditions[] = "h.barangay_id = ?";
    $params[] = $data['barangayFilter'];
}

// Date range filter
if (!empty($data['dateFrom'])) {
    $conditions[] = "DATE(h.created_at) >= ?";
    $params[] = $data['dateFrom'];
}
if (!empty($data['dateTo'])) {
    $conditions[] = "DATE(h.created_at) <= ?";
    $params[] = $data['dateTo'];
}

// Get all households with their data
$query = "
    SELECT 
        h.*,
        b.name as barangay_name,
        b.id as barangay_id
    FROM households h
    JOIN barangays b ON h.barangay_id = b.id
    WHERE " . implode(" AND ", $conditions) . "
    ORDER BY b.name, h.zone, h.household_head
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$households = $stmt->fetchAll(PDO::FETCH_ASSOC);

$exportData = [];
$summary = [
    'total_households' => count($households),
    'total_members' => 0,
    'total_pwd' => 0,
    'total_pregnant' => 0,
    'total_senior' => 0,
    'total_infant' => 0,
    'total_minor' => 0,
    'total_vulnerable' => 0,
    'barangays_covered' => 0,
    'hazard_summary' => []
];

$barangaysCovered = [];
$hazardStats = [];

foreach ($households as $household) {
    $barangaysCovered[$household['barangay_id']] = $household['barangay_name'];
    
    // Get household members
    $memberStmt = $pdo->prepare("SELECT * FROM household_members WHERE household_id = ?");
    $memberStmt->execute([$household['id']]);
    $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate vulnerability summary
    $totalVulnerable = 0;
    foreach ($members as $member) {
        if ($member['is_pwd']) $totalVulnerable++;
        if ($member['is_pregnant']) $totalVulnerable++;
        if ($member['is_senior']) $totalVulnerable++;
        if ($member['is_infant']) $totalVulnerable++;
        if ($member['is_minor']) $totalVulnerable++;
    }
    
    // Update summary totals
    $summary['total_members'] += intval($household['family_members'] ?? 1);
    $summary['total_pwd'] += intval($household['pwd_count'] ?? 0);
    $summary['total_pregnant'] += intval($household['pregnant_count'] ?? 0);
    $summary['total_senior'] += intval($household['senior_count'] ?? 0);
    $summary['total_infant'] += intval($household['infant_count'] ?? 0);
    $summary['total_minor'] += intval($household['minor_count'] ?? 0);
    $summary['total_vulnerable'] += $totalVulnerable;
    
    // Get hazard risks for this household
    $hazards = [];
    if ($data['includeHazards'] && $household['latitude'] && $household['longitude']) {
        $hazardQuery = "
            SELECT 
                hz.*,
                ht.name as hazard_name,
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
            WHERE hz.barangay_id = ?
                AND hz.coordinates IS NOT NULL
            HAVING distance_km <= radius_km * 1.5
            ORDER BY distance_km
        ";
        
        $hazardStmt = $pdo->prepare($hazardQuery);
        $hazardStmt->execute([
            $household['latitude'], 
            $household['longitude'], 
            $household['latitude'], 
            $household['barangay_id']
        ]);
        $hazards = $hazardStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update hazard statistics
        foreach ($hazards as $h) {
            $key = $h['hazard_name'] . '|' . $h['risk_level'];
            if (!isset($hazardStats[$key])) {
                $hazardStats[$key] = [
                    'type' => $h['hazard_name'],
                    'risk_level' => $h['risk_level'],
                    'count' => 0
                ];
            }
            $hazardStats[$key]['count']++;
        }
    }
    
    // Apply risk filter if specified
    if (!empty($data['riskFilter']) && !empty($hazards)) {
        $hasHighRisk = false;
        $hasModerateRisk = false;
        $hasLowRisk = false;
        
        foreach ($hazards as $h) {
            if (strpos($h['risk_level'], 'High') !== false || $h['risk_level'] == 'Prone') {
                $hasHighRisk = true;
            } elseif (strpos($h['risk_level'], 'Moderate') !== false) {
                $hasModerateRisk = true;
            } elseif (strpos($h['risk_level'], 'Low') !== false) {
                $hasLowRisk = true;
            }
        }
        
        if ($data['riskFilter'] === 'High' && !$hasHighRisk) continue;
        if ($data['riskFilter'] === 'Moderate' && !$hasModerateRisk) continue;
        if ($data['riskFilter'] === 'Low' && !$hasLowRisk) continue;
    }
    
    $exportData[] = [
        'id' => $household['id'],
        'barangay_name' => $household['barangay_name'],
        'zone' => $household['zone'],
        'household_head' => $household['household_head'],
        'age' => $household['age'],
        'gender' => $household['gender'],
        'house_type' => $household['house_type'],
        'family_members' => $household['family_members'],
        'pwd_count' => $household['pwd_count'],
        'pregnant_count' => $household['pregnant_count'],
        'senior_count' => $household['senior_count'],
        'infant_count' => $household['infant_count'],
        'minor_count' => $household['minor_count'],
        'latitude' => $household['latitude'],
        'longitude' => $household['longitude'],
        'created_at' => $household['created_at'],
        'members' => $members,
        'hazards' => $hazards,
        'total_vulnerable' => $totalVulnerable
    ];
}

// Update summary
$summary['barangays_covered'] = count($barangaysCovered);
$summary['hazard_summary'] = array_values($hazardStats);

echo json_encode([
    'success' => true,
    'households' => $exportData,
    'summary' => $summary,
    'message' => 'Data exported successfully'
]);
?>