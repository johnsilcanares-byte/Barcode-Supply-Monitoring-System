<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Admin Dashboard · DEBESMSCAT SIS';

try {
    // KPI: Total Users
    $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // KPI: Total Items
    $totalItems = (int) $db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();

    // KPI: Total Sales
    $totalSales = (float) $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM sales")->fetchColumn();

    // KPI: Low Stock Items (stock < 10)
    $lowStockItems = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE stock < 10")->fetchColumn();

    // Notification: Expired items
    $expiredItems = 0;
    try {
        $expiredItems = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE()")->fetchColumn();
    } catch (PDOException $e) {}

    // Notification: Expiring soon (within 30 days)
    $expiringSoonItems = 0;
    try {
        $expiringSoonItems = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (PDOException $e) {}

    // Notification: Recent stock adjustments (last 7 days)
    $recentIncreases = 0;
    $recentDecreases = 0;
    try {
        $recentIncreases = (int) $db->query("SELECT COUNT(*) FROM stock_adjustments WHERE adjustment_type = 'increase' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $recentDecreases = (int) $db->query("SELECT COUNT(*) FROM stock_adjustments WHERE adjustment_type = 'decrease' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    } catch (PDOException $e) {}

    // --- NEW: Scanner access requests ---
    $pendingRequests = [];
    $pendingRequestsCount = 0;
    try {
        $stmt = $db->prepare("
            SELECT r.id, r.created_at, u.username, u.email 
            FROM scanner_requests r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.status = 'pending' 
            ORDER BY r.created_at ASC
        ");
        $stmt->execute();
        $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pendingRequestsCount = count($pendingRequests);
    } catch (PDOException $e) {
        // Table might not exist yet – ignore
        $pendingRequests = [];
        $pendingRequestsCount = 0;
    }

} catch (PDOException $e) {
    error_log("Database error (index): " . $e->getMessage());
    die("Database error: Unable to fetch dashboard statistics.");
}

// Format date for header
$currentDate = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes"/>
    <title><?= $page_title ?></title>
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

        /* ========== SIDEBAR - FULLY RESPONSIVE (same as pos_admin) ========== */
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

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.8rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #0f172a, #16a34a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.25rem;
        }

        .welcome-section p { color: #64748b; font-weight: 500; margin-bottom: 0; }

        .date-badge {
            background: white;
            padding: 0.65rem 1.5rem;
            border-radius: 60px;
            box-shadow: var(--card-shadow);
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e2e8f0;
        }

        /* Notification Bar */
        .notification-bar {
            background: white;
            border-radius: 60px;
            padding: 0.7rem 1.8rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem 1.8rem;
        }
        
        .notification-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .notification-divider {
            width: 1px;
            height: 28px;
            background: #e2e8f0;
        }
        
        .notification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1.1rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .notification-badge.warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .notification-badge.danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .notification-badge.info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        .notification-badge.success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .notification-badge.primary { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        
        .notification-badge:hover {
            transform: translateY(-2px);
            filter: brightness(0.98);
        }
        
        .notification-count {
            background: rgba(0,0,0,0.08);
            padding: 0.15rem 0.6rem;
            border-radius: 40px;
            margin-left: 0.25rem;
            font-weight: 700;
        }
        
        .alert-bell {
            animation: softRing 3s infinite;
            color: #f59e0b;
        }
        
        @keyframes softRing {
            0%,100% { transform: rotate(0deg); }
            10%,30% { transform: rotate(-8deg); }
            20%,40% { transform: rotate(8deg); }
        }

        /* KPI Cards */
        .kpi-card {
            border-radius: var(--border-radius-element);
            padding: 1.5rem 1.2rem;
            height: 100%;
            border: none;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            opacity: 0;
            transition: 0.5s;
        }
        
        .kpi-card:hover::after {
            opacity: 1;
            transform: scale(1.2);
        }
        
        .kpi-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.25);
        }
        
        .kpi-value { font-size: 2.4rem; font-weight: 800; line-height: 1.2; margin-bottom: 0.25rem; letter-spacing: -0.02em; }
        .kpi-label { font-size: 0.85rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-icon { font-size: 2.8rem; opacity: 0.7; transition: 0.3s; }
        .kpi-card:hover .kpi-icon { transform: scale(1.05); opacity: 1; }
        
        .kpi-primary { background: linear-gradient(145deg, #16a34a, #059669); }
        .kpi-success { background: linear-gradient(145deg, #059669, #10b981); }
        .kpi-warning { background: linear-gradient(145deg, #d97706, #f59e0b); }
        .kpi-info { background: linear-gradient(145deg, #2563eb, #3b82f6); }

        /* Quick Actions Cards */
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            color: var(--primary);
            background: rgba(22, 163, 74, 0.12);
            padding: 8px;
            border-radius: 14px;
        }
        
        .nav-card {
            background: white;
            border-radius: var(--border-radius-card);
            padding: 1.8rem 1rem;
            height: 100%;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .nav-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-hover-shadow);
            border-color: var(--primary-light);
        }
        
        .nav-card i { font-size: 2.8rem; margin-bottom: 1rem; transition: var(--transition); }
        .nav-card:hover i { transform: scale(1.08); }
        .nav-card h5 { font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; font-size: 1.2rem; }
        .nav-card p { color: #64748b; font-size: 0.85rem; margin: 0; font-weight: 500; }
        
        .icon-primary { color: var(--primary); }
        .icon-success { color: var(--success); }
        .icon-info { color: var(--info); }
        .icon-warning { color: var(--warning); }
        .icon-danger { color: var(--danger); }
        .icon-secondary { color: #8b5cf6; }

        .pulse-warning { animation: softPulse 2s infinite; }
        
        @keyframes softPulse {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
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

        /* Modal custom styling */
        .modal-requests .modal-content {
            border-radius: 24px;
            border: none;
            overflow: hidden;
        }
        .modal-requests .modal-header {
            background: linear-gradient(135deg, #052e16, #064e3b);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
        }
        .modal-requests .btn-close-white {
            filter: brightness(0) invert(1);
        }
        .request-table td, .request-table th {
            vertical-align: middle;
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
            .dashboard-header { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 768px) {
            .notification-bar { border-radius: 28px; justify-content: center; padding: 0.8rem 1rem; }
            .welcome-section h1 { font-size: 1.5rem; }
            .kpi-value { font-size: 1.8rem; }
            .user-greeting { display: none; }
            .page-title { font-size: 1.2rem; }
            .sidebar-logo { width: 70px; height: 70px; }
            .sidebar-brand h4 { font-size: 0.85rem; }
            .sidebar-brand { padding: 1rem; }
            .nav-item { padding: 0.6rem 0.8rem; }
            .logout-btn { padding: 0.6rem 0.8rem; }
        }
        
        @media (max-width: 576px) {
            .content-wrapper { padding: 1rem; }
            .nav-card { padding: 1.2rem 0.8rem; }
            .nav-card i { font-size: 2rem; }
            .nav-card h5 { font-size: 1rem; }
            .sidebar-logo { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar with Logo and Centered Title (same as pos_admin) -->
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
                <i class="bi bi-speedometer2"></i>
                <span>Admin Dashboard</span>
            </div>
            <div class="user-menu">
                <span class="user-greeting">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                </span>
               
                <!-- Burger Menu Button -->
                <button class="menu-toggle" id="menuToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="welcome-section">
                    <h1>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?> 👋</h1>
                    <p>Real-time inventory insights and quick actions at your fingertips.</p>
                </div>
                <div class="date-badge">
                    <i class="bi bi-calendar3"></i>
                    <span><?= $currentDate ?></span>
                </div>
            </div>

            <!-- Professional Notification Bar (UPDATED: added scanner requests badge) -->
            <div class="notification-bar">
                <div class="notification-section">
                    <i class="bi bi-bell-fill alert-bell fs-5"></i>
                    <span class="fw-semibold text-secondary">Alerts & Updates</span>
                </div>
                <div class="notification-divider"></div>
                <div class="notification-section">
                    <?php if ($lowStockItems > 0): ?>
                    <a href="stocks.php?tab=restock" class="notification-badge warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Low Stock <span class="notification-count"><?= $lowStockItems ?></span></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($expiredItems > 0): ?>
                    <a href="reports.php?tab=expiring" class="notification-badge danger">
                        <i class="bi bi-calendar-x-fill"></i>
                        <span>Expired <span class="notification-count"><?= $expiredItems ?></span></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($expiringSoonItems > 0): ?>
                    <a href="reports.php?tab=expiring" class="notification-badge info">
                        <i class="bi bi-calendar-exclamation"></i>
                        <span>Expiring Soon <span class="notification-count"><?= $expiringSoonItems ?></span></span>
                    </a>
                    <?php endif; ?>
                    <!-- NEW: Scanner Requests badge -->
                    <?php if ($pendingRequestsCount > 0): ?>
                    <a href="#" class="notification-badge primary" data-bs-toggle="modal" data-bs-target="#scannerRequestsModal">
                        <i class="bi bi-qr-code-scan"></i>
                        <span>Scanner Requests <span class="notification-count"><?= $pendingRequestsCount ?></span></span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ($recentIncreases > 0 || $recentDecreases > 0): ?>
                <div class="notification-divider"></div>
                <div class="notification-section">
                    <?php if ($recentIncreases > 0): ?>
                    <a href="reports.php?tab=history" class="notification-badge success">
                        <i class="bi bi-arrow-up-circle-fill"></i>
                        <span>Stock +<span class="notification-count"><?= $recentIncreases ?></span></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($recentDecreases > 0): ?>
                    <a href="reports.php?tab=history" class="notification-badge danger">
                        <i class="bi bi-arrow-down-circle-fill"></i>
                        <span>Stock -<span class="notification-count"><?= $recentDecreases ?></span></span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($lowStockItems == 0 && $expiredItems == 0 && $expiringSoonItems == 0 && $recentIncreases == 0 && $recentDecreases == 0 && $pendingRequestsCount == 0): ?>
                <div class="notification-section">
                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>All systems operational</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- KPI Cards Row -->
            <div class="row g-4 mb-5">
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card kpi-primary d-flex align-items-center justify-content-between">
                        <div><div class="kpi-value"><?= number_format($totalItems) ?></div><div class="kpi-label">Total Items</div></div>
                        <i class="bi bi-box-seam kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card kpi-success d-flex align-items-center justify-content-between">
                        <div><div class="kpi-value">₱<?= number_format($totalSales, 2) ?></div><div class="kpi-label">Total Sales</div></div>
                        <i class="bi bi-cash-stack kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card kpi-warning d-flex align-items-center justify-content-between pulse-warning">
                        <div><div class="kpi-value" id="lowStockCount"><?= (int)$lowStockItems ?></div><div class="kpi-label">Low Stock Items</div></div>
                        <i class="bi bi-exclamation-triangle kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card kpi-info d-flex align-items-center justify-content-between">
                        <div><div class="kpi-value"><?= number_format($totalUsers) ?></div><div class="kpi-label">Active Users</div></div>
                        <i class="bi bi-people kpi-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Quick Actions (UPDATED: added Scanner Requests card) -->
            <div class="section-title"><i class="bi bi-grid-3x3-gap-fill"></i><span>Quick Navigation</span></div>
            <div class="row g-4 mb-4">
                <div class="col-6 col-md-4 col-lg-3"><a href="inventory.php" class="nav-card"><i class="bi bi-box-seam icon-primary"></i><h5>Inventory</h5><p>Manage stock & items</p></a></div>
                <div class="col-6 col-md-4 col-lg-3"><a href="stocks.php" class="nav-card"><i class="bi bi-inboxes icon-success"></i><h5>Stocks</h5><p>Stock monitoring</p></a></div>
                <div class="col-6 col-md-4 col-lg-3"><a href="users.php" class="nav-card"><i class="bi bi-people icon-warning"></i><h5>Users</h5><p>Manage system users</p></a></div>
                <div class="col-6 col-md-4 col-lg-3"><a href="reports.php" class="nav-card"><i class="bi bi-graph-up icon-secondary"></i><h5>Reports</h5><p>Analytics & insights</p></a></div>
                <div class="col-6 col-md-4 col-lg-3"><a href="pos_admin.php" class="nav-card"><i class="bi bi-camera icon-primary"></i><h5>SIS Camera</h5><p>Smart stock management</p></a></div>
                <!-- NEW: Scanner Requests Card -->
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="#" class="nav-card" data-bs-toggle="modal" data-bs-target="#scannerRequestsModal">
                        <i class="bi bi-qr-code-scan icon-primary"></i>
                        <h5>Scanner Requests 
                            <?php if ($pendingRequestsCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?= $pendingRequestsCount ?></span>
                            <?php endif; ?>
                        </h5>
                        <p>Approve user access</p>
                    </a>
                </div>
            </div>
        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- ==================== MODAL: SCANNER REQUESTS ==================== -->
<div class="modal fade modal-requests" id="scannerRequestsModal" tabindex="-1" aria-labelledby="scannerRequestsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="scannerRequestsModalLabel">
                    <i class="bi bi-qr-code-scan me-2"></i> Scanner Access Requests
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($pendingRequests)): ?>
                    <div class="alert alert-success text-center">
                        <i class="bi bi-check-circle-fill me-2"></i> No pending scanner requests at this time.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover request-table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Requested On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $index => $req): ?>
                                <tr id="request-row-<?= $req['id'] ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($req['username']) ?></td>
                                    <td><?= htmlspecialchars($req['email']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-success approve-request" data-request-id="<?= $req['id'] ?>" data-username="<?= htmlspecialchars($req['username']) ?>">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Core Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function(){
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
    
    // DataTables initialization
    $('.datatable').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records..."
        }
    });

    // Delete confirmation
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
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary px-4 py-2 rounded-3 me-2',
                cancelButton: 'btn btn-outline-secondary px-4 py-2 rounded-3'
            }
        }).then((result) => {
            if(result.isConfirmed) {
                window.location.href = link;
            }
        });
    });

    // Refresh low stock count periodically
    function refreshLowStock() {
        $.getJSON('get_low_stock.php', function(resp) {
            if (resp && typeof resp.count !== 'undefined') {
                $('#lowStockCount').text(resp.count);
            }
        }).fail(function() {});
    }
    refreshLowStock();
    setInterval(refreshLowStock, 15000);

    // ==================== NEW: Approve Scanner Request via AJAX ====================
    $(document).on('click', '.approve-request', function() {
        const btn = $(this);
        const requestId = btn.data('request-id');
        const username = btn.data('username');
        
        Swal.fire({
            title: 'Approve Scanner Access',
            text: `Grant barcode scanner permission to ${username}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#ef4444',
            confirmButtonText: '<i class="bi bi-check-lg"></i> Yes, approve',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Disable button to prevent double submission
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Approving...');
                
                $.ajax({
                    url: 'approve_scanner_request.php',
                    type: 'POST',
                    data: { request_id: requestId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Approved!',
                                text: `Scanner access granted to ${username}.`,
                                confirmButtonColor: '#16a34a'
                            }).then(() => {
                                // Remove row from table
                                $(`#request-row-${requestId}`).remove();
                                // Update pending count in UI
                                let remaining = $('.request-table tbody tr').length;
                                if (remaining === 0) {
                                    // Reload modal content or show empty message
                                    $('#scannerRequestsModal .modal-body').html('<div class="alert alert-success text-center"><i class="bi bi-check-circle-fill me-2"></i> No pending scanner requests at this time.</div>');
                                }
                                // Update badge in notification bar and quick card
                                const newCount = remaining;
                                $('.notification-badge.primary .notification-count').text(newCount);
                                $('.nav-card:has(i.bi-qr-code-scan) h5 .badge').text(newCount);
                                if (newCount === 0) {
                                    $('.notification-badge.primary').remove();
                                    $('.nav-card:has(i.bi-qr-code-scan) h5 .badge').remove();
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: response.message || 'Could not approve request.',
                                confirmButtonColor: '#16a34a'
                            });
                            btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Approve');
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Network Error',
                            text: 'Please try again.',
                            confirmButtonColor: '#16a34a'
                        });
                        btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Approve');
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>