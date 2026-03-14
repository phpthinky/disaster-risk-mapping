<?php
// data_entry.php
session_start();
require_once 'config.php';

// Handle form submission
if ($_POST) {
    if (isset($_POST['submit_general'])) {
        $data_type = $_POST['data_type'];
        $description = $_POST['description'];
        
        $stmt = $pdo->prepare("INSERT INTO data_entries (user_id, data_type, description) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $data_type, $description]);
        
        $success = "Data entry submitted successfully!";
    }
}

// Get user's recent data entries
$user_entries = $pdo->prepare("SELECT * FROM data_entries WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$user_entries->execute([$_SESSION['user_id']]);
$entries = $user_entries->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Entry - Sablayan Risk Assessment</title>
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
                    <h1 class="h2">Data Entry Portal</h1>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">General Data Entry</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Data Type</label>
                                        <select name="data_type" class="form-select" required>
                                            <option value="population">Population Data</option>
                                            <option value="hazard">Hazard Information</option>
                                            <option value="general">General Information</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="4" placeholder="Enter detailed information..." required></textarea>
                                    </div>
                                    <button type="submit" name="submit_general" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Entry
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Links</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="population_data.php" class="btn btn-outline-primary">
                                        <i class="fas fa-users me-2"></i>Population Data Entry
                                    </a>
                                    <a href="hazard_data.php" class="btn btn-outline-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Hazard Data Entry
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">My Recent Entries</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($entries as $entry): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $entry['data_type'] == 'population' ? 'primary' : 
                                                                 ($entry['data_type'] == 'hazard' ? 'warning' : 'info'); 
                                                        ?>">
                                                            <?php echo ucfirst($entry['data_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo substr($entry['description'], 0, 50) . '...'; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $entry['status'] == 'approved' ? 'success' : 
                                                                 ($entry['status'] == 'rejected' ? 'danger' : 'warning'); 
                                                        ?>">
                                                            <?php echo ucfirst($entry['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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