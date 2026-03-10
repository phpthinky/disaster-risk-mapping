<?php
/**
 * database/run_migrations.php
 * Run this once via browser to apply all pending schema changes.
 * Admin only — delete or restrict after use on production.
 */
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('<h3>Admin access required.</h3>');
}

$results = [];

$migrations = [
    'Add barangays.pwd_count'       => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS pwd_count INT DEFAULT 0",
    'Add barangays.senior_count'    => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS senior_count INT DEFAULT 0",
    'Add barangays.children_count'  => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS children_count INT DEFAULT 0",
    'Add barangays.infant_count'    => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS infant_count INT DEFAULT 0",
    'Add barangays.pregnant_count'  => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS pregnant_count INT DEFAULT 0",
    'Add barangays.ip_count'        => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS ip_count INT DEFAULT 0",
    'Add barangays.household_count' => "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS household_count INT DEFAULT 0",
    'Add barangays.boundary_geojson'=> "ALTER TABLE barangays ADD COLUMN IF NOT EXISTS boundary_geojson LONGTEXT DEFAULT NULL",
    'Add hazard_zones.polygon_geojson' => "ALTER TABLE hazard_zones ADD COLUMN IF NOT EXISTS polygon_geojson LONGTEXT DEFAULT NULL",
    'Create incident_reports table' => "CREATE TABLE IF NOT EXISTS incident_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        hazard_type_id INT DEFAULT NULL,
        incident_date DATE NOT NULL,
        status ENUM('ongoing','resolved','monitoring') DEFAULT 'ongoing',
        polygon_geojson LONGTEXT DEFAULT NULL,
        description TEXT DEFAULT NULL,
        total_affected_households INT DEFAULT 0,
        total_affected_population INT DEFAULT 0,
        total_affected_pwd INT DEFAULT 0,
        total_affected_seniors INT DEFAULT 0,
        total_affected_infants INT DEFAULT 0,
        total_affected_minors INT DEFAULT 0,
        total_affected_pregnant INT DEFAULT 0,
        total_ip_count INT DEFAULT 0,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'Create affected_areas table' => "CREATE TABLE IF NOT EXISTS affected_areas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        incident_id INT NOT NULL,
        barangay_id INT NOT NULL,
        affected_households INT DEFAULT 0,
        affected_population INT DEFAULT 0,
        affected_pwd INT DEFAULT 0,
        affected_seniors INT DEFAULT 0,
        affected_infants INT DEFAULT 0,
        affected_minors INT DEFAULT 0,
        affected_pregnant INT DEFAULT 0,
        ip_count INT DEFAULT 0,
        computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (incident_id) REFERENCES incident_reports(id) ON DELETE CASCADE
    )",
    'Create population_data_archive table' => "CREATE TABLE IF NOT EXISTS population_data_archive (
        id INT PRIMARY KEY AUTO_INCREMENT,
        original_id INT DEFAULT NULL,
        barangay_id INT DEFAULT NULL,
        total_population INT DEFAULT 0,
        households INT DEFAULT 0,
        elderly_count INT DEFAULT 0,
        children_count INT DEFAULT 0,
        pwd_count INT DEFAULT 0,
        ips_count INT DEFAULT 0,
        solo_parent_count INT DEFAULT 0,
        widow_count INT DEFAULT 0,
        data_date DATE DEFAULT NULL,
        archived_by VARCHAR(100) DEFAULT 'system',
        change_type ENUM('INSERT','UPDATE','DELETE') DEFAULT 'UPDATE',
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'Sync all barangay computed columns from households' => null, // handled separately below
];

foreach ($migrations as $label => $sql) {
    if ($sql === null) continue;
    try {
        $pdo->exec($sql);
        $results[] = ['status' => 'ok', 'label' => $label];
    } catch (PDOException $e) {
        $results[] = ['status' => 'error', 'label' => $label, 'error' => $e->getMessage()];
    }
}

// Run sync on all barangays to populate computed columns
require_once __DIR__ . '/../core/sync.php';
$barangays = $pdo->query("SELECT id FROM barangays")->fetchAll(PDO::FETCH_COLUMN);
$synced = 0;
foreach ($barangays as $bid) {
    try { handle_sync($pdo, $bid); $synced++; } catch (Exception $e) {}
}
$results[] = ['status' => 'ok', 'label' => "Synced $synced barangays from household data"];
?>
<!DOCTYPE html>
<html>
<head>
<title>Run Migrations — DRMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container" style="max-width:700px;">
  <h3><i class="bi bi-database"></i> Migration Results</h3>
  <table class="table table-bordered mt-3">
    <thead><tr><th>Migration</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($results as $r): ?>
      <tr class="<?= $r['status'] === 'ok' ? 'table-success' : 'table-danger' ?>">
        <td><?= htmlspecialchars($r['label']) ?></td>
        <td>
          <?php if ($r['status'] === 'ok'): ?>
            <span class="badge bg-success">OK</span>
          <?php else: ?>
            <span class="badge bg-danger">ERROR</span>
            <small class="ms-2 text-danger"><?= htmlspecialchars($r['error'] ?? '') ?></small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <a href="/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>
</body>
</html>
