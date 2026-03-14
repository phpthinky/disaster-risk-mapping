<?php
?>
<nav class="navbar navbar-expand-lg" style="background-color: #1f4061;">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php" style="color: white">
            <i class="fas fa-map-marked-alt me-2"></i>
            Sablayan Risk Assessment
        </a>
        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a style="color: white" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['username']; ?>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>