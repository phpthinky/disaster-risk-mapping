<?php
// households.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard_barangay.php');
    exit;
}

// Create households table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS households (
        id INT PRIMARY KEY AUTO_INCREMENT,
        household_head VARCHAR(255) NOT NULL,
        barangay_id INT NOT NULL,
        sex ENUM('Male', 'Female') NOT NULL,
        age INT NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        house_type VARCHAR(100),
        family_members INT DEFAULT 1,
        pwd_count INT DEFAULT 0,
        pregnant_count INT DEFAULT 0,
        senior_count INT DEFAULT 0,
        infant_count INT DEFAULT 0,
        minor_count INT DEFAULT 0,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (barangay_id) REFERENCES barangays(id)
    )
");

// Add new columns to households table if they don't exist
try {
    // Check if columns exist and add them if they don't
    $pdo->exec("ALTER TABLE households 
                ADD COLUMN IF NOT EXISTS sitio_purok_zone VARCHAR(255) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS ip_non_ip ENUM('IP', 'Non-IP') DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS hh_id VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS child_count INT DEFAULT 0,
                ADD COLUMN IF NOT EXISTS adolescent_count INT DEFAULT 0,
                ADD COLUMN IF NOT EXISTS young_adult_count INT DEFAULT 0,
                ADD COLUMN IF NOT EXISTS adult_count INT DEFAULT 0,
                ADD COLUMN IF NOT EXISTS middle_aged_count INT DEFAULT 0,
                ADD COLUMN IF NOT EXISTS preparedness_kit ENUM('Yes', 'No') DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS educational_attainment VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
    // Columns might already exist, continue
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_household'])) {
        $household_head = $_POST['household_head'];
        $barangay_id = $_POST['barangay_id'];
        $zone = $_POST['zone'] ?? '';
        $sex = $_POST['gender']; // Use gender as sex
        $age = $_POST['age'];
        $gender = $_POST['gender'];
        $house_type = $_POST['house_type'];
        $family_members = $_POST['family_members'];
        $pwd_count = $_POST['pwd_count'];
        $pregnant_count = $_POST['pregnant_count'];
        $senior_count = $_POST['senior_count'];
        $infant_count = $_POST['infant_count'];
        $minor_count = $_POST['minor_count'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $members_data = json_decode($_POST['members_data'], true);

        // Start transaction
        $pdo->beginTransaction();
        
        try {
// Insert household
$stmt = $pdo->prepare("INSERT INTO households 
                      (household_head, barangay_id, zone, sex, age, gender, house_type, family_members, 
                       pwd_count, pregnant_count, senior_count, infant_count, minor_count, 
                       latitude, longitude, sitio_purok_zone, ip_non_ip, hh_id, child_count, 
                       adolescent_count, young_adult_count, adult_count, middle_aged_count, 
                       preparedness_kit, educational_attainment) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$household_head, $barangay_id, $zone, $gender, $age, $gender, $house_type, $family_members, 
               $pwd_count, $pregnant_count, $senior_count, $infant_count, $minor_count, 
               $latitude, $longitude, $_POST['sitio_purok_zone'], $_POST['ip_non_ip'], $_POST['hh_id'],
               $_POST['child_count'], $_POST['adolescent_count'], $_POST['young_adult_count'],
               $_POST['adult_count'], $_POST['middle_aged_count'], $_POST['preparedness_kit'],
               $_POST['educational_attainment']]);
            
            $household_id = $pdo->lastInsertId();
            
            // Insert members
            if (!empty($members_data)) {
                $memberStmt = $pdo->prepare("INSERT INTO household_members 
                                            (household_id, full_name, age, gender, relationship, 
                                             is_pwd, is_pregnant, is_senior, is_infant, is_minor) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($members_data as $member) {
                    $memberStmt->execute([
                        $household_id,
                        $member['name'],
                        $member['age'],
                        $member['gender'],
                        $member['relationship'],
                        $member['is_pwd'] ? 1 : 0,
                        $member['is_pregnant'] ? 1 : 0,
                        $member['is_senior'] ? 1 : 0,
                        $member['is_infant'] ? 1 : 0,
                        $member['is_minor'] ? 1 : 0
                    ]);
                }
            }
            
            $pdo->commit();
            $success = "Household added successfully!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_household'])) {
        $household_id = $_POST['household_id'];
        $household_head = $_POST['household_head'];
        $barangay_id = $_POST['barangay_id'];
        $zone = $_POST['zone'] ?? '';
        $age = $_POST['age'];
        $gender = $_POST['gender'];
        $house_type = $_POST['house_type'];
        $family_members = $_POST['family_members'];
        $pwd_count = $_POST['pwd_count'];
        $pregnant_count = $_POST['pregnant_count'];
        $senior_count = $_POST['senior_count'];
        $infant_count = $_POST['infant_count'];
        $minor_count = $_POST['minor_count'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $members_data = json_decode($_POST['members_data'], true);

        // Start transaction
        $pdo->beginTransaction();
        
        try {
// Update household
$stmt = $pdo->prepare("UPDATE households 
                      SET household_head = ?, barangay_id = ?, zone = ?, sex = ?, age = ?, gender = ?, 
                          house_type = ?, family_members = ?, pwd_count = ?, pregnant_count = ?, 
                          senior_count = ?, infant_count = ?, minor_count = ?, latitude = ?, 
                          longitude = ?, updated_at = NOW(), sitio_purok_zone = ?, ip_non_ip = ?, 
                          hh_id = ?, child_count = ?, adolescent_count = ?, young_adult_count = ?, 
                          adult_count = ?, middle_aged_count = ?, preparedness_kit = ?, 
                          educational_attainment = ? 
                      WHERE id = ?");
$stmt->execute([$household_head, $barangay_id, $zone, $gender, $age, $gender, $house_type, $family_members, 
               $pwd_count, $pregnant_count, $senior_count, $infant_count, $minor_count, 
               $latitude, $longitude, $_POST['sitio_purok_zone'], $_POST['ip_non_ip'], $_POST['hh_id'],
               $_POST['child_count'], $_POST['adolescent_count'], $_POST['young_adult_count'],
               $_POST['adult_count'], $_POST['middle_aged_count'], $_POST['preparedness_kit'],
               $_POST['educational_attainment'], $household_id]);
            
            // Delete existing members
            $deleteStmt = $pdo->prepare("DELETE FROM household_members WHERE household_id = ?");
            $deleteStmt->execute([$household_id]);
            
            // Insert new members
            if (!empty($members_data)) {
                $memberStmt = $pdo->prepare("INSERT INTO household_members 
                                            (household_id, full_name, age, gender, relationship, 
                                             is_pwd, is_pregnant, is_senior, is_infant, is_minor) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($members_data as $member) {
                    $memberStmt->execute([
                        $household_id,
                        $member['name'],
                        $member['age'],
                        $member['gender'],
                        $member['relationship'],
                        $member['is_pwd'] ? 1 : 0,
                        $member['is_pregnant'] ? 1 : 0,
                        $member['is_senior'] ? 1 : 0,
                        $member['is_infant'] ? 1 : 0,
                        $member['is_minor'] ? 1 : 0
                    ]);
                }
            }
            
            $pdo->commit();
            $success = "Household updated successfully!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    $stmt = $pdo->prepare("DELETE FROM households WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    header('Location: households.php?deleted=1');
    exit;
}

// Handle edit action
$edit_household = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("
        SELECT h.*, b.name as barangay_name 
        FROM households h 
        JOIN barangays b ON h.barangay_id = b.id 
        WHERE h.id = ?
    ");
    $stmt->execute([$edit_id]);
    $edit_household = $stmt->fetch();
}

// Get data for dropdowns
$barangays = $pdo->query("SELECT * FROM barangays")->fetchAll();

// Get households data with filters
$whereConditions = [];
$params = [];

// Barangay filter
if (isset($_GET['barangay_filter']) && !empty($_GET['barangay_filter'])) {
    $whereConditions[] = "h.barangay_id = ?";
    $params[] = $_GET['barangay_filter'];
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $whereConditions[] = "(h.household_head LIKE ? OR b.name LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Build query
$query = "
    SELECT h.*, b.name as barangay_name 
    FROM households h 
    JOIN barangays b ON h.barangay_id = b.id 
";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY h.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$households = $stmt->fetchAll();

// Get hazard zones for map display
$hazard_zones = $pdo->query("
    SELECT hz.*, ht.name as hazard_name, ht.color, b.name as barangay_name 
    FROM hazard_zones hz 
    JOIN hazard_types ht ON hz.hazard_type_id = ht.id 
    JOIN barangays b ON hz.barangay_id = b.id 
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Households Management - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        #householdMap {
            height: 400px;
            width: 100%;
            border-radius: 8px;
        }
        .household-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .household-row:hover {
            background-color: #f8f9fa;
        }
        .household-row.selected {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .stats-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0;
        }
        .stats-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .vulnerability-badge {
            font-size: 0.7rem;
            margin: 1px;
        }
    </style>
    
    <style>
/* Hazard view button */
.btn-outline-info.view-hazard-btn {
    border-color: #17a2b8;
    color: #17a2b8;
}
.btn-outline-info.view-hazard-btn:hover {
    background-color: #17a2b8;
    color: white;
}

/* Hazard modal styles */
#hazardsList .card {
    transition: transform 0.2s;
}
#hazardsList .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.15) !important;
}

#hazardsList .progress {
    background-color: #e9ecef;
    border-radius: 10px;
}

#hazardsList .badge {
    font-size: 0.7rem;
    padding: 3px 6px;
}

/* Loading spinner */
.fa-spinner {
    color: #17a2b8;
}
</style>

</head>
<body style="zoom: 85%;">
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Households Management</h1>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Household deleted successfully!</div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-value text-primary"><?php echo count($households); ?></div>
                            <div class="stats-label">Total Households</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <?php
                            $totalVulnerable = array_reduce($households, function($carry, $item) {
                                return $carry + $item['pwd_count'] + $item['pregnant_count'] + $item['senior_count'] + $item['infant_count'];
                            }, 0);
                            ?>
                            <div class="stats-value text-warning"><?php echo $totalVulnerable; ?></div>
                            <div class="stats-label">Vulnerable Members</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <?php
                            $totalMembers = array_reduce($households, function($carry, $item) {
                                return $carry + $item['family_members'];
                            }, 0);
                            ?>
                            <div class="stats-value text-info"><?php echo $totalMembers; ?></div>
                            <div class="stats-label">Total Family Members</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <?php
                            $seniors = array_reduce($households, function($carry, $item) {
                                return $carry + $item['senior_count'];
                            }, 0);
                            ?>
                            <div class="stats-value text-secondary"><?php echo $seniors; ?></div>
                            <div class="stats-label">Senior Citizens</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <?php
                            $pwd = array_reduce($households, function($carry, $item) {
                                return $carry + $item['pwd_count'];
                            }, 0);
                            ?>
                            <div class="stats-value text-danger"><?php echo $pwd; ?></div>
                            <div class="stats-label">PWD Members</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <?php
                            $children = array_reduce($households, function($carry, $item) {
                                return $carry + $item['infant_count'] + $item['minor_count'];
                            }, 0);
                            ?>
                            <div class="stats-value text-success"><?php echo $children; ?></div>
                            <div class="stats-label">Children</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
<!-- Add/Edit Household Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <?php echo $edit_household ? 'Edit Household' : 'Add New Household'; ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="householdForm">
            <?php if ($edit_household): ?>
                <input type="hidden" name="household_id" value="<?php echo $edit_household['id']; ?>">
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Household Head Name</label>
                <input type="text" name="household_head" class="form-control" 
                    value="<?php echo $edit_household ? htmlspecialchars($edit_household['household_head']) : ''; ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Barangay</label>
                        <select name="barangay_id" id="barangaySelect" class="form-select" required>
                            <option value="">Select Barangay</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?php echo $barangay['id']; ?>" 
                                    <?php echo ($edit_household && $edit_household['barangay_id'] == $barangay['id']) ? 'selected' : ''; ?>>
                                    <?php echo $barangay['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Zone</label>
                        <input type="text" name="zone" class="form-control" 
                            value="<?php echo $edit_household ? htmlspecialchars($edit_household['zone'] ?? '') : ''; ?>" 
                            placeholder="e.g., Zone 1, Purok 2">
                    </div>
                </div>
            </div>
            
            <!-- Add these new fields after the existing zone field -->
<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">Sitio</label>
            <input type="text" name="sitio_purok_zone" class="form-control" 
                value="<?php echo $edit_household ? htmlspecialchars($edit_household['sitio_purok_zone'] ?? '') : ''; ?>" 
                placeholder="e.g., Sitio Maligaya">
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">IP or Non-IP</label>
            <select name="ip_non_ip" class="form-select">
                <option value="">Select</option>
                <option value="IP" <?php echo ($edit_household && ($edit_household['ip_non_ip'] ?? '') == 'IP') ? 'selected' : ''; ?>>IP (Indigenous People)</option>
                <option value="Non-IP" <?php echo ($edit_household && ($edit_household['ip_non_ip'] ?? '') == 'Non-IP') ? 'selected' : ''; ?>>Non-IP</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="mb-3">
            <label class="form-label">HH ID (Household ID)</label>
            <input type="text" name="hh_id" class="form-control" 
                value="<?php echo $edit_household ? htmlspecialchars($edit_household['hh_id'] ?? '') : ''; ?>" 
                placeholder="e.g., HH-2024-001">
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Educational Attainment (Highest in Household)</label>
    <select name="educational_attainment" class="form-select">
        <option value="">Select</option>
        <option value="No Formal Education" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'No Formal Education') ? 'selected' : ''; ?>>No Formal Education</option>
        <option value="Elementary Level" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'Elementary Level') ? 'selected' : ''; ?>>Elementary Level</option>
        <option value="Elementary Graduate" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'Elementary Graduate') ? 'selected' : ''; ?>>Elementary Graduate</option>
        <option value="High School Level" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'High School Level') ? 'selected' : ''; ?>>High School Level</option>
        <option value="High School Graduate" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'High School Graduate') ? 'selected' : ''; ?>>High School Graduate</option>
        <option value="College Level" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'College Level') ? 'selected' : ''; ?>>College Level</option>
        <option value="College Graduate" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'College Graduate') ? 'selected' : ''; ?>>College Graduate</option>
        <option value="Post Graduate" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'Post Graduate') ? 'selected' : ''; ?>>Post Graduate</option>
        <option value="Vocational" <?php echo ($edit_household && ($edit_household['educational_attainment'] ?? '') == 'Vocational') ? 'selected' : ''; ?>>Vocational</option>
    </select>
</div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="Male" <?php echo ($edit_household && $edit_household['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($edit_household && $edit_household['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($edit_household && $edit_household['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['age'] : ''; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">House Type</label>
                <select name="house_type" class="form-select">
                    <option value="">Select House Type</option>
                    <option value="Concrete" <?php echo ($edit_household && $edit_household['house_type'] == 'Concrete') ? 'selected' : ''; ?>>Concrete</option>
                    <option value="Wood" <?php echo ($edit_household && $edit_household['house_type'] == 'Wood') ? 'selected' : ''; ?>>Wood</option>
                    <option value="Bamboo" <?php echo ($edit_household && $edit_household['house_type'] == 'Bamboo') ? 'selected' : ''; ?>>Bamboo</option>
                    <option value="Mixed" <?php echo ($edit_household && $edit_household['house_type'] == 'Mixed') ? 'selected' : ''; ?>>Mixed Materials</option>
                    <option value="Informal" <?php echo ($edit_household && $edit_household['house_type'] == 'Informal') ? 'selected' : ''; ?>>Informal Settlement</option>
                </select>
            </div>
            
            <!-- Household Members Section -->
            <div class="card mt-3 mb-3 border-primary">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>Household Members
                        <button type="button" class="btn btn-sm btn-light float-end" id="addMemberBtn">
                            <i class="fas fa-plus me-1"></i>Add Member
                        </button>
                    </h6>
                </div>
                <div class="card-body" id="membersContainer">
                    <!-- Members will be added here dynamically -->
                    <div class="text-muted text-center py-3" id="noMembersMsg">
                        <i class="fas fa-info-circle me-1"></i>No members added yet. Click "Add Member" to add family members.
                    </div>
                </div>
            </div>
            
            <!-- Hidden input to store members data -->
            <input type="hidden" name="members_data" id="membersData" value="">
            
<!-- Age Group Counts (Auto-calculated) -->
<div class="card mt-3 mb-3 border-info">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Age Group Distribution (Auto-calculated from Members)</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">Child (0-12 years)</label>
                    <input type="number" name="child_count" id="childCount" class="form-control" 
                        value="<?php echo $edit_household ? ($edit_household['child_count'] ?? '0') : '0'; ?>" 
                        min="0" readonly>
                    <small class="text-muted">Auto-calculated</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">Adolescent (13-17 years)</label>
                    <input type="number" name="adolescent_count" id="adolescentCount" class="form-control" 
                        value="<?php echo $edit_household ? ($edit_household['adolescent_count'] ?? '0') : '0'; ?>" 
                        min="0" readonly>
                    <small class="text-muted">Auto-calculated</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">Young Adult (18-30 years)</label>
                    <input type="number" name="young_adult_count" id="youngAdultCount" class="form-control" 
                        value="<?php echo $edit_household ? ($edit_household['young_adult_count'] ?? '0') : '0'; ?>" 
                        min="0" readonly>
                    <small class="text-muted">Auto-calculated</small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">Adult (31-45 years)</label>
                    <input type="number" name="adult_count" id="adultCount" class="form-control" 
                        value="<?php echo $edit_household ? ($edit_household['adult_count'] ?? '0') : '0'; ?>" 
                        min="0" readonly>
                    <small class="text-muted">Auto-calculated</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">Middle-Aged (46-59 years)</label>
                    <input type="number" name="middle_aged_count" id="middleAgedCount" class="form-control" 
                        value="<?php echo $edit_household ? ($edit_household['middle_aged_count'] ?? '0') : '0'; ?>" 
                        min="0" readonly>
                    <small class="text-muted">Auto-calculated</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">Preparedness Kit</label>
                    <select name="preparedness_kit" class="form-select">
                        <option value="">Select</option>
                        <option value="Yes" <?php echo ($edit_household && ($edit_household['preparedness_kit'] ?? '') == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                        <option value="No" <?php echo ($edit_household && ($edit_household['preparedness_kit'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Family Members (Auto-calculated)</label>
                        <input type="number" name="family_members" id="familyMembers" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['family_members'] : '1'; ?>" min="1" readonly required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">PWD Count (Auto-calculated)</label>
                        <input type="number" name="pwd_count" id="pwdCount" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['pwd_count'] : '0'; ?>" min="0" readonly>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Pregnant Count (Auto-calculated)</label>
                        <input type="number" name="pregnant_count" id="pregnantCount" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['pregnant_count'] : '0'; ?>" min="0" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Senior Count (Auto-calculated)</label>
                        <input type="number" name="senior_count" id="seniorCount" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['senior_count'] : '0'; ?>" min="0" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Infant Count (Auto-calculated)</label>
                        <input type="number" name="infant_count" id="infantCount" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['infant_count'] : '0'; ?>" min="0" readonly>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Minor Count (Auto-calculated)</label>
                <input type="number" name="minor_count" id="minorCount" class="form-control" 
                    value="<?php echo $edit_household ? $edit_household['minor_count'] : '0'; ?>" min="0" readonly>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['latitude'] : ''; ?>" 
                            placeholder="e.g., 12.834567" step="any">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-control" 
                            value="<?php echo $edit_household ? $edit_household['longitude'] : ''; ?>" 
                            placeholder="e.g., 120.768901" step="any">
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <?php if ($edit_household): ?>
                    <button type="submit" name="update_household" class="btn btn-warning">
                        <i class="fas fa-save me-2"></i>Update Household
                    </button>
                    <a href="households.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                <?php else: ?>
                    <button type="submit" name="add_household" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Household
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
                    </div>
                    
                    <div class="col-md-8">
                        <!-- Filters -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Search Households</label>
                                        <input type="text" name="search" class="form-control" 
                                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                                            placeholder="Search by household head or barangay...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Filter by Barangay</label>
                                        <select name="barangay_filter" class="form-select">
                                            <option value="">All Barangays</option>
                                            <?php foreach ($barangays as $barangay): ?>
                                                <option value="<?php echo $barangay['id']; ?>" 
                                                    <?php echo (isset($_GET['barangay_filter']) && $_GET['barangay_filter'] == $barangay['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $barangay['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-filter me-2"></i>Filter
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Households Table -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Households List</h5>
                                <span class="badge bg-primary"><?php echo count($households); ?> records</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Household Head</th>
                                                <th>Barangay</th>
                                                <th>Age</th>
                                                <th>Family Size</th>
                                                <th>Vulnerable</th>
                                                <th>Coordinates</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($households as $household): ?>
                                                <tr class="household-row" 
                                                    data-household-id="<?php echo $household['id']; ?>"
                                                    data-household-head="<?php echo htmlspecialchars($household['household_head']); ?>"
                                                    data-barangay="<?php echo htmlspecialchars($household['barangay_name']); ?>"
                                                    data-latitude="<?php echo $household['latitude']; ?>"
                                                    data-longitude="<?php echo $household['longitude']; ?>"
                                                    data-family-members="<?php echo $household['family_members']; ?>"
                                                    data-pwd="<?php echo $household['pwd_count']; ?>"
                                                    data-pregnant="<?php echo $household['pregnant_count']; ?>"
                                                    data-senior="<?php echo $household['senior_count']; ?>"
                                                    data-infant="<?php echo $household['infant_count']; ?>"
                                                    data-minor="<?php echo $household['minor_count']; ?>"
                                                    data-house-type="<?php echo $household['house_type']; ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($household['household_head']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($household['barangay_name']); ?></td>
                                                    <td>
                                                        <?php echo $household['age']; ?> 
                                                        <span class="badge bg-info"><?php echo $household['sex']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $household['family_members']; ?> members</span>
                                                    </td>
                                                    <td>
                                                        <?php if ($household['pwd_count'] > 0): ?>
                                                            <span class="badge bg-danger vulnerability-badge">PWD: <?php echo $household['pwd_count']; ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($household['pregnant_count'] > 0): ?>
                                                            <span class="badge bg-warning vulnerability-badge">Pregnant: <?php echo $household['pregnant_count']; ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($household['senior_count'] > 0): ?>
                                                            <span class="badge bg-secondary vulnerability-badge">Senior: <?php echo $household['senior_count']; ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($household['infant_count'] > 0): ?>
                                                            <span class="badge bg-success vulnerability-badge">Infant: <?php echo $household['infant_count']; ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($household['minor_count'] > 0): ?>
                                                            <span class="badge bg-info vulnerability-badge">Minor: <?php echo $household['minor_count']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($household['latitude'] && $household['longitude']): ?>
                                                            <small class="text-muted">
                                                                <?php echo number_format($household['latitude'], 6); ?>, 
                                                                <?php echo number_format($household['longitude'], 6); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">No coordinates</span>
                                                        <?php endif; ?>
                                                    </td>
<td>
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-success view-members-btn" 
                data-household-id="<?php echo $household['id']; ?>"
                data-household-head="<?php echo htmlspecialchars($household['household_head']); ?>"
                title="View Members">
            <i class="fas fa-users"></i>
        </button>
        <button type="button" class="btn btn-outline-info view-hazard-btn" 
                data-household-id="<?php echo $household['id']; ?>"
                data-household-head="<?php echo htmlspecialchars($household['household_head']); ?>"
                data-latitude="<?php echo $household['latitude']; ?>"
                data-longitude="<?php echo $household['longitude']; ?>"
                data-barangay-id="<?php echo $household['barangay_id']; ?>"
                title="View Hazards">
            <i class="fas fa-exclamation-triangle"></i>
        </button>
        <a href="households.php?edit_id=<?php echo $household['id']; ?>" 
           class="btn btn-outline-primary" title="Edit">
            <i class="fas fa-edit"></i>
        </a>
        <button type="button" class="btn btn-outline-danger delete-household" 
                data-id="<?php echo $household['id']; ?>" 
                data-name="<?php echo htmlspecialchars($household['household_head']); ?>"
                title="Delete">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Map Display -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map me-2"></i> Location Map
                                    <small class="text-muted" id="mapTitle">- Select a household to view location</small>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="householdMap"></div>
                                <div class="mt-3" id="householdDetails" style="display: none;">
                                    <h6>Household Information</h6>
                                    <div class="row" id="detailsContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- View Hazards Modal -->
<div class="modal fade" id="hazardsModal" tabindex="-1" aria-labelledby="hazardsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="hazardsModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Household Hazards Assessment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title" id="modalHouseholdName"></h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Barangay:</strong> <span id="modalBarangay"></span></p>
                                        <p><strong>Coordinates:</strong> <span id="modalCoordinates"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Distance from hazard center:</strong> <span id="modalDistance"></span></p>
                                        <p><strong>Risk Assessment:</strong> <span id="modalRiskStatus"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="mb-3">Hazards Affecting This Household</h6>
                        <div id="hazardsList" class="row">
                            <!-- Hazards will be loaded here -->
                            <div class="col-12 text-center">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading hazards...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="exportHouseholdRisk">
                    <i class="fas fa-download me-1"></i>Export Assessment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Members Modal -->
<div class="modal fade" id="membersModal" tabindex="-1" aria-labelledby="membersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="membersModalLabel">
                    <i class="fas fa-users me-2"></i>Household Members
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title" id="modalHouseholdName"></h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><strong>Barangay:</strong> <span id="modalHouseholdBarangay"></span></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong>Zone:</strong> <span id="modalHouseholdZone"></span></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong>Total Members:</strong> <span id="modalTotalMembers"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="membersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Relationship</th>
                                <th>Vulnerability</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Loading members...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="exportMembersBtn">
                    <i class="fas fa-download me-1"></i>Export List
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Member Form Template (Hidden) -->
<div id="memberTemplate" style="display: none;">
    <div class="member-item card mb-2 border-secondary">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="card-title mb-0">Family Member</h6>
                <button type="button" class="btn btn-sm btn-danger remove-member-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control form-control-sm member-name" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2">
                        <label class="form-label">Age</label>
                        <input type="number" class="form-control form-control-sm member-age" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2">
                        <label class="form-label">Gender</label>
                        <select class="form-select form-select-sm member-gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2">
                        <label class="form-label">Relationship</label>
                        <select class="form-select form-select-sm member-relationship" required>
                            <option value="Spouse">Spouse</option>
                            <option value="Son">Son</option>
                            <option value="Daughter">Daughter</option>
                            <option value="Parent">Parent</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Grandchild">Grandchild</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input member-pwd" type="checkbox">
                        <label class="form-check-label">PWD</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input member-pregnant" type="checkbox">
                        <label class="form-check-label">Pregnant</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input member-senior" type="checkbox">
                        <label class="form-check-label">Senior (60+)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input member-infant" type="checkbox">
                        <label class="form-check-label">Infant (0-1)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input member-minor" type="checkbox">
                        <label class="form-check-label">Minor (2-17)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete household: <strong id="deleteHouseholdName"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Map and marker variables
        let map;
        let householdMarker = null;
        let hazardLayers = [];

        // Initialize the map
        function initMap() {
            // Default coordinates for Sablayan
            const sablayanCoords = [12.8333, 120.7667];
            
            // Initialize the map
            map = L.map('householdMap').setView(sablayanCoords, 12);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Add hazard zones to map
            const hazardZones = <?php echo json_encode($hazard_zones); ?>;
            
            hazardZones.forEach(hazard => {
                let lat, lng;
                if (hazard.coordinates) {
                    const coords = hazard.coordinates.split(',');
                    lat = parseFloat(coords[0].trim());
                    lng = parseFloat(coords[1].trim());
                } else {
                    // Fallback coordinates
                    lat = 12.8333 + (Math.random() - 0.5) * 0.1;
                    lng = 120.7667 + (Math.random() - 0.5) * 0.1;
                }
                
                const circle = L.circle([lat, lng], {
                    color: hazard.color,
                    fillColor: hazard.color,
                    fillOpacity: 0.3,
                    radius: hazard.area_km2 * 100,
                    weight: 2
                }).addTo(map);
                
                circle.bindPopup(`
                    <strong>${hazard.hazard_name}</strong><br>
                    Barangay: ${hazard.barangay_name}<br>
                    Risk Level: ${hazard.risk_level}<br>
                    Area: ${hazard.area_km2} km²<br>
                    Affected: ${hazard.affected_population} people
                `);
                
                hazardLayers.push(circle);
            });
        }

        // Show household on map
        function showHouseholdOnMap(household) {
            const lat = parseFloat(household.latitude);
            const lng = parseFloat(household.longitude);
            
            if (isNaN(lat) || isNaN(lng)) {
                alert('No coordinates available for this household.');
                return;
            }
            
            // Remove existing marker
            if (householdMarker) {
                map.removeLayer(householdMarker);
            }
            
            // Add new marker
            householdMarker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`
                    <strong>${household.householdHead}</strong><br>
                    Barangay: ${household.barangay}<br>
                    Family Members: ${household.familyMembers}<br>
                    House Type: ${household.houseType || 'Not specified'}
                `)
                .openPopup();
            
            // Center map on household
            map.setView([lat, lng], 15);
            
            // Update map title
            document.getElementById('mapTitle').textContent = `- ${household.householdHead}, ${household.barangay}`;
            
            // Show household details
            showHouseholdDetails(household);
        }

        // Show household details
        function showHouseholdDetails(household) {
            const detailsContent = document.getElementById('detailsContent');
            const householdDetails = document.getElementById('householdDetails');
            
            detailsContent.innerHTML = `
                <div class="col-md-6">
                    <p><strong>Household Head:</strong> ${household.householdHead}</p>
                    <p><strong>Barangay:</strong> ${household.barangay}</p>
                    <p><strong>Family Members:</strong> ${household.familyMembers}</p>
                    <p><strong>House Type:</strong> ${household.houseType || 'Not specified'}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Vulnerable Members:</strong></p>
                    <p>PWD: ${household.pwd} | Pregnant: ${household.pregnant}</p>
                    <p>Seniors: ${household.senior} | Infants: ${household.infant} | Minors: ${household.minor}</p>
                    <p><strong>Coordinates:</strong> ${household.latitude}, ${household.longitude}</p>
                </div>
            `;
            
            householdDetails.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            initMap();
            
            // Household row click event
            document.querySelectorAll('.household-row').forEach(row => {
                row.addEventListener('click', function() {
                    // Remove selected class from all rows
                    document.querySelectorAll('.household-row').forEach(r => {
                        r.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked row
                    this.classList.add('selected');
                    
                    // Get household data
                    const household = {
                        id: this.getAttribute('data-household-id'),
                        householdHead: this.getAttribute('data-household-head'),
                        barangay: this.getAttribute('data-barangay'),
                        latitude: this.getAttribute('data-latitude'),
                        longitude: this.getAttribute('data-longitude'),
                        familyMembers: this.getAttribute('data-family-members'),
                        pwd: this.getAttribute('data-pwd'),
                        pregnant: this.getAttribute('data-pregnant'),
                        senior: this.getAttribute('data-senior'),
                        infant: this.getAttribute('data-infant'),
                        minor: this.getAttribute('data-minor'),
                        houseType: this.getAttribute('data-house-type')
                    };
                    
                    // Show on map
                    showHouseholdOnMap(household);
                });
            });
            
            // Delete confirmation modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const deleteHouseholdName = document.getElementById('deleteHouseholdName');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            
            document.querySelectorAll('.delete-household').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent row click event
                    
                    const householdId = this.getAttribute('data-id');
                    const householdName = this.getAttribute('data-name');
                    
                    deleteHouseholdName.textContent = householdName;
                    confirmDeleteBtn.href = `households.php?delete_id=${householdId}`;
                    deleteModal.show();
                });
            });
            
            // Auto-fill coordinates when barangay is selected
            const barangaySelect = document.querySelector('select[name="barangay_id"]');
            const latitudeInput = document.querySelector('input[name="latitude"]');
            const longitudeInput = document.querySelector('input[name="longitude"]');
            
            // Create mapping of barangay names to approximate coordinates
            const barangayCoordinates = {
                <?php foreach ($barangays as $barangay): ?>
                    '<?php echo $barangay['id']; ?>': '<?php echo $barangay['coordinates'] ?? ''; ?>',
                <?php endforeach; ?>
            };
            
            if (barangaySelect) {
                barangaySelect.addEventListener('change', function() {
                    const selectedBarangayId = this.value;
                    
                    if (selectedBarangayId && barangayCoordinates[selectedBarangayId]) {
                        const coords = barangayCoordinates[selectedBarangayId].split(',');
                        if (coords.length === 2) {
                            latitudeInput.value = coords[0].trim();
                            longitudeInput.value = coords[1].trim();
                        }
                    } else {
                        latitudeInput.value = '';
                        longitudeInput.value = '';
                    }
                });
            }
        });
    </script>
    
    <script>
// Hazard icons mapping
const hazardIcons = {
    'Flooding': { icon: 'fa-water', color: '#3498db' },
    'Storm Surge': { icon: 'fa-water', color: '#8e44ad' },
    'Tsunami': { icon: 'fa-water', color: '#2980b9' },
    'Liquefaction': { icon: 'fa-mud', color: '#7f8c8d' },
    'Ground Shaking': { icon: 'fa-hill-rockslide', color: '#c0392b' },
    'Landslide': { icon: 'fa-mountain', color: '#e67e22' }
};

// Risk level colors
const riskColors = {
    'High Susceptible': '#dc3545',
    'Moderate Susceptible': '#ffc107',
    'Low Susceptible': '#28a745',
    'Not Susceptible': '#6c757d',
    'Prone': '#dc3545',
    'Generally Susceptible': '#17a2b8',
    'PEIS VIII - Very destructive to devastating ground shaking': '#8e44ad',
    'PEIS VII - Destructive ground shaking': '#9b59b6',
    'General Inundation': '#17a2b8'
};

// View Hazard button click event
document.querySelectorAll('.view-hazard-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        const householdId = this.getAttribute('data-household-id');
        const householdHead = this.getAttribute('data-household-head');
        const latitude = this.getAttribute('data-latitude');
        const longitude = this.getAttribute('data-longitude');
        const barangayId = this.getAttribute('data-barangay-id');
        
        // Set modal header
        document.getElementById('modalHouseholdName').innerHTML = `<strong>Household Head:</strong> ${householdHead}`;
        document.getElementById('modalCoordinates').textContent = latitude && longitude ? `${latitude}, ${longitude}` : 'No coordinates';
        
        // Show modal with loading
        const modal = new bootstrap.Modal(document.getElementById('hazardsModal'));
        modal.show();
        
        // Fetch hazards for this household
        fetchHouseholdHazards(householdId, latitude, longitude, barangayId);
    });
});

function fetchHouseholdHazards(householdId, latitude, longitude, barangayId) {
    fetch('get_household_hazards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            household_id: householdId,
            latitude: latitude,
            longitude: longitude,
            barangay_id: barangayId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayHouseholdHazards(data.hazards, data.summary);
            document.getElementById('modalBarangay').textContent = data.barangay_name || 'N/A';
            document.getElementById('modalDistance').textContent = data.closest_distance || 'N/A';
            document.getElementById('modalRiskStatus').innerHTML = data.overall_risk ? 
                `<span class="badge bg-${data.overall_risk.color}">${data.overall_risk.level}</span>` : 'No immediate risk';
        } else {
            document.getElementById('hazardsList').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>${data.message}
                    </div>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('hazardsList').innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>Error loading hazards
                </div>
            </div>
        `;
    });
}

function displayHouseholdHazards(hazards, summary) {
    const hazardsList = document.getElementById('hazardsList');
    
    if (!hazards || hazards.length === 0) {
        hazardsList.innerHTML = `
            <div class="col-12">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>No hazards found near this household
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    hazards.forEach(hazard => {
        const iconInfo = hazardIcons[hazard.hazard_name] || { icon: 'fa-exclamation-triangle', color: '#95a5a6' };
        const riskColor = riskColors[hazard.risk_level] || '#6c757d';
        const distanceKm = parseFloat(hazard.distance_km).toFixed(2);
        const isClose = hazard.distance_km <= hazard.radius_km;
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="card ${isClose ? 'border-danger' : ''}" style="box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div style="
                                background: ${iconInfo.color}; 
                                width: 40px; 
                                height: 40px; 
                                border-radius: 50%; 
                                display: flex; 
                                align-items: center; 
                                justify-content: center;
                                margin-right: 10px;
                                color: white;
                            ">
                                <i class="fas ${iconInfo.icon}"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">${hazard.hazard_name}</h6>
                                <span class="badge" style="background-color: ${riskColor}; color: white;">
                                    ${hazard.risk_level}
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <p class="mb-1"><strong>Barangay:</strong> ${hazard.barangay_name}</p>
                            <p class="mb-1"><strong>Distance:</strong> ${distanceKm} km from hazard center</p>
                            <p class="mb-1"><strong>Hazard Area:</strong> ${hazard.area_km2} km²</p>
                            <div class="progress mt-2" style="height: 5px;">
                                <div class="progress-bar" style="width: ${Math.min(100, (hazard.distance_km / hazard.radius_km) * 100)}%; background-color: ${riskColor};"></div>
                            </div>
                            <small class="text-muted">
                                ${isClose ? 
                                    '<i class="fas fa-exclamation-circle text-danger me-1"></i>Within hazard zone' : 
                                    '<i class="fas fa-check-circle text-success me-1"></i>Outside hazard zone'}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    // Add summary statistics
    html += `
        <div class="col-12 mt-3">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6>Total Hazards</h6>
                            <h3>${summary.total_hazards}</h3>
                        </div>
                        <div class="col-md-4">
                            <h6>High Risk Hazards</h6>
                            <h3 class="text-danger">${summary.high_risk_count}</h3>
                        </div>
                        <div class="col-md-4">
                            <h6>Within Hazard Zone</h6>
                            <h3 class="text-warning">${summary.within_hazard_count}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    hazardsList.innerHTML = html;
}

// Export household risk assessment
document.getElementById('exportHouseholdRisk').addEventListener('click', function() {
    const householdName = document.getElementById('modalHouseholdName').innerText.replace('Household Head:', '').trim();
    const hazards = document.querySelectorAll('#hazardsList .col-md-6');
    
    if (hazards.length === 0) {
        alert('No hazard data to export');
        return;
    }
    
    let csv = [];
    csv.push('"Household Hazard Assessment Report"');
    csv.push(`"Generated: ${new Date().toLocaleString()}"`);
    csv.push(`"Household: ${householdName}"`);
    csv.push(`"Barangay: ${document.getElementById('modalBarangay').textContent}"`);
    csv.push(`"Coordinates: ${document.getElementById('modalCoordinates').textContent}"`);
    csv.push('');
    csv.push('"Hazard Type","Risk Level","Distance (km)","Status"');
    
    hazards.forEach(hazard => {
        const hazardText = hazard.innerText.split('\n');
        const lines = hazardText.filter(line => line.trim() !== '');
        if (lines.length > 2) {
            const hazardType = lines[0];
            const riskLevel = lines[1];
            const distance = lines[3].replace('Distance:', '').replace('km from hazard center', '').trim();
            const status = hazard.innerText.includes('Within hazard zone') ? 'Within Zone' : 'Outside Zone';
            
            csv.push(`"${hazardType}","${riskLevel}","${distance}","${status}"`);
        }
    });
    
    // Download CSV
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `household_risk_${householdName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
});
</script>

<script>
// Member management variables
let members = [];
let editMode = <?php echo $edit_household ? 'true' : 'false'; ?>;
let editHouseholdId = <?php echo $edit_household ? $edit_household['id'] : 'null'; ?>;

// Initialize member management
document.addEventListener('DOMContentLoaded', function() {
    // Load existing members if in edit mode
    if (editMode && editHouseholdId) {
        loadExistingMembers(editHouseholdId);
    }
    
    // Add member button click
    document.getElementById('addMemberBtn').addEventListener('click', function() {
        addMemberForm();
    });
    
    // View members button click
    document.querySelectorAll('.view-members-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const householdId = this.getAttribute('data-household-id');
            const householdHead = this.getAttribute('data-household-head');
            viewHouseholdMembers(householdId, householdHead);
        });
    });
});

function loadExistingMembers(householdId) {
    fetch(`get_household_members.php?household_id=${householdId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.members.length > 0) {
                members = data.members;
                displayMembersInForm();
                // The updateMembersFromForms() will be called automatically
                // when members are added to the form
            }
        })
        .catch(error => console.error('Error loading members:', error));
}

function addMemberForm(memberData = null) {
    const template = document.getElementById('memberTemplate').innerHTML;
    const container = document.getElementById('membersContainer');
    const noMembersMsg = document.getElementById('noMembersMsg');
    
    // Hide no members message
    if (noMembersMsg) {
        noMembersMsg.style.display = 'none';
    }
    
    // Create temporary div to hold the template
    const temp = document.createElement('div');
    temp.innerHTML = template;
    const memberForm = temp.firstElementChild;
    
    // If memberData is provided, fill the form
    if (memberData) {
        memberForm.querySelector('.member-name').value = memberData.full_name || '';
        memberForm.querySelector('.member-age').value = memberData.age || '';
        memberForm.querySelector('.member-gender').value = memberData.gender || 'Male';
        memberForm.querySelector('.member-relationship').value = memberData.relationship || 'Other';
        memberForm.querySelector('.member-pwd').checked = memberData.is_pwd == 1;
        memberForm.querySelector('.member-pregnant').checked = memberData.is_pregnant == 1;
        memberForm.querySelector('.member-senior').checked = memberData.is_senior == 1;
        memberForm.querySelector('.member-infant').checked = memberData.is_infant == 1;
        memberForm.querySelector('.member-minor').checked = memberData.is_minor == 1;
    }
    
    // Add remove functionality
    memberForm.querySelector('.remove-member-btn').addEventListener('click', function() {
        memberForm.remove();
        updateMembersFromForms();
        if (container.children.length === 0) {
            document.getElementById('noMembersMsg').style.display = 'block';
        }
    });
    
    // Add change listeners to update counts
    memberForm.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', updateMembersFromForms);
        input.addEventListener('keyup', updateMembersFromForms);
    });
    
    container.appendChild(memberForm);
    updateMembersFromForms();
}

function updateMembersFromForms() {
    const memberForms = document.querySelectorAll('.member-item');
    const membersData = [];
    
    // Initialize counters
    let childCount = 0;      // 0-12 years
    let adolescentCount = 0; // 13-17 years
    let youngAdultCount = 0; // 18-30 years
    let adultCount = 0;      // 31-45 years
    let middleAgedCount = 0; // 46-59 years
    let seniorCount = 0;     // 60+ years (existing)
    let infantCount = 0;     // 0-1 years (existing)
    let minorCount = 0;      // 2-17 years (existing)
    let pwdCount = 0;
    let pregnantCount = 0;
    
    memberForms.forEach(form => {
        const name = form.querySelector('.member-name').value;
        const age = parseInt(form.querySelector('.member-age').value) || 0;
        const gender = form.querySelector('.member-gender').value;
        const relationship = form.querySelector('.member-relationship').value;
        const isPwd = form.querySelector('.member-pwd').checked;
        const isPregnant = form.querySelector('.member-pregnant').checked;
        
        // Determine age group categories
        const isInfant = age >= 0 && age <= 1;
        const isChild = age >= 0 && age <= 12;
        const isAdolescent = age >= 13 && age <= 17;
        const isYoungAdult = age >= 18 && age <= 30;
        const isAdult = age >= 31 && age <= 45;
        const isMiddleAged = age >= 46 && age <= 59;
        const isSenior = age >= 60;
        const isMinor = age >= 2 && age <= 17;
        
        // Auto-check the appropriate age group checkboxes
        form.querySelector('.member-infant').checked = isInfant;
        form.querySelector('.member-minor').checked = isMinor;
        form.querySelector('.member-senior').checked = isSenior;
        
        if (name && age > 0) {
            membersData.push({
                name: name,
                age: age,
                gender: gender,
                relationship: relationship,
                is_pwd: isPwd,
                is_pregnant: isPregnant,
                is_senior: isSenior,
                is_infant: isInfant,
                is_minor: isMinor
            });
            
            // Count age groups
            if (isChild) childCount++;
            if (isAdolescent) adolescentCount++;
            if (isYoungAdult) youngAdultCount++;
            if (isAdult) adultCount++;
            if (isMiddleAged) middleAgedCount++;
            
            // Count vulnerabilities
            if (isPwd) pwdCount++;
            if (isPregnant) pregnantCount++;
            if (isSenior) seniorCount++;
            if (isInfant) infantCount++;
            if (isMinor) minorCount++;
        }
    });
    
    // Update hidden input
    document.getElementById('membersData').value = JSON.stringify(membersData);
    
    // Update all count fields
    let totalMembers = membersData.length + 1; // +1 for household head
    
    document.getElementById('familyMembers').value = totalMembers;
    document.getElementById('pwdCount').value = pwdCount;
    document.getElementById('pregnantCount').value = pregnantCount;
    document.getElementById('seniorCount').value = seniorCount;
    document.getElementById('infantCount').value = infantCount;
    document.getElementById('minorCount').value = minorCount;
    
    // Update new age group fields
    document.getElementById('childCount').value = childCount;
    document.getElementById('adolescentCount').value = adolescentCount;
    document.getElementById('youngAdultCount').value = youngAdultCount;
    document.getElementById('adultCount').value = adultCount;
    document.getElementById('middleAgedCount').value = middleAgedCount;
}

function displayMembersInForm() {
    members.forEach(member => {
        addMemberForm(member);
    });
}

function viewHouseholdMembers(householdId, householdHead) {
    // Set modal title
    document.getElementById('modalHouseholdName').innerHTML = `<strong>Household Head:</strong> ${householdHead}`;
    
    // Show modal with loading
    const modal = new bootstrap.Modal(document.getElementById('membersModal'));
    modal.show();
    
    // Fetch members
    fetch(`get_household_members.php?household_id=${householdId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMembersInModal(data.members, data.household);
            } else {
                document.getElementById('membersTableBody').innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-danger">${data.message}</td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('membersTableBody').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger">Error loading members</td>
                </tr>
            `;
        });
}

function displayMembersInModal(members, household) {
    const tbody = document.getElementById('membersTableBody');
    
    // Update household info
    document.getElementById('modalHouseholdBarangay').textContent = household.barangay_name || 'N/A';
    document.getElementById('modalHouseholdZone').textContent = household.zone || 'N/A';
    document.getElementById('modalTotalMembers').textContent = members.length + 1;
    
    if (!members || members.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">No additional members found</td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    members.forEach((member, index) => {
        // Determine vulnerability badges
        let vulnerabilities = [];
        if (member.is_pwd) vulnerabilities.push('<span class="badge bg-danger">PWD</span>');
        if (member.is_pregnant) vulnerabilities.push('<span class="badge bg-warning">Pregnant</span>');
        if (member.is_senior) vulnerabilities.push('<span class="badge bg-secondary">Senior</span>');
        if (member.is_infant) vulnerabilities.push('<span class="badge bg-success">Infant</span>');
        if (member.is_minor) vulnerabilities.push('<span class="badge bg-info">Minor</span>');
        
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>${member.full_name}</td>
                <td>${member.age}</td>
                <td>${member.gender}</td>
                <td>${member.relationship}</td>
                <td>${vulnerabilities.join(' ') || '<span class="text-muted">None</span>'}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Export members
document.getElementById('exportMembersBtn').addEventListener('click', function() {
    const table = document.getElementById('membersTable');
    const rows = table.querySelectorAll('tr');
    const householdHead = document.getElementById('modalHouseholdName').innerText.replace('Household Head:', '').trim();
    
    let csv = [];
    csv.push('"Household Members List"');
    csv.push(`"Household Head: ${householdHead}"`);
    csv.push(`"Barangay: ${document.getElementById('modalHouseholdBarangay').textContent}"`);
    csv.push(`"Zone: ${document.getElementById('modalHouseholdZone').textContent}"`);
    csv.push(`"Generated: ${new Date().toLocaleString()}"`);
    csv.push('');
    csv.push('"#","Full Name","Age","Gender","Relationship","Vulnerabilities"');
    
    table.querySelectorAll('tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            const rowData = [
                `"${cells[0].textContent}"`,
                `"${cells[1].textContent}"`,
                `"${cells[2].textContent}"`,
                `"${cells[3].textContent}"`,
                `"${cells[4].textContent}"`,
                `"${cells[5].textContent.replace(/<[^>]*>/g, ' ')}"`
            ];
            csv.push(rowData.join(','));
        }
    });
    
    // Download CSV
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `household_members_${householdHead.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
});
</script>

</body>
</html>