<?php
// navbar.php
?>
<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg fixed-top" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); height: var(--header-height); padding: 0 1.5rem; box-shadow: var(--shadow-md); z-index: 1030;">
    <div class="container-fluid px-0">
        <!-- Sidebar Toggle for Mobile -->
        <button class="btn btn-link text-white d-lg-none" type="button" id="sidebarToggle">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="color: white">
            <div class="brand-icon me-2" style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-map-marked-alt" style="color: var(--primary); font-size: 1.2rem;"></i>
            </div>
            <div>
                <span style="font-weight: 600; font-size: 1.2rem;">Sablayan</span>
                <span style="font-weight: 300; font-size: 0.9rem; display: block; margin-top: -5px; opacity: 0.8;">Risk Assessment System</span>
            </div>
        </a>
        
        <!-- Right Side Navigation -->
        <div class="navbar-nav ms-auto flex-row align-items-center">
            <!-- Notifications -->
            <div class="nav-item dropdown me-3">
                <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" style="color: white;">
                    <i class="fas fa-bell fa-lg"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        3
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 300px; padding: 0;">
                    <div class="dropdown-header bg-light py-3 px-3">
                        <h6 class="mb-0">Notifications</h6>
                    </div>
                    <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
                        <a class="dropdown-item py-3 px-3 border-bottom" href="#">
                            <div class="d-flex align-items-center">
                                <div class="notification-icon bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">2 min ago</small>
                                    <span>New hazard zone identified</span>
                                </div>
                            </div>
                        </a>
                        <a class="dropdown-item py-3 px-3 border-bottom" href="#">
                            <div class="d-flex align-items-center">
                                <div class="notification-icon bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-users text-primary"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">1 hour ago</small>
                                    <span>Population data updated</span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="dropdown-footer text-center py-2">
                        <a href="#" class="text-decoration-none small">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="nav-item dropdown">
                <a class="nav-link d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" style="color: white;">
                    <div class="user-avatar me-2" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <span style="font-weight: 600;"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                    </div>
                    <div class="d-none d-md-block">
                        <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.8;"><?php echo ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'User')); ?></div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                    <li>
                        <a class="dropdown-item py-2" href="profile.php">
                            <i class="fas fa-user-circle me-2 text-primary"></i> Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="settings.php">
                            <i class="fas fa-cog me-2 text-secondary"></i> Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>