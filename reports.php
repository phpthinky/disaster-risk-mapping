<?php
// reports.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle report generation and exports
if (isset($_POST['generate_report']) || isset($_POST['export_pdf']) || isset($_POST['export_excel'])) {
    $report_type = $_POST['report_type'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $barangay_filter = $_POST['barangay_filter'];
    
    // Build base query
    $query = "";
    $params = [];
    
    switch ($report_type) {
        case 'risk_assessment':
            $query = "
                SELECT 
                    b.name as barangay_name,
                    b.population,
                    COUNT(hz.id) as hazard_count,
                    SUM(hz.affected_population) as total_affected,
                    ROUND((SUM(hz.affected_population) / b.population) * 100, 2) as risk_percentage,
                    MAX(hz.risk_level) as highest_risk
                FROM barangays b
                LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
            ";
            break;
            
        case 'hazard_analysis':
            $query = "
                SELECT 
                    ht.name as hazard_type,
                    COUNT(hz.id) as zone_count,
                    SUM(hz.affected_population) as total_affected,
                    SUM(hz.area_km2) as total_area,
                    AVG(CASE 
                        WHEN hz.risk_level = 'high' THEN 3
                        WHEN hz.risk_level = 'medium' THEN 2
                        ELSE 1 
                    END) as avg_risk_score
                FROM hazard_types ht
                LEFT JOIN hazard_zones hz ON ht.id = hz.hazard_type_id
            ";
            break;
            
        case 'population_risk':
            $query = "
                SELECT 
                    b.name as barangay_name,
                    pd.total_population,
                    pd.elderly_count,
                    pd.children_count,
                    pd.pwd_count,
                    SUM(hz.affected_population) as at_risk_population,
                    ROUND((SUM(hz.affected_population) / pd.total_population) * 100, 2) as risk_percentage
                FROM barangays b
                LEFT JOIN population_data pd ON b.id = pd.barangay_id 
                    AND pd.data_date = (SELECT MAX(data_date) FROM population_data WHERE barangay_id = b.id)
                LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
            ";
            break;
    }
    
    // Add filters
    $whereConditions = [];
    
    if ($barangay_filter) {
        $whereConditions[] = "b.id = ?";
        $params[] = $barangay_filter;
    }
    
    if ($date_from && $date_to) {
        $whereConditions[] = "DATE(hz.created_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    }
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Add group by
    switch ($report_type) {
        case 'risk_assessment':
            $query .= " GROUP BY b.id, b.name, b.population";
            break;
        case 'hazard_analysis':
            $query .= " GROUP BY ht.id, ht.name";
            break;
        case 'population_risk':
            $query .= " GROUP BY b.id, b.name, pd.total_population, pd.elderly_count, pd.children_count, pd.pwd_count";
            break;
    }
    
    // $query .= " ORDER BY total_affected DESC";
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
    // Handle exports
    if (isset($_POST['export_pdf'])) {
        exportToPDF($report_data, $report_type, $date_from, $date_to);
        exit;
    }
    
    if (isset($_POST['export_excel'])) {
        exportToExcel($report_data, $report_type, $date_from, $date_to);
        exit;
    }
}

// Get barangays for filter
$barangays = $pdo->query("SELECT * FROM barangays")->fetchAll();

// Get summary statistics for dashboard
$totalBarangays = $pdo->query("SELECT COUNT(*) as total FROM barangays")->fetch()['total'];
$totalHazards = $pdo->query("SELECT COUNT(*) as total FROM hazard_zones")->fetch()['total'];
$totalAtRisk = $pdo->query("SELECT SUM(affected_population) as total FROM hazard_zones")->fetch()['total'];
$highRiskZones = $pdo->query("SELECT COUNT(*) as total FROM hazard_zones WHERE risk_level = 'high'")->fetch()['total'];

// Export functions
function exportToPDF($data, $report_type, $date_from, $date_to) {
    // In a real implementation, you would use a PDF library like TCPDF or Dompdf
    // This is a simplified version that would redirect to a PDF generation script
    header('Location: generate_pdf.php?report_type=' . $report_type . '&date_from=' . $date_from . '&date_to=' . $date_to);
    exit;
}

function exportToExcel($data, $report_type, $date_from, $date_to) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='" . (count($data[0] ?? []) ?: 1) . "'>" . getReportTitle($report_type) . "</th></tr>";
    
    if (!empty($data)) {
        // Headers
        echo "<tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . ucwords(str_replace('_', ' ', $header)) . "</th>";
        }
        echo "</tr>";
        
        // Data
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . $cell . "</td>";
            }
            echo "</tr>";
        }
    } else {
        echo "<tr><td>No data available</td></tr>";
    }
    
    echo "</table>";
    exit;
}

function getReportTitle($report_type) {
    $titles = [
        'risk_assessment' => 'Risk Assessment Report',
        'hazard_analysis' => 'Hazard Analysis Report',
        'population_risk' => 'Population Risk Report'
    ];
    return $titles[$report_type] ?? 'Report';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        .report-table th {
            background-color: #2c3e50;
            color: white;
        }
        .btn-export {
            transition: all 0.2s;
        }
        .btn-export:hover {
            transform: scale(1.05);
        }
        .risk-high { color: #e74c3c; font-weight: bold; }
        .risk-medium { color: #f39c12; font-weight: bold; }
        .risk-low { color: #27ae60; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reports & Analytics</h1>
                </div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <i class="fas fa-city fa-2x mb-2 text-primary"></i>
            <div class="stat-value text-primary"><?php echo $totalBarangays; ?></div>
            <div class="stat-label">Total Barangays</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <i class="fas fa-exclamation-circle fa-2x mb-2 text-warning"></i>
            <div class="stat-value text-warning"><?php echo $totalHazards; ?></div>
            <div class="stat-label">Hazard Zones</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <i class="fas fa-user-injured fa-2x mb-2 text-danger"></i>
            <div class="stat-value text-danger"><?php echo number_format($totalAtRisk); ?></div>
            <div class="stat-label">Population at Risk</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <i class="fas fa-map-marked-alt fa-2x mb-2 text-danger"></i>
            <div class="stat-value text-danger"><?php echo $highRiskZones; ?></div>
            <div class="stat-label">High Risk Zones</div>
        </div>
    </div>
</div>


                <div class="row">
                    <div class="col-md-12">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-cog me-2"></i>Generate Report
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" id="reportForm">
                <div class="row align-items-end">
                    <!-- Report Type -->
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-chart-pie me-2 text-primary"></i>Report Type
                            </label>
                            <select name="report_type" class="form-select" required>
                                <option value="risk_assessment">Risk Assessment Report</option>
                                <option value="hazard_analysis">Hazard Analysis Report</option>
                                <option value="population_risk">Population Risk Report</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Barangay Filter -->
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-map-marker-alt me-2 text-success"></i>Barangay
                            </label>
                            <select name="barangay_filter" class="form-select">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['id']; ?>">
                                        <?php echo htmlspecialchars($barangay['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Date From -->
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-calendar-alt me-2 text-info"></i>From
                            </label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Date To -->
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-calendar-check me-2 text-info"></i>To
                            </label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-white">_</label>
                            <div class="d-flex gap-2">
                                <button type="submit" name="generate_report" class="btn btn-primary flex-grow-1" 
                                        data-bs-toggle="tooltip" title="Generate Report">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <div class="btn-group" role="group">
                                    <button type="submit" name="export_pdf" class="btn btn-danger" 
                                            data-bs-toggle="tooltip" title="Export as PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button type="submit" name="export_excel" class="btn btn-success" 
                                            data-bs-toggle="tooltip" title="Export as Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Filters Toggle (Optional) -->
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="text-center">
                            <a href="#" class="text-decoration-none small" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                <i class="fas fa-filter me-1"></i>Advanced Filters
                                <i class="fas fa-chevron-down ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Filters Section (Collapsible) -->
                <div class="collapse mt-3" id="advancedFilters">
                    <div class="card card-body bg-light">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Risk Level</label>
                                    <select class="form-select">
                                        <option value="">All Risk Levels</option>
                                        <option value="high">High Risk</option>
                                        <option value="medium">Medium Risk</option>
                                        <option value="low">Low Risk</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Hazard Type</label>
                                    <select class="form-select">
                                        <option value="">All Hazard Types</option>
                                        <option value="flood">Flood</option>
                                        <option value="landslide">Landslide</option>
                                        <option value="storm">Storm Surge</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Sort By</label>
                                    <select class="form-select">
                                        <option value="date">Date</option>
                                        <option value="risk">Risk Level</option>
                                        <option value="population">Population</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
                    
                    
                    <div class="col-md-9">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?php echo isset($report_type) ? getReportTitle($report_type) : 'Report Preview'; ?>
                                </h5>
                                <?php if (isset($report_data) && !empty($report_data)): ?>
                                    <div class="text-muted">
                                        <small>Generated on: <?php echo date('M j, Y g:i A'); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (isset($report_data)): ?>
                                    <?php if (!empty($report_data)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover report-table">
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
                                                                    <?php if ($key === 'highest_risk' || $key === 'risk_level'): ?>
                                                                        <span class="risk-<?php echo strtolower($cell); ?>">
                                                                            <?php echo ucfirst($cell); ?>
                                                                        </span>
                                                                    <?php elseif ($key === 'risk_percentage'): ?>
                                                                        <?php echo number_format($cell, 2); ?>%
                                                                    <?php elseif (is_numeric($cell) && $key !== 'id'): ?>
                                                                        <?php echo number_format($cell); ?>
                                                                    <?php else: ?>
                                                                        <?php echo htmlspecialchars($cell ?? '0'); ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Summary Statistics -->
                                        <div class="mt-4 p-3 bg-light rounded">
                                            <h6>Report Summary</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <strong>Total Records:</strong> <?php echo count($report_data); ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Date Range:</strong> 
                                                    <?php echo $date_from ? $date_from . ' to ' . $date_to : 'All dates'; ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Generated By:</strong> <?php echo $_SESSION['username']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <h5>No Data Available</h5>
                                            <p class="text-muted">No records found for the selected criteria.</p>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <h5>No Report Generated</h5>
                                        <p class="text-muted">Select report criteria and click "Generate Report" to view data.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Charts Section -->
                        <?php if (isset($report_data) && !empty($report_data)): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Visual Analytics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <i class="fas fa-chart-pie fa-2x text-primary mb-2"></i>
                                            <h6>Data Distribution</h6>
                                            <p class="text-muted small">Visual representation of report data</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <i class="fas fa-chart-bar fa-2x text-success mb-2"></i>
                                            <h6>Trend Analysis</h6>
                                            <p class="text-muted small">Historical trends and patterns</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Chart visualization would be implemented with Chart.js in production
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Reports</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary quick-report" data-report="risk_assessment">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Quick Risk Report
                                    </button>
                                    <button class="btn btn-outline-warning quick-report" data-report="hazard_analysis">
                                        <i class="fas fa-layer-group me-2"></i>Quick Hazard Report
                                    </button>
                                    <button class="btn btn-outline-info quick-report" data-report="population_risk">
                                        <i class="fas fa-users me-2"></i>Quick Population Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Quick report buttons
            document.querySelectorAll('.quick-report').forEach(btn => {
                btn.addEventListener('click', function() {
                    const reportType = this.getAttribute('data-report');
                    const form = document.getElementById('reportForm');
                    
                    form.report_type.value = reportType;
                    form.date_from.value = '';
                    form.date_to.value = '';
                    form.barangay_filter.value = '';
                    
                    // Submit the form
                    form.querySelector('button[name="generate_report"]').click();
                });
            });
            
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            const oneMonthAgoStr = oneMonthAgo.toISOString().split('T')[0];
            
            document.querySelector('input[name="date_from"]').value = oneMonthAgoStr;
            document.querySelector('input[name="date_to"]').value = today;
            
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.report-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
            
            // Export button animations
            const exportButtons = document.querySelectorAll('.btn-export');
            exportButtons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });
        });
    </script>
</body>
</html>