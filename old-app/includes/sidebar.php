<?php
// sidebar.php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Menu items
$menu_items = [
    [
        'title' => 'Dashboard',
        'icon' => 'fa-chart-pie',
        'link' => 'dashboard.php',
        'badge' => ''
    ],
    [
        'title' => 'Reports',
        'icon' => 'fa-file-alt',
        'link' => 'reports.php',
        'badge' => 'New'
    ],
    [
        'title' => 'Hazard Zones',
        'icon' => 'fa-map',
        'link' => 'hazard_zones.php',
        'badge' => ''
    ],
    [
        'title' => 'Population Data',
        'icon' => 'fa-users',
        'link' => 'population_data.php',
        'badge' => '3'
    ],
    [
        'title' => 'Announcements',
        'icon' => 'fa-bullhorn',
        'link' => 'announcements.php',
        'badge' => ''
    ],
    [
        'title' => 'Alerts',
        'icon' => 'fa-bell',
        'link' => 'alerts.php',
        'badge' => '2'
    ],
    [
        'title' => 'Barangays',
        'icon' => 'fa-city',
        'link' => 'barangays.php',
        'badge' => ''
    ]
];
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <div class="sidebar-brand-icon">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="sidebar-brand-text">
                <h5>Sablayan MDRRMO</h5>
                <small>Risk Assessment System</small>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Menu -->
    <div class="sidebar-menu">
        <div class="menu-label">MAIN MENU</div>
        <ul class="nav flex-column">
            <?php foreach ($menu_items as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == $item['link']) ? 'active' : ''; ?>" href="<?php echo $item['link']; ?>">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div>
                                <i class="fas <?php echo $item['icon']; ?> menu-icon"></i>
                                <span><?php echo $item['title']; ?></span>
                            </div>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="badge <?php echo $item['badge'] == 'New' ? 'bg-primary' : 'bg-warning'; ?> rounded-pill">
                                    <?php echo $item['badge']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <!-- Secondary Menu -->
        <div class="menu-label mt-4">SYSTEM</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog menu-icon"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="help.php">
                    <i class="fas fa-question-circle menu-icon"></i>
                    <span>Help & Support</span>
                </a>
            </li>
        </ul>
    </div>
    
 
</div>