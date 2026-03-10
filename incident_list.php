<?php
// incident_list.php — Full filterable incident list

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Filters
$f_status    = $_GET['status']       ?? '';
$f_type      = $_GET['hazard_type']  ?? '';
$f_date_from = $_GET['date_from']    ?? '';
$f_date_to   = $_GET['date_to']      ?? '';

$where = ['1=1'];
$params = [];

if ($f_status) { $where[] = 'ir.status = ?'; $params[] = $f_status; }
if ($f_type)   { $where[] = 'ir.hazard_type_id = ?'; $params[] = (int)$f_type; }
if ($f_date_from) { $where[] = 'ir.incident_date >= ?'; $params[] = $f_date_from; }
if ($f_date_to)   { $where[] = 'ir.incident_date <= ?'; $params[] = $f_date_to; }

$sql = "
    SELECT ir.*, ht.name as hazard_type_name, u.username as created_by_name
    FROM incident_reports ir
    LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
    LEFT JOIN users u ON ir.created_by = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ir.incident_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hazard_types = $pdo->query("SELECT * FROM hazard_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident List — Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .main-content { padding: 20px; }
        .status-ongoing    { background: #dc3545; color:#fff; }
        .status-monitoring { background: #fd7e14; color:#fff; }
        .status-resolved   { background: #198754; color:#fff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <?php include 'navbar.php'; ?>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                <h4><i class="fas fa-list me-2"></i>All Incident Reports</h4>
                <a href="incident_reports.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-plus me-1"></i> New Incident
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="ongoing"    <?php echo $f_status==='ongoing'?'selected':''; ?>>Ongoing</option>
                                <option value="monitoring" <?php echo $f_status==='monitoring'?'selected':''; ?>>Monitoring</option>
                                <option value="resolved"   <?php echo $f_status==='resolved'?'selected':''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hazard Type</label>
                            <select name="hazard_type" class="form-select form-select-sm">
                                <option value="">All Types</option>
                                <?php foreach ($hazard_types as $ht): ?>
                                <option value="<?php echo $ht['id']; ?>" <?php echo $f_type==$ht['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars($ht['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $f_date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $f_date_to; ?>">
                        </div>
                        <div class="col-md-auto">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="incident_list.php" class="btn btn-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Disaster Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Affected HH</th>
                                    <th>Affected Pop</th>
                                    <th>PWD</th>
                                    <th>Seniors</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $i => $inc): ?>
                                <tr>
                                    <td><?php echo $i+1; ?></td>
                                    <td><?php echo htmlspecialchars($inc['title']); ?></td>
                                    <td><?php echo htmlspecialchars($inc['hazard_type_name'] ?? '—'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($inc['incident_date'])); ?></td>
                                    <td><span class="badge status-<?php echo $inc['status']; ?>"><?php echo ucfirst($inc['status']); ?></span></td>
                                    <td><?php echo number_format($inc['total_affected_households']); ?></td>
                                    <td><strong><?php echo number_format($inc['total_affected_population']); ?></strong></td>
                                    <td><?php echo number_format($inc['total_affected_pwd']); ?></td>
                                    <td><?php echo number_format($inc['total_affected_seniors']); ?></td>
                                    <td>
                                        <a href="incident_reports.php?view_id=<?php echo $inc['id']; ?>" class="btn btn-sm btn-outline-primary py-0">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($incidents)): ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">No incidents found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
