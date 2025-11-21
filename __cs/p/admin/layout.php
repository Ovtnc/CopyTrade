<?php
// Admin Layout - Sidebar Dashboard
if (!isset($pageTitle)) $pageTitle = "Admin Dashboard";
if (!isset($breadcrumbs)) $breadcrumbs = [['name' => 'Admin', 'url' => WEB_URL . '/admin']];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CopyStar Admin</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= WEB_URL; ?>/vendor/logo.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= WEB_URL; ?>/vendor/st.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
        }

        body {
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a2332 0%, #0f1419 100%);
            color: #fff;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Sidebar should be behind modal */
        body.modal-open .admin-sidebar {
            z-index: 999;
        }

        .admin-sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .admin-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .sidebar-logo img {
            width: 40px;
            height: 40px;
        }

        .sidebar-logo-text {
            transition: opacity 0.3s ease;
        }

        .admin-sidebar.collapsed .sidebar-logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            margin: 5px 15px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }

        .menu-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .menu-link.active {
            background: linear-gradient(135deg, #2A4F79 0%, #1e3a5a 100%);
            color: #fff;
        }

        .menu-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .menu-text {
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }

        .admin-sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .menu-badge {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .admin-sidebar.collapsed .menu-badge {
            display: none;
        }

        /* Main Content Area */
        .admin-main {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            background: var(--bg-secondary);
        }

        .admin-main.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top Header */
        .admin-header {
            height: var(--header-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        /* Header should be behind modal */
        body.modal-open .admin-header {
            z-index: 998;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.3rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: var(--bg-tertiary);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: var(--bg-tertiary);
            border-radius: 25px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .user-menu:hover {
            background: var(--border-color);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2A4F79 0%, #D8A050 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
        }

        /* Content Area */
        .admin-content {
            padding: 30px;
        }

        /* Breadcrumb */
        .admin-breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }

        .admin-breadcrumb .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .admin-breadcrumb .breadcrumb-item.active {
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-sidebar.mobile-open {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
            }

            .admin-main.sidebar-collapsed {
                margin-left: 0;
            }
        }

        /* Mobile Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        /* Overlay should be behind modal */
        body.modal-open .sidebar-overlay {
            z-index: 997;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 992px) {
            .sidebar-overlay.active {
                display: block;
            }
        }

        /* Modal Fixes - Higher z-index to be above sidebar and header */
        .modal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1055 !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }

        .modal-backdrop.show {
            opacity: 0.5 !important;
        }

        .modal-dialog {
            z-index: 1061 !important;
            margin: 1.75rem auto;
            position: relative;
        }

        .modal-content {
            position: relative;
            z-index: 1062 !important;
        }

        .modal.show {
            display: block !important;
        }

        .modal.show .modal-dialog {
            transform: none;
        }

        /* Ensure modal is clickable */
        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: translate(0, 0);
        }

        .modal-content {
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
        }

        [data-theme="dark"] .modal-content {
            background-color: var(--card-bg);
            color: var(--text-primary);
        }

        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            border-color: var(--border-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="<?= WEB_URL; ?>/admin" class="sidebar-logo">
                <img src="<?= WEB_URL; ?>/vendor/logo.png" alt="CopyStar">
                <span class="sidebar-logo-text">CopyStar Admin</span>
            </a>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/admin" class="menu-link <?= (!isset($_GET['p']) || $_GET['p'] == 'admin' || (isset($_GET['p']) && $_GET['p'] == 'admin' && !strpos($_GET['p'], '/'))) ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/admin/traders" class="menu-link <?= (isset($_GET['p']) && (strpos($_GET['p'], 'admin/traders') !== false)) ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span class="menu-text">Traders</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/admin/trades" class="menu-link <?= (isset($_GET['p']) && (strpos($_GET['p'], 'admin/trades') !== false)) ? 'active' : '' ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span class="menu-text">Trades</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/admin/users" class="menu-link <?= (isset($_GET['p']) && (strpos($_GET['p'], 'admin/users') !== false)) ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">Users</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/admin/kyc" class="menu-link <?= (isset($_GET['p']) && (strpos($_GET['p'], 'admin/kyc') !== false)) ? 'active' : '' ?>">
                    <i class="fas fa-id-card"></i>
                    <span class="menu-text">KYC Doğrulamaları</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/admin/withdrawals" class="menu-link <?= (isset($_GET['p']) && (strpos($_GET['p'], 'admin/withdrawals') !== false)) ? 'active' : '' ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span class="menu-text">Para Çekme Talepleri</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/dashboard" class="menu-link">
                    <i class="fas fa-arrow-left"></i>
                    <span class="menu-text">User Panel</span>
                </a>
            </div>
            <div class="menu-item">
                <a href="<?= WEB_URL; ?>/logout" class="menu-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-main" id="adminMain">
        <!-- Top Header -->
        <header class="admin-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="header-title"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['email'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="d-none d-md-block">
                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">
                            <?= htmlspecialchars($currentUser['email'] ?? 'Admin') ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                            Admin
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="admin-content">
            <?php if (!empty($breadcrumbs) && count($breadcrumbs) > 1): ?>
                <nav aria-label="breadcrumb" class="admin-breadcrumb">
                    <ol class="breadcrumb">
                        <?php foreach ($breadcrumbs as $index => $crumb): ?>
                            <?php if ($index === count($breadcrumbs) - 1): ?>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['name']) ?></li>
                            <?php else: ?>
                                <li class="breadcrumb-item">
                                    <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['name']) ?></a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>

