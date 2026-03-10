<?php
/**
 * includes/header.php
 * Usage: require_once __DIR__ . '/../includes/header.php';
 * Expects: $pageTitle (string), $extraHead (optional string of extra <link>/<style> tags)
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/config.php';
}
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = $pageTitle ?? 'Disaster Risk Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — DRMS</title>

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

  <style>
    html, body { height: 100%; overflow: hidden; }
    .app-wrapper { display: flex; height: 100vh; overflow: hidden; }
    /* Sidebar */
    .sidebar {
      width: 240px; min-width: 240px; background: #1a1f2e;
      overflow-y: auto; overflow-x: hidden; flex-shrink: 0;
      display: flex; flex-direction: column;
    }
    .sidebar .nav-link {
      color: #b0bec5; border-radius: 6px; padding: 8px 12px;
      margin-bottom: 2px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px;
      transition: background 0.15s, color 0.15s;
    }
    .sidebar .nav-link:hover { background: #2d3548; color: #fff; }
    .sidebar .nav-link.active { background: #0d6efd; color: #fff; font-weight: 600; }
    .sidebar .nav-section { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;
      color: #546e7a; padding: 12px 12px 4px; }
    /* Main content */
    .main-content { flex: 1; overflow-y: auto; overflow-x: hidden; background: #f0f2f5; }
    .topbar {
      background: #fff; border-bottom: 1px solid #e0e0e0;
      padding: 0 20px; height: 56px; display: flex; align-items: center;
      justify-content: space-between; position: sticky; top: 0; z-index: 100;
    }
    /* Toast container */
    #toastContainer { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }

    /* GIS Loading Overlay */
    .loading-overlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.85); display: none; justify-content: center;
      align-items: center; z-index: 9998; backdrop-filter: blur(5px);
    }
    .gis-loader {
      background: linear-gradient(145deg,#1a3a5f,#0d1f33); border-radius:12px;
      padding:30px; box-shadow:0 10px 30px rgba(0,0,0,.5);
      border:1px solid rgba(64,156,255,.3); max-width:400px; width:90%; text-align:center;
    }
    .gis-loader h4 { color:#fff; font-weight:600; }
    .gis-loader p  { color:#a0c8ff; font-size:.9rem; }
    .gis-progress-bar { height:6px; background:rgba(255,255,255,.1); border-radius:3px; overflow:hidden; margin-top:12px; }
    .gis-progress-fill { height:100%; background:linear-gradient(90deg,#409cff,#6bd0ff);
      border-radius:3px; animation:gis-fill 2s ease-in-out infinite; }
    @keyframes gis-fill { 0%{width:0%} 50%{width:70%} 100%{width:100%} }
  </style>
  <?= $extraHead ?? '' ?>
</head>
<body>
<div class="app-wrapper">
