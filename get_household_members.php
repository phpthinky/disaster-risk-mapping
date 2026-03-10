<?php
// get_household_members.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$householdId = $_GET['household_id'] ?? 0;

if (!$householdId) {
    echo json_encode(['success' => false, 'message' => 'Household ID required']);
    exit;
}

// Get household info
$householdStmt = $pdo->prepare("
    SELECT h.*, b.name as barangay_name 
    FROM households h 
    JOIN barangays b ON h.barangay_id = b.id 
    WHERE h.id = ?
");
$householdStmt->execute([$householdId]);
$household = $householdStmt->fetch(PDO::FETCH_ASSOC);

if (!$household) {
    echo json_encode(['success' => false, 'message' => 'Household not found']);
    exit;
}

// Get members
$membersStmt = $pdo->prepare("
    SELECT * FROM household_members 
    WHERE household_id = ? 
    ORDER BY age DESC
");
$membersStmt->execute([$householdId]);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'members' => $members,
    'household' => $household,
    'total' => count($members)
]);
?>