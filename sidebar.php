<?php 
// Get current file name
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- GIS Loading Overlay -->
<div id="gisLoadingOverlay" class="loading-overlay">
  <div class="gis-loader">
    <div class="gis-loader-content">
      <div class="gis-loader-icon">
        <div class="gis-map-container">
          <div class="gis-map-grid"></div>
          <div class="gis-map-marker">
            <div class="gis-pulse"></div>
          </div>
          <div class="gis-progress-ring">
            <svg width="80" height="80" viewBox="0 0 80 80">
              <circle class="gis-progress-ring-bg" cx="40" cy="40" r="36"></circle>
              <circle class="gis-progress-ring-circle" cx="40" cy="40" r="36"></circle>
            </svg>
          </div>
        </div>
      </div>
      <div class="gis-loader-text">
        <h4>Loading GIS Data</h4>
        <p>Initializing interactive map...</p>
        <div class="gis-progress-bar">
          <div class="gis-progress-fill"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Top Navbar (Visible only on small screens) -->
<nav class="navbar navbar-dark bg-dark sticky-top flex-md-nowrap p-0 shadow d-md-none">
  <a class="navbar-brand col-10 px-3" href="#">
    <img src="logo.png" alt="Logo" style="height: 40px;">
  </a>
  <button class="navbar-toggler collapsed me-2" type="button" 
          data-bs-toggle="collapse" data-bs-target="#sidebarMenu" 
          aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
</nav>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" id="sidebarMenu" style="min-height: 100vh;">
  <div class="position-sticky pt-3">
    <!-- Logo (Visible only on medium and larger screens) -->
    <div class="text-center mb-4 mt-3 d-none d-md-block">
      <img src="logo.png" alt="Logo" class="img-fluid" style="width: 150px;">
    </div>

    <ul class="nav flex-column">
  <?php if ($_SESSION['role'] == 'barangay_staff'): ?>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
          <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'map_view.php') ? 'active' : ''; ?>" href="map_view.php">
          <i class="fas fa-map me-2"></i> Interactive Map
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'incident_reports.php' || $currentPage == 'incident_list.php') ? 'active' : ''; ?>" href="incident_reports.php">
          <i class="fas fa-file-medical-alt me-2"></i> Incident Reports
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'population_data.php') ? 'active' : ''; ?>" href="population_data.php">
          <i class="fas fa-users me-2"></i> Population Data
        </a>
      </li>
    <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'households.php') ? 'active' : ''; ?>" href="households.php">
          <i class="fas fa-users me-2"></i> Households
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'hazard_data.php') ? 'active' : ''; ?>" href="hazard_data.php">
          <i class="fas fa-exclamation-triangle me-2"></i> Hazard Data
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'risk_analysis.php') ? 'active' : ''; ?>" href="risk_analysis.php">
          <i class="fas fa-chart-bar me-2"></i> Risk Analysis
        </a>
      </li>
            <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'barangay_reports.php') ? 'active' : ''; ?>" href="barangay_reports.php">
          <i class="fas fa-file-alt me-2"></i> Barangay Reports
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'barangay_profile.php') ? 'active' : ''; ?>" href="barangay_profile.php">
          <i class="fas fa-building me-2"></i> Barangay Profile
        </a>
      </li>
    <?php endif; ?>
      
      <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
          <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'map_view.php') ? 'active' : ''; ?>" href="map_view.php">
          <i class="fas fa-map me-2"></i> Interactive Map
        </a>
      </li>
        <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'announcements.php') ? 'active' : ''; ?>" href="announcements.php">
          <i class="fas fa-bullhorn me-2"></i> Announcements
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'population_data.php') ? 'active' : ''; ?>" href="population_data.php">
          <i class="fas fa-users me-2"></i> Population Data
        </a>
      </li>
    <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'households.php') ? 'active' : ''; ?>" href="households.php">
          <i class="fas fa-users me-2"></i> Households
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'hazard_data.php') ? 'active' : ''; ?>" href="hazard_data.php">
          <i class="fas fa-exclamation-triangle me-2"></i> Hazard Data
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'risk_analysis.php') ? 'active' : ''; ?>" href="risk_analysis.php">
          <i class="fas fa-chart-bar me-2"></i> Risk Analysis
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
          <i class="fas fa-file-alt me-2"></i> Reports
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>" href="users.php">
          <i class="fas fa-cog me-2"></i> User Management
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'incident_reports.php' || $currentPage == 'incident_list.php') ? 'active' : ''; ?>" href="incident_reports.php">
          <i class="fas fa-file-medical-alt me-2"></i> Incident Reports
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'barangay_boundaries.php') ? 'active' : ''; ?>" href="barangay_boundaries.php">
          <i class="fas fa-draw-polygon me-2"></i> Barangay Boundaries
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'population_comparison.php') ? 'active' : ''; ?>" href="population_comparison.php">
          <i class="fas fa-chart-line me-2"></i> Population Comparison
        </a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link text-white d-flex align-items-center px-3 py-2 rounded hover-bg <?php echo ($currentPage == 'gps_quality_report.php') ? 'active' : ''; ?>" href="gps_quality_report.php">
          <i class="fas fa-map-marker-alt me-2"></i> GPS Quality Report
        </a>
      </li>
      <?php endif; ?>
      
    <?php if ($_SESSION['role'] == 'division_chief'): ?>
        <li class="nav-item">
            <a class="nav-link text-white" href="dashboard_division.php">
                <i class="fas fa-tachometer-alt me-2"></i> Division Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="evacuation_centers.php">
                <i class="fas fa-home me-2"></i> Evacuation Centers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="announcements.php">
                <i class="fas fa-bullhorn me-2"></i> Announcements
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="profile.php">
                <i class="fas fa-user me-2"></i> My Profile
            </a>
        </li>
        <?php endif; ?>
      
    </ul>
  </div>
</div>

<style>
.sidebar .nav-link:hover {
  background-color: #495057;
  color: #fff;
}
.sidebar .nav-link.active {
  background-color: #0d6efd;
  color: #fff;
  font-weight: bold;
}
.hover-bg {
  transition: all 0.2s ease-in-out;
}
.sidebar .nav-link {
  margin-bottom: 12px;
}
@media (max-width: 767.98px) {
  .sidebar {
    position: fixed;
    z-index: 1040;
  }
}

/* GIS Loading Styles */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.85);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  backdrop-filter: blur(5px);
}

.gis-loader {
  background: linear-gradient(145deg, #1a3a5f, #0d1f33);
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  border: 1px solid rgba(64, 156, 255, 0.3);
  max-width: 400px;
  width: 90%;
}

.gis-loader-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.gis-loader-icon {
  margin-bottom: 20px;
  position: relative;
}

.gis-map-container {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto;
}

.gis-map-grid {
  position: absolute;
  width: 100%;
  height: 100%;
  background-image: 
    linear-gradient(rgba(64, 156, 255, 0.2) 1px, transparent 1px),
    linear-gradient(90deg, rgba(64, 156, 255, 0.2) 1px, transparent 1px);
  background-size: 20px 20px;
  border-radius: 8px;
  border: 1px solid rgba(64, 156, 255, 0.5);
}

.gis-map-marker {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 20px;
  height: 20px;
}

.gis-map-marker:before {
  content: '';
  position: absolute;
  width: 20px;
  height: 20px;
  background-color: #ff6b6b;
  border-radius: 50% 50% 50% 0;
  transform: rotate(-45deg);
  box-shadow: 0 0 10px rgba(255, 107, 107, 0.7);
}

.gis-pulse {
  position: absolute;
  width: 20px;
  height: 20px;
  background-color: rgba(255, 107, 107, 0.5);
  border-radius: 50%;
  top: 0;
  left: 0;
  animation: gis-pulse 2s infinite;
}

.gis-progress-ring {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.gis-progress-ring-bg {
  fill: transparent;
  stroke: rgba(255, 255, 255, 0.1);
  stroke-width: 4;
}

.gis-progress-ring-circle {
  fill: transparent;
  stroke: #409cff;
  stroke-width: 4;
  stroke-linecap: round;
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
  stroke-dasharray: 226.1946710584651;
  stroke-dashoffset: 226.1946710584651;
  animation: gis-progress-ring 2s linear infinite;
}

.gis-loader-text h4 {
  color: #fff;
  margin-bottom: 8px;
  font-weight: 600;
}

.gis-loader-text p {
  color: #a0c8ff;
  margin-bottom: 15px;
  font-size: 0.9rem;
}

.gis-progress-bar {
  width: 100%;
  height: 6px;
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
  overflow: hidden;
}

.gis-progress-fill {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #409cff, #6bd0ff);
  border-radius: 3px;
  animation: gis-progress-fill 2s ease-in-out infinite;
}

@keyframes gis-pulse {
  0% {
    transform: scale(0.8);
    opacity: 1;
  }
  100% {
    transform: scale(2.5);
    opacity: 0;
  }
}

@keyframes gis-progress-ring {
  0% {
    stroke-dashoffset: 226.1946710584651;
  }
  50% {
    stroke-dashoffset: 56.548667764616275;
  }
  100% {
    stroke-dashoffset: 0;
  }
}

@keyframes gis-progress-fill {
  0% {
    width: 0%;
  }
  50% {
    width: 70%;
  }
  100% {
    width: 100%;
  }
}
</style>

<script>
// GIS Loading Functionality - Only for Interactive Map
document.addEventListener('DOMContentLoaded', function() {
  const loadingOverlay = document.getElementById('gisLoadingOverlay');
  const interactiveMapLink = document.querySelector('a[href="map_view.php"]');
  
  // Function to show loading overlay
  function showLoadingOverlay() {
    loadingOverlay.style.display = 'flex';
    
    // Reset and restart animation
    const progressFill = document.querySelector('.gis-progress-fill');
    progressFill.style.animation = 'none';
    setTimeout(() => {
      progressFill.style.animation = 'gis-progress-fill 2s ease-in-out infinite';
    }, 10);
  }
  
  // Function to hide loading overlay
  function hideLoadingOverlay() {
    loadingOverlay.style.display = 'none';
  }
  
  // Add click event listener only to Interactive Map link
  if (interactiveMapLink) {
    interactiveMapLink.addEventListener('click', function(e) {
      // Only show loading if not already on the map page
      if (!this.classList.contains('active')) {
        e.preventDefault();
        showLoadingOverlay();
        
        // Navigate to map page after showing loading
        setTimeout(() => {
          window.location.href = this.getAttribute('href');
        }, 2000); // Show loading for 2 seconds
      }
    });
  }
  
  // Hide loading overlay when page is fully loaded
  window.addEventListener('load', hideLoadingOverlay);
  
  // Fallback: hide loading overlay after 5 seconds (in case of errors)
  setTimeout(hideLoadingOverlay, 5000);
});
</script>