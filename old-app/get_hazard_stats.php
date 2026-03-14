<?php
// get_hazard_stats.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$barangayId = isset($_GET['barangay_id']) ? $_GET['barangay_id'] : 'all';

// Get hazard type IDs
$hazardTypes = $pdo->query("SELECT id, name FROM hazard_types")->fetchAll();
$hazardTypeIds = [];
foreach ($hazardTypes as $type) {
    $hazardTypeIds[$type['name']] = $type['id'];
}

// Function to get statistics
function getStats($pdo, $hazardTypeId, $barangayId) {
    $sql = "
        SELECT 
            risk_level,
            SUM(affected_population) as total_affected
        FROM hazard_zones 
        WHERE hazard_type_id = :hazard_type_id
    ";
    
    $params = [':hazard_type_id' => $hazardTypeId];
    
    if ($barangayId !== 'all') {
        $sql .= " AND barangay_id = :barangay_id";
        $params[':barangay_id'] = $barangayId;
    }
    
    $sql .= " GROUP BY risk_level";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[$row['risk_level']] = (int)$row['total_affected'];
    }
    
    return $results;
}

// Get stats for each hazard type
$response = [];

if (isset($hazardTypeIds['Flooding'])) {
    $response['Flooding'] = getStats($pdo, $hazardTypeIds['Flooding'], $barangayId);
}
if (isset($hazardTypeIds['Storm Surge'])) {
    $response['Storm Surge'] = getStats($pdo, $hazardTypeIds['Storm Surge'], $barangayId);
}
if (isset($hazardTypeIds['Tsunami'])) {
    $response['Tsunami'] = getStats($pdo, $hazardTypeIds['Tsunami'], $barangayId);
}
if (isset($hazardTypeIds['Liquefaction'])) {
    $response['Liquefaction'] = getStats($pdo, $hazardTypeIds['Liquefaction'], $barangayId);
}
if (isset($hazardTypeIds['Ground Shaking'])) {
    $response['Ground Shaking'] = getStats($pdo, $hazardTypeIds['Ground Shaking'], $barangayId);
}
if (isset($hazardTypeIds['Landslide'])) {
    $response['Landslide'] = getStats($pdo, $hazardTypeIds['Landslide'], $barangayId);
}

echo json_encode($response);
?>