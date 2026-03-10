<?php
// barangay_reports.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: login.php');
    exit;
}

$barangay_id = $_SESSION['barangay_id'];

// Get barangay information
$stmt = $pdo->prepare("SELECT * FROM barangays WHERE id = ?");
$stmt->execute([$barangay_id]);
$barangay = $stmt->fetch();

// Handle report generation
if (isset($_POST['generate_report']) || isset($_POST['export_pdf'])) {
    $report_type = $_POST['report_type'];
    
    // Get barangay-specific data based on report type
    switch ($report_type) {
        case 'risk_summary':
            $data = $pdo->prepare("
                SELECT 
                    hz.risk_level,
                    COUNT(*) as zone_count,
                    SUM(hz.affected_population) as total_affected,
                    SUM(hz.area_km2) as total_area
                FROM hazard_zones hz
                WHERE hz.barangay_id = ?
                GROUP BY hz.risk_level
                ORDER BY hz.risk_level
            ");
            $data->execute([$barangay_id]);
            $report_data = $data->fetchAll();
            break;
            
        case 'hazard_details':
            $data = $pdo->prepare("
                SELECT 
                    ht.name as hazard_type,
                    hz.risk_level,
                    hz.area_km2,
                    hz.affected_population,
                    hz.description,
                    DATE(hz.created_at) as reported_date
                FROM hazard_zones hz
                JOIN hazard_types ht ON hz.hazard_type_id = ht.id
                WHERE hz.barangay_id = ?
                ORDER BY hz.risk_level DESC, hz.affected_population DESC
            ");
            $data->execute([$barangay_id]);
            $report_data = $data->fetchAll();
            break;
            
        case 'population_risk':
            $data = $pdo->prepare("
                SELECT 
                    COALESCE(pd.total_population, b.population) as total_population,
                    COALESCE(pd.elderly_count, 0) as elderly_count,
                    COALESCE(pd.children_count, 0) as children_count,
                    COALESCE(pd.pwd_count, 0) as pwd_count,
                    COALESCE(SUM(hz.affected_population), 0) as at_risk_population,
                    ROUND((COALESCE(SUM(hz.affected_population), 0) / COALESCE(pd.total_population, b.population)) * 100, 2) as risk_percentage
                FROM barangays b
                LEFT JOIN (
                    SELECT barangay_id, 
                           MAX(total_population) as total_population,
                           MAX(elderly_count) as elderly_count,
                           MAX(children_count) as children_count,
                           MAX(pwd_count) as pwd_count
                    FROM population_data 
                    WHERE barangay_id = ?
                    GROUP BY barangay_id
                ) as pd ON b.id = pd.barangay_id
                LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
                WHERE b.id = ?
                GROUP BY b.id, b.population, pd.total_population, pd.elderly_count, pd.children_count, pd.pwd_count
            ");
            $data->execute([$barangay_id, $barangay_id]);
            $report_data = $data->fetchAll();
            break;
    }
    
    if (isset($_POST['export_pdf'])) {
        // Simple PDF export (in production, use TCPDF or Dompdf)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $barangay['name'] . '_' . $report_type . '_' . date('Y-m-d') . '.xls"');
        
        echo "<h2>" . $barangay['name'] . " - " . ucfirst(str_replace('_', ' ', $report_type)) . " Report</h2>";
        echo "<p>Generated on: " . date('F j, Y') . "</p>";
        echo "<table border='1'>";
        
        if (!empty($report_data)) {
            // Headers
            echo "<tr>";
            foreach (array_keys($report_data[0]) as $header) {
                echo "<th>" . ucwords(str_replace('_', ' ', $header)) . "</th>";
            }
            echo "</tr>";
            
            // Data
            foreach ($report_data as $row) {
                echo "<tr>";
                foreach ($row as $cell) {
                    echo "<td>" . $cell . "</td>";
                }
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='" . (count($report_data[0] ?? []) ?: 1) . "'>No data available</td></tr>";
        }
        
        echo "</table>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Reports - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Barangay Reports - <?php echo $barangay['name']; ?></h1>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Generate Report</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Report Type</label>
                                        <select name="report_type" class="form-select" required>
                                            <option value="risk_summary">Risk Summary Report</option>
                                            <option value="hazard_details">Hazard Details Report</option>
                                            <option value="population_risk">Population Risk Report</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="generate_report" class="btn btn-primary">
                                            <i class="fas fa-chart-bar me-2"></i>Generate Report
                                        </button>
                                        <button type="submit" name="export_pdf" class="btn btn-success">
                                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Report Preview</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($report_data)): ?>
                                    <?php if (!empty($report_data)): ?>
                                        <h5><?php echo ucfirst(str_replace('_', ' ', $report_type)); ?> Report</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <?php foreach (array_keys($report_data[0]) as $header): ?>
                                                            <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($report_data as $row): ?>
                                                        <tr>
                                                            <?php foreach ($row as $key => $cell): ?>
                                                                <td>
                                                                    <?php if ($key === 'risk_level'): ?>
                                                                        <span class="badge bg-<?php 
                                                                            echo $cell == 'high' ? 'danger' : 
                                                                                 ($cell == 'medium' ? 'warning' : 'success'); 
                                                                        ?>">
                                                                            <?php echo ucfirst($cell); ?>
                                                                        </span>
                                                                    <?php elseif (is_numeric($cell) && $key !== 'id'): ?>
                                                                        <?php echo number_format($cell, $key === 'risk_percentage' ? 2 : 0); ?>
                                                                        <?php echo $key === 'risk_percentage' ? '%' : ''; ?>
                                                                    <?php else: ?>
                                                                        <?php echo htmlspecialchars($cell ?? 'N/A'); ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <h5>No Data Available</h5>
                                            <p class="text-muted">No records found for this report type.</p>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                        <h5>No Report Generated</h5>
                                        <p class="text-muted">Select a report type and click "Generate Report" to view data.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>