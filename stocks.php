<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $item_id = (int)$_POST['item_id'];
    $new_stock = (int)$_POST['stock'];
    $reason = trim($_POST['reason'] ?? 'Manual adjustment');
    
    if ($new_stock >= 0) {
        // Get current stock for logging
        $stmt = $db->prepare("SELECT stock, name FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $old_stock = $item['stock'];
            $stmt = $db->prepare("UPDATE inventory SET stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $item_id]);
            
            // Log the adjustment (optional - you may have a stock_logs table)
            try {
                $logStmt = $db->prepare("INSERT INTO stock_adjustments (item_id, old_stock, new_stock, reason, adjusted_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $logStmt->execute([$item_id, $old_stock, $new_stock, $reason, $_SESSION['user_id']]);
            } catch (Exception $e) {
                // Table might not exist, ignore
            }
            
            $success = "Stock for '{$item['name']}' updated from {$old_stock} to {$new_stock}.";
        }
    }
}

// Fetch stock statistics
$stats = [];
$stats['total_items'] = $db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$stats['total_stock'] = $db->query("SELECT SUM(stock) FROM inventory")->fetchColumn() ?: 0;
$stats['low_stock'] = $db->query("SELECT COUNT(*) FROM inventory WHERE stock < 10 AND stock > 0")->fetchColumn();
$stats['out_of_stock'] = $db->query("SELECT COUNT(*) FROM inventory WHERE stock = 0")->fetchColumn();
$stats['total_value'] = $db->query("SELECT SUM(price * stock) FROM inventory")->fetchColumn() ?: 0;
$stats['avg_stock'] = $stats['total_items'] > 0 ? round($stats['total_stock'] / $stats['total_items'], 1) : 0;

// Filtering and pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';
$sort_order = $_GET['order'] ?? 'ASC';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(name LIKE ? OR barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($stock_filter === 'low') {
    $where[] = "stock < 10 AND stock > 0";
} elseif ($stock_filter === 'out') {
    $where[] = "stock = 0";
} elseif ($stock_filter === 'healthy') {
    $where[] = "stock >= 10";
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM inventory $where_clause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// Fetch items
$query = "SELECT * FROM inventory $where_clause ORDER BY $sort_by $sort_order LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Stock Monitoring';
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

        /* Stock-specific styles (preserved and updated with green accents) */
        .kpi-card {
            border-radius: var(--border-radius-element);
            padding: 1.5rem 1.2rem;
            height: 100%;
            border: none;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: var(--card-hover-shadow); }
        .kpi-primary { background: linear-gradient(145deg, #16a34a, #059669); }
        .kpi-success { background: linear-gradient(145deg, #059669, #10b981); }
        .kpi-warning { background: linear-gradient(145deg, #d97706, #f59e0b); }
        .kpi-danger { background: linear-gradient(145deg, #dc2626, #ef4444); }
        .kpi-info { background: linear-gradient(145deg, #2563eb, #3b82f6); }
        .kpi-value { font-size: 2.2rem; font-weight: 800; line-height: 1.2; }
        .kpi-label { font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .kpi-icon { font-size: 2.5rem; opacity: 0.6; }

        .filter-bar {
            background: white;
            border-radius: 60px;
            padding: 0.4rem 0.4rem 0.4rem 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.02);
            margin-bottom: 2rem;
        }
        .filter-input {
            border: none;
            background: transparent;
            padding: 0.6rem 0;
            font-size: 0.95rem;
        }
        .filter-input:focus { outline: none; box-shadow: none; }
        .filter-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 40px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-select:hover { border-color: #16a34a; }

        .stock-table th {
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            background: #f8fafc;
            padding: 1rem;
        }
        .stock-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .stock-table tbody tr:hover {
            background: #f8fafc;
        }

        .stock-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .stock-healthy { background: #d1fae5; color: #065f46; }
        .stock-low { background: #fef3c7; color: #92400e; }
        .stock-out { background: #fee2e2; color: #991b1b; }

        .progress-stock {
            width: 100px;
            height: 6px;
            border-radius: 100px;
            background: #e2e8f0;
        }
        .progress-bar-stock {
            height: 6px;
            border-radius: 100px;
            background: #16a34a;
        }

        .btn-adjust {
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            color: #475569;
            transition: all 0.2s;
        }
        .btn-adjust:hover {
            background: #16a34a;
            color: white;
            border-color: #16a34a;
        }

        .modal-content {
            border-radius: 28px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #16a34a, #059669);
            color: white;
            border-bottom: none;
            border-radius: 28px 28px 0 0;
            padding: 1.5rem 1.5rem 1rem;
        }
        .modal-header .btn-close { filter: brightness(0) invert(1); }

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

        /* Mobile responsiveness */
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
            .filter-bar { border-radius: 20px; padding: 1rem; flex-direction: column; align-items: stretch !important; }
            .filter-bar .vr { display: none; }
            .sidebar-logo { width: 70px; height: 70px; }
            .sidebar-brand h4 { font-size: 0.85rem; }
            .sidebar-brand { padding: 1rem; }
            .nav-item { padding: 0.6rem 0.8rem; }
            .logout-btn { padding: 0.6rem 0.8rem; }
        }
        
        @media (max-width: 576px) {
            .content-wrapper { padding: 1rem; }
            .kpi-value { font-size: 1.5rem; }
            .kpi-icon { font-size: 1.8rem; }
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
                <i class="bi bi-inboxes"></i>
                <span>Stock Monitoring</span>
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
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-primary">
                        <div>
                            <div class="kpi-value"><?= number_format($stats['total_items']) ?></div>
                            <div class="kpi-label">Total Items</div>
                        </div>
                        <i class="bi bi-box kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-info">
                        <div>
                            <div class="kpi-value"><?= number_format($stats['total_stock']) ?></div>
                            <div class="kpi-label">Total Units</div>
                        </div>
                        <i class="bi bi-boxes kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-success">
                        <div>
                            <div class="kpi-value">₱<?= number_format($stats['total_value']/1000, 0) ?>k</div>
                            <div class="kpi-label">Total Value</div>
                        </div>
                        <i class="bi bi-currency-dollar kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-warning">
                        <div>
                            <div class="kpi-value"><?= $stats['low_stock'] ?></div>
                            <div class="kpi-label">Low Stock</div>
                        </div>
                        <i class="bi bi-exclamation-triangle kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-danger">
                        <div>
                            <div class="kpi-value"><?= $stats['out_of_stock'] ?></div>
                            <div class="kpi-label">Out of Stock</div>
                        </div>
                        <i class="bi bi-x-circle kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-primary">
                        <div>
                            <div class="kpi-value"><?= $stats['avg_stock'] ?></div>
                            <div class="kpi-label">Avg Stock</div>
                        </div>
                        <i class="bi bi-bar-chart kpi-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="filter-bar d-flex align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center flex-grow-1">
                    <i class="bi bi-search text-muted me-2"></i>
                    <input type="text" name="search" class="filter-input flex-grow-1" placeholder="Search by name or barcode..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="vr mx-2 d-none d-sm-block"></div>
                <select name="stock_filter" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Stock Levels</option>
                    <option value="healthy" <?= $stock_filter == 'healthy' ? 'selected' : '' ?>>Healthy (≥10)</option>
                    <option value="low" <?= $stock_filter == 'low' ? 'selected' : '' ?>>Low Stock (<10)</option>
                    <option value="out" <?= $stock_filter == 'out' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="name" <?= $sort_by == 'name' ? 'selected' : '' ?>>Name</option>
                    <option value="stock" <?= $sort_by == 'stock' ? 'selected' : '' ?>>Stock Level</option>
                    <option value="price" <?= $sort_by == 'price' ? 'selected' : '' ?>>Price</option>
                </select>
                <select name="order" class="filter-select" onchange="this.form.submit()">
                    <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Descending</option>
                </select>
                <button type="submit" class="btn btn-primary rounded-pill px-4 ms-auto">
                    <i class="bi bi-funnel-fill me-1"></i> Apply
                </button>
                <a href="stocks.php" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-repeat"></i></a>
                <?php if(isset($_GET['page'])): ?><input type="hidden" name="page" value="<?= $_GET['page'] ?>"><?php endif; ?>
            </form>

            <!-- Stock Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-list-ul me-2 text-primary"></i>Current Stock Levels</h5>
                    <span class="text-muted small"><?= $totalItems ?> items found</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="stock-table w-100">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Barcode</th>
                                    <th class="text-center">Price</th>
                                    <th class="text-center">Current Stock</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Stock Level</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                            No items found matching your filters.
                                          </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): 
                                        $stockStatus = $item['stock'] == 0 ? 'out' : ($item['stock'] < 10 ? 'low' : 'healthy');
                                        $statusClass = $item['stock'] == 0 ? 'stock-out' : ($item['stock'] < 10 ? 'stock-low' : 'stock-healthy');
                                        $statusText = $item['stock'] == 0 ? 'Out of Stock' : ($item['stock'] < 10 ? 'Low Stock' : 'Healthy');
                                        $stockPercent = min(100, ($item['stock'] / 50) * 100);
                                        $progressColor = $item['stock'] == 0 ? '#ef4444' : ($item['stock'] < 10 ? '#f59e0b' : '#10b981');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="bg-primary bg-opacity-10 p-2 rounded-3">
                                                    <i class="bi bi-box-seam text-primary"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                                                    <?php if (!empty($item['expiration_date'])): ?>
                                                        <br><small class="text-muted">Exp: <?= date('M d, Y', strtotime($item['expiration_date'])) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                          </td>
                                        <td class="text-center"><code><?= htmlspecialchars($item['barcode'] ?? '—') ?></code></td>
                                        <td class="text-center fw-semibold">₱<?= number_format($item['price'], 2) ?></td>
                                        <td class="text-center fw-bold fs-5"><?= $item['stock'] ?></td>
                                        <td class="text-center">
                                            <span class="stock-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <div class="progress-stock">
                                                    <div class="progress-bar-stock" style="width: <?= $stockPercent ?>%; background: <?= $progressColor ?>;"></div>
                                                </div>
                                                <small><?= $item['stock'] ?> units</small>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn-adjust" data-bs-toggle="modal" data-bs-target="#adjustStockModal" 
                                                    data-id="<?= $item['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($item['name']) ?>" 
                                                    data-stock="<?= $item['stock'] ?>">
                                                <i class="bi bi-pencil-square me-1"></i> Adjust
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted small">Showing <?= $offset+1 ?>-<?= min($offset+$limit, $totalItems) ?> of <?= $totalItems ?> items</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page<=1?'disabled':'' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                                <li class="page-item <?= $i==$page?'active':'' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="adjustStockForm">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="item_id" id="modalItemId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Item</label>
                        <input type="text" class="form-control bg-light" id="modalItemName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Stock</label>
                        <input type="text" class="form-control bg-light" id="modalCurrentStock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Stock Quantity</label>
                        <input type="number" class="form-control" name="stock" id="modalNewStock" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason (optional)</label>
                        <select class="form-select" name="reason">
                            <option value="Stock received">Stock received</option>
                            <option value="Inventory count correction">Inventory count correction</option>
                            <option value="Damaged/Expired">Damaged/Expired</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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

    // Populate modal with item data
    const adjustModal = document.getElementById('adjustStockModal');
    adjustModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const stock = button.getAttribute('data-stock');
        
        document.getElementById('modalItemId').value = id;
        document.getElementById('modalItemName').value = name;
        document.getElementById('modalCurrentStock').value = stock;
        document.getElementById('modalNewStock').value = stock;
        document.getElementById('modalNewStock').focus();
    });

    // Auto-dismiss success alerts
    setTimeout(() => {
        document.querySelectorAll('.alert-success').forEach(alert => {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        });
    }, 4000);

    // Real-time search with debounce
    let searchTimeout;
    document.querySelector('input[name="search"]')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => this.form.submit(), 500);
    });
});
</script>
</body>
</html>