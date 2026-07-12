<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    
    if (!empty($username) && !empty($password)) {
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
        $success = "User added successfully!";
    } else {
        $error = "Username and password are required.";
    }
}

// Delete User
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Prevent deleting own account
    if ($id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "User deleted successfully!";
    }
}

// Fetch all users
$users = $db->query("SELECT * FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'User Management';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $page_title ?> · DEBESMSCAT SIS</title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            /* ── Dark Green Palette (matching dashboard) ── */
            --primary: #16a34a;
            --primary-dark: #15803d;
            --primary-light: #22c55e;
            --secondary: #064e3b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
            --dark: #052e16;
            --light-bg: #f0fdf4;
            --sidebar-bg: linear-gradient(180deg, #052e16 0%, #064e3b 50%, #065f46 100%);
            --sidebar-accent: rgba(34, 197, 94, 0.25);
            --topbar-height: 72px;
            --transition: all 0.3s cubic-bezier(0.2, 0.95, 0.4, 1);
            --card-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.03);
            --card-hover-shadow: 0 20px 30px -12px rgba(22, 163, 74, 0.2), 0 4px 8px -4px rgba(0, 0, 0, 0.06);
            --border-radius-card: 24px;
            --border-radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light-bg);
            color: #1e293b;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        .app-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ========== SIDEBAR - FULLY RESPONSIVE ========== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: rgba(255,255,255,0.92);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            box-shadow: 8px 0 30px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            backdrop-filter: blur(2px);
            border-right: 1px solid rgba(255,255,255,0.07);
            overflow: hidden;
        }
        
        /* Sidebar Brand - Centered with Logo */
        .sidebar-brand {
            flex-shrink: 0;
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.02);
            text-align: center;
        }
        
        .sidebar-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 0.75rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
            transition: all 0.3s ease;
            border-radius: 50%;
        }
        
        .sidebar-logo:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 6px 16px rgba(34,197,94,0.4));
        }
        
        .sidebar-brand h4 {
            font-weight: 800;
            letter-spacing: -0.02em;
            color: white;
            margin: 0.5rem 0 0 0;
            font-size: 1rem;
            background: linear-gradient(135deg, #ffffff, #86efac);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .sidebar-brand p {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 4px;
            color: #86efac;
            letter-spacing: 0.5px;
        }
        
        .brand-badge {
            font-size: 0.7rem;
            background: rgba(34, 197, 94, 0.25);
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            margin-top: 8px;
            font-weight: 500;
            color: #86efac;
        }
        
        /* Sidebar navigation - scrollable area */
        .sidebar-nav {
            flex: 1;
            padding: 0 1rem;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        
        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: #86efac;
            border-radius: 10px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 0.75rem 1rem;
            margin: 4px 0;
            color: rgba(255,255,255,0.72);
            text-decoration: none;
            border-radius: 14px;
            font-weight: 500;
            transition: all 0.25s ease;
            position: relative;
        }
        
        .nav-item i {
            font-size: 1.3rem;
            width: 26px;
            text-align: center;
            transition: var(--transition);
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(4px);
        }
        
        .nav-item:hover i {
            transform: scale(1.05);
            color: #86efac;
        }
        
        .nav-item.active {
            background: rgba(22, 163, 74, 0.35);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border: 1px solid rgba(34, 197, 94, 0.45);
        }
        
        .nav-item.active i {
            color: #86efac;
        }
        
        /* Sidebar footer - always visible at bottom */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 1rem 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin-top: 0;
        }
        
        .logout-btn {
            background: rgba(239, 68, 68, 0.15);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 40px;
            padding: 0.7rem 1rem;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: #ef4444;
            border-color: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239,68,68,0.35);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            width: calc(100% - var(--sidebar-width));
            background: #f0fdf4;
            min-height: 100vh;
        }

        /* ========== TOPBAR — DARK GREEN GRADIENT ========== */
        .topbar {
            height: var(--topbar-height);
            background: linear-gradient(135deg, #052e16 0%, #065f46 50%, #047857 100%);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 4px 24px rgba(5,46,22,0.4);
            position: sticky;
            top: 0;
            z-index: 1000;
            overflow: hidden;
        }
        
        /* Animated radial highlight */
        .topbar::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.18) 0%, transparent 65%);
            animation: navbarShine 9s ease-in-out infinite;
            pointer-events: none;
        }
        
        @keyframes navbarShine {
            0%, 100% { transform: translate(-30%, -30%) rotate(0deg); opacity: 0.5; }
            50% { transform: translate(30%, 30%) rotate(180deg); opacity: 1; }
        }
        
        .page-title {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff, #86efac, #6ee7b7);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        
        .page-title i {
            background: linear-gradient(135deg, #ffffff, #86efac);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.6rem;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        
        .user-greeting {
            font-weight: 600;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-size: 0.9rem;
            color: white;
            border: 1px solid rgba(255,255,255,0.18);
            transition: all 0.3s ease;
        }
        
        .user-greeting:hover {
            background: rgba(255,255,255,0.22);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            background: linear-gradient(145deg, #16a34a, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.2, 0.95, 0.4, 1.1);
            border: 2px solid rgba(255,255,255,0.3);
            box-shadow: 0 6px 14px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .user-avatar::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.28);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .user-avatar:hover::before {
            width: 100px;
            height: 100px;
        }
        
        .user-avatar:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 8px 22px rgba(22,163,74,0.5);
            border-color: rgba(255,255,255,0.5);
        }
        
        /* Menu Toggle Button */
        .menu-toggle {
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
            color: white;
            padding: 8px 14px;
            border-radius: 14px;
            transition: all 0.3s ease;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            margin-left: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .menu-toggle::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.28);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .menu-toggle:hover::before {
            width: 100px;
            height: 100px;
        }
        
        .menu-toggle:hover {
            background: rgba(255,255,255,0.22);
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0,0,0,0.3);
        }

        .content-wrapper {
            padding: 2rem;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1055;
            display: none;
        }

        /* Users page specific styles (green theme) */
        .user-table th {
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            background: #f8fafc;
            padding: 1rem;
        }
        
        .user-table td {
            vertical-align: middle;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .role-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .role-admin {
            background: #e0f2e8;
            color: #16a34a;
        }
        
        .role-user {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .btn-delete-user {
            background: transparent;
            border: 1px solid #fecaca;
            border-radius: 40px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            transition: all 0.2s;
            color: #ef4444;
        }
        
        .btn-delete-user:hover {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
            transform: translateY(-2px);
        }
        
        .empty-row td {
            padding: 3rem !important;
            text-align: center;
            color: #94a3b8;
        }

        /* Card Styles */
        .card-modern {
            background: white;
            border-radius: var(--border-radius-card);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            overflow: hidden;
        }
        
        .card-modern:hover {
            box-shadow: var(--card-hover-shadow);
        }

        /* Alert styles */
        .alert {
            border-radius: 60px;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
            box-shadow: 0 8px 20px -8px rgba(0,0,0,0.15);
        }

        /* Form controls green theme */
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22,163,74,0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #16a34a, #059669);
            border: none;
            border-radius: 40px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #15803d, #047857);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22,163,74,0.3);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); position: fixed; transition: transform 0.3s ease; z-index: 1060; }
            .sidebar.mobile-open { transform: translateX(0); }
            .menu-toggle { display: flex; }
        }
        
        @media (min-width: 1201px) {
            .menu-toggle { display: none; }
        }
        
        @media (max-width: 992px) {
            .topbar { padding: 0 1.2rem; }
            .content-wrapper { padding: 1.2rem; }
        }
        
        @media (max-width: 768px) {
            .user-greeting { display: none; }
            .page-title { font-size: 1.2rem; }
            .btn-delete-user { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
            .sidebar-logo { width: 70px; height: 70px; }
            .sidebar-brand h4 { font-size: 0.85rem; }
            .sidebar-brand { padding: 1rem; }
            .nav-item { padding: 0.6rem 0.8rem; }
            .logout-btn { padding: 0.6rem 0.8rem; }
        }
        
        @media (max-width: 576px) {
            .content-wrapper { padding: 1rem; }
            .card-header-custom { flex-direction: column; gap: 10px; }
            .sidebar-logo { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar with Logo and Centered Title -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="image/logo.jpg" alt="DEBESMSCAT Logo" class="sidebar-logo" onerror="this.src='https://placehold.co/80x80/16a34a/white?text=SIS'">
            <h4>Supply Inventory System</h4>
            <div class="brand-badge">Admin Portal</div>
        </div>
        
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="inventory.php" class="nav-item <?= $current_page == 'inventory.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventory</span>
            </a>
            <a href="stocks.php" class="nav-item <?= $current_page == 'stocks.php' ? 'active' : '' ?>">
                <i class="bi bi-inboxes"></i>
                <span>Stocks</span>
            </a>
            
            <a href="users.php" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
            
            <a href="reports.php" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data"></i>
                <span>Reports</span>
            </a>
            <a href="pos_admin.php" class="nav-item <?= $current_page == 'pos_admin.php' ? 'active' : '' ?>">
                <i class="bi bi-camera"></i>
                <span>Stock Adjustment</span>
            </a>

             <a href="logout.php" class="nav-item <?= $current_page == 'logout.php' ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign Out</span>
            </a>
        </div>
        
       
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar with Burger Button -->
        <div class="topbar">
            <div class="page-title">
                <i class="bi bi-people"></i>
                <span>User Management</span>
            </div>
            <div class="user-menu">
                <span class="user-greeting">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                </span>
               
                <button class="menu-toggle" id="menuToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Alerts -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add User Card -->
            <div class="card card-modern mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Add New User</h5>
                    <p class="text-muted small">Create a new system user account.</p>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                                <label for="username">Username</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password">Password</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="role" name="role" required>
                                    <option value="admin">Administrator</option>
                                    <option value="user">User</option>
                                </select>
                                <label for="role">Role</label>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="card card-modern">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>System Users</h5>
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                        <i class="bi bi-person-check me-1"></i><?= count($users) ?> users
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table user-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 15%;">ID</th>
                                    <th style="width: 45%;">Username</th>
                                    <th style="width: 25%;">Role</th>
                                    <th style="width: 15%;" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr class="empty-row">
                                        <td colspan="4">
                                            <i class="bi bi-person-x fs-1 d-block mb-3"></i>
                                            No users found. Add a user to get started.
                                          </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td class="fw-semibold">#<?= $u['id'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-primary bg-opacity-10 p-2">
                                                        <i class="bi bi-person-fill text-primary"></i>
                                                    </div>
                                                    <?= htmlspecialchars($u['username']) ?>
                                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-info bg-opacity-10 text-info ms-2">You</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="role-badge <?= $u['role'] == 'admin' ? 'role-admin' : 'role-user' ?>">
                                                    <i class="bi bi-<?= $u['role'] == 'admin' ? 'shield-shaded' : 'person' ?> me-1"></i>
                                                    <?= $u['role'] == 'admin' ? 'Admin' : 'User' ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn-delete-user" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                                        <i class="bi bi-trash3 me-1"></i>Delete
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Mobile sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    function openSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('mobile-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }
    
    overlay.addEventListener('click', closeSidebar);
    
    // Close sidebar when clicking a nav link on mobile
    $('.nav-item').on('click', function() {
        if (window.innerWidth <= 1200) {
            closeSidebar();
        }
    });
    
    // Auto-close on window resize if screen becomes large
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1200) {
            closeSidebar();
            document.body.style.overflow = '';
        }
    });

    // Initialize DataTables
    $('.datatable').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records..."
        }
    });

    // Enhanced delete confirmation
    $('.btn-delete').click(function(e){
        e.preventDefault();
        const link = $(this).attr('href');
        Swal.fire({
            title: 'Confirm Deletion',
            text: "This action cannot be undone. Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#ef4444',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Yes, delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if(result.isConfirmed) {
                window.location.href = link;
            }
        });
    });

    // Tooltip initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function deleteUser(id, username) {
    Swal.fire({
        title: 'Delete User?',
        html: `Are you sure you want to delete <strong>${username}</strong>?<br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Yes, delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `users.php?delete=${id}`;
        }
    });
}

// Auto-dismiss success alerts after 4 seconds
setTimeout(() => {
    document.querySelectorAll('.alert-success').forEach(alert => {
        alert.style.transition = 'opacity 0.4s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 400);
    });
}, 4000);
</script>
</body>
</html>