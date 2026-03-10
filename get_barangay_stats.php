<?php
// get_barangay_stats.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$barangayId = isset($_GET['barangay_id']) ? $_GET['barangay_id'] : 'all';

if ($barangayId === 'all') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hazard_zones");
    $result = $stmt->fetch();
    echo json_encode(['total_zones' => $result['total']]);
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM hazard_zones WHERE barangay_id = ?");
    $stmt->execute([$barangayId]);
    $result = $stmt->fetch();
    echo json_encode(['total_zones' => $result['total']]);
}
?>