<?php
/**
 * includes/sidebar.php
 * Requires session to be started and BASE_PATH defined.
 * Expects $_SESSION['role'], $_SESSION['username'], $_SESSION['barangay_id']
 */
$role        = $_SESSION['role']        ?? '';
$username    = $_SESSION['username']    ?? 'User';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- GIS Loading Overlay -->
<div id="gisLoadingOverlay" class="loading-overlay">
  <div class="gis-loader">
    <i class="fas fa-map-marked-alt fa-2x text-info mb-3"></i>
    <h4>Loading GIS Data</h4>
    <p>Initializing interactive map...</p>
    <div class="gis-progress-bar"><div class="gis-progress-fill"></div></div>
  </div>
</div>

<!-- Sidebar -->
<div class="sidebar" id="appSidebar">
  <!-- Brand -->
  <div class="text-center py-3 px-2 border-bottom border-secondary">
    <img src="<?= BASE_URL ?>assets/logo.png" alt="DRMS" style="height:50px;">
    <div class="text-white mt-2" style="font-size:.75rem; font-weight:600; letter-spacing:.5px;">
      DISASTER RISK MGMT
    </div>
  </div>

  <!-- User Badge -->
  <div class="px-3 py-2 border-bottom border-secondary" style="background:#12182a;">
    <div class="d-flex align-items-center gap-2">
      <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:.8rem;font-weight:700;color:#fff;">
        <?= strtoupper(substr($username, 0, 1)) ?>
      </div>
      <div>
        <div class="text-white" style="font-size:.8rem;font-weight:600;"><?= htmlspecialchars($username) ?></div>
        <div class="text-muted" style="font-size:.7rem;"><?= ucwords(str_replace('_',' ',$role)) ?></div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="px-2 py-3 flex-grow-1">
    <ul class="nav flex-column">

    <?php if ($role === 'admin'): ?>
      <li><span class="nav-section">Main</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'map_view.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>map/map_view.php" id="mapNavLink">
          <i class="fas fa-map"></i> Interactive Map
        </a>
      </li>

      <li><span class="nav-section">Management</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'barangay_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/barangays/barangay_management.php">
          <i class="fas fa-city"></i> Barangays
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'barangay_boundary.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/barangays/barangay_boundary.php">
          <i class="fas fa-draw-polygon"></i> Barangay Boundaries
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'household_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/households/household_management.php">
          <i class="fas fa-house-user"></i> Households
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'hazard_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/hazards/hazard_management.php">
          <i class="fas fa-exclamation-triangle"></i> Hazard Zones
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($currentPage === 'incident_management.php' || $currentPage === 'incident_list.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/incidents/incident_management.php">
          <i class="fas fa-file-medical-alt"></i> Incident Reports
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'evacuation_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/evacuation/evacuation_management.php">
          <i class="fas fa-house-damage"></i> Evacuation Centers
        </a>
      </li>

      <li><span class="nav-section">Data &amp; Reports</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'population_history.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/population/population_history.php">
          <i class="fas fa-chart-line"></i> Population History
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'population_comparison.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/population/population_comparison.php">
          <i class="fas fa-balance-scale"></i> Population Comparison
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'gps_quality_report.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/households/gps_quality_report.php">
          <i class="fas fa-map-marker-alt"></i> GPS Quality Report
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'risk_analysis.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/reports/risk_analysis.php">
          <i class="fas fa-chart-bar"></i> Risk Analysis
        </a>
      </li>

      <li><span class="nav-section">System</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/users/users.php">
          <i class="fas fa-users-cog"></i> User Management
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'announcements.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/announcements/announcements.php">
          <i class="fas fa-bullhorn"></i> Announcements
        </a>
      </li>

    <?php elseif ($role === 'barangay_staff'): ?>
      <li><span class="nav-section">Main</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'dashboard_barangay.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard_barangay.php">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'map_view.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>map/map_view.php" id="mapNavLink">
          <i class="fas fa-map"></i> Interactive Map
        </a>
      </li>

      <li><span class="nav-section">My Barangay</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'household_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/households/household_management.php">
          <i class="fas fa-house-user"></i> Households
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'hazard_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/hazards/hazard_management.php">
          <i class="fas fa-exclamation-triangle"></i> Hazard Zones
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($currentPage === 'incident_management.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/incidents/incident_management.php">
          <i class="fas fa-file-medical-alt"></i> Incident Reports
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'barangay_profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/barangays/barangay_profile.php">
          <i class="fas fa-building"></i> Barangay Profile
        </a>
      </li>

    <?php elseif ($role === 'division_chief'): ?>
      <li><span class="nav-section">Main</span></li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'dashboard_division.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard_division.php">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'map_view.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>map/map_view.php" id="mapNavLink">
          <i class="fas fa-map"></i> Interactive Map
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'evacuation_management.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/evacuation/evacuation_management.php">
          <i class="fas fa-house-damage"></i> Evacuation Centers
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'population_comparison.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/population/population_comparison.php">
          <i class="fas fa-balance-scale"></i> Population Comparison
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === 'announcements.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/announcements/announcements.php">
          <i class="fas fa-bullhorn"></i> Announcements
        </a>
      </li>
    <?php endif; ?>

    </ul>
  </nav>

  <!-- Logout at bottom -->
  <div class="px-2 pb-3 mt-auto border-top border-secondary pt-2">
    <a class="nav-link text-danger d-flex align-items-center gap-2" href="<?= BASE_URL ?>logout.php">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div>
</div>

<script>
// GIS loading overlay for map link
document.addEventListener('DOMContentLoaded', function () {
  const overlay = document.getElementById('gisLoadingOverlay');
  const mapLink = document.getElementById('mapNavLink');
  if (mapLink && overlay) {
    mapLink.addEventListener('click', function (e) {
      if (!this.classList.contains('active')) {
        e.preventDefault();
        overlay.style.display = 'flex';
        setTimeout(() => { window.location.href = this.href; }, 1800);
      }
    });
  }
});
</script>
