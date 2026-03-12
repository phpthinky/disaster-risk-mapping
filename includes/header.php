<?php
// header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Sablayan Risk Assessment</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Leaflet Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --light: #f8fafc;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
            --footer-height: 60px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: var(--header-height);
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 1020;
            overflow-y: auto;
            overflow-x: hidden;
            color: #fff;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 10px;
        }

        .sidebar-brand-text h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .sidebar-brand-text small {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-label {
            padding: 10px 20px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }

        .sidebar .nav-link {
            padding: 12px 20px;
            color: rgba(255,255,255,0.7);
            transition: var(--transition);
            margin: 2px 10px;
            border-radius: 8px;
        }

        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        .sidebar .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        .sidebar .nav-link .menu-icon {
            width: 24px;
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar-sm {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-role {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .logout-btn {
            color: rgba(255,255,255,0.7);
            transition: var(--transition);
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        /* Main Wrapper */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            transition: var(--transition);
            min-height: calc(100vh - var(--header-height));
            display: flex;
            flex-direction: column;
        }

        .main-wrapper.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .content-wrapper {
            flex: 1;
            padding: 20px;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stats-label {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stats-trend {
            font-size: 0.8rem;
            margin: 0;
        }

        .stats-trend.positive {
            color: var(--success);
        }

        .stats-trend.negative {
            color: var(--danger);
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Timeline */
        .timeline {
            position: relative;
        }

        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 20px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: 15px;
            top: 24px;
            bottom: -24px;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item:last-child:before {
            display: none;
        }

        .timeline-badge {
            position: absolute;
            left: 0;
            top: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }

        .timeline-content {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
        }

        /* Announcements */
        .announcement-item {
            transition: var(--transition);
        }

        .announcement-item:hover {
            background: #f8fafc;
        }

        .announcement-icon .rounded-circle {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Footer */
        .footer {
            background: white;
            padding: 15px 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: var(--secondary);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        .slide-in {
            animation: slideIn 0.3s ease forwards;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile.show {
                transform: translateX(0);
            }
            
            .main-wrapper {
                margin-left: 0;
            }
            
            .main-wrapper.expanded {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>