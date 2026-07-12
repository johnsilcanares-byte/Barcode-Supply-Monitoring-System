<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Date range handling (for possible future use)
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// ========== INVENTORY KPIs ==========
function getInventoryKPIs($db) {
    $totalItems = $db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $totalStock = $db->query("SELECT SUM(stock) FROM inventory")->fetchColumn() ?: 0;
    $inventoryValue = $db->query("SELECT SUM(price * stock) FROM inventory")->fetchColumn() ?: 0;
    $outOfStock = $db->query("SELECT COUNT(*) FROM inventory WHERE stock = 0")->fetchColumn();
    $lowStock = $db->query("SELECT COUNT(*) FROM inventory WHERE stock > 0 AND stock < 10")->fetchColumn();
    $healthyStock = $db->query("SELECT COUNT(*) FROM inventory WHERE stock >= 10")->fetchColumn();
    
    $expiringSoon = 0;
    try {
        $expiringSoon = $db->query("SELECT COUNT(*) FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $e) {}
    
    $avgStock = $totalItems > 0 ? round($totalStock / $totalItems, 1) : 0;
    
    $recentAdjustments = 0;
    try {
        $recentAdjustments = $db->query("SELECT COUNT(*) FROM stock_adjustments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $e) {}
    
    return compact('totalItems', 'totalStock', 'inventoryValue', 'outOfStock', 'lowStock', 'healthyStock', 'expiringSoon', 'avgStock', 'recentAdjustments');
}

// ========== STOCK LEVEL DISTRIBUTION ==========
function getStockLevelDistribution($db) {
    return $db->query("
        SELECT 
            CASE 
                WHEN stock = 0 THEN 'Out of Stock'
                WHEN stock < 10 THEN 'Low Stock (<10)'
                WHEN stock < 50 THEN 'Medium Stock (10-49)'
                ELSE 'Well Stocked (50+)'
            END as level,
            COUNT(*) as count,
            SUM(stock) as total_units,
            ROUND(SUM(price * stock), 2) as total_value
        FROM inventory
        GROUP BY level
        ORDER BY 
            CASE level
                WHEN 'Out of Stock' THEN 1
                WHEN 'Low Stock (<10)' THEN 2
                WHEN 'Medium Stock (10-49)' THEN 3
                ELSE 4
            END
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ========== ITEMS NEEDING RESTOCK ==========
function getRestockItems($db) {
    return $db->query("
        SELECT id, name, barcode, stock, price, (price * stock) as stock_value
        FROM inventory 
        WHERE stock < 10 
        ORDER BY stock ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ========== EXPIRING ITEMS ==========
function getExpiringItems($db) {
    try {
        return $db->query("
            SELECT id, name, barcode, stock, expiration_date, DATEDIFF(expiration_date, CURDATE()) as days_left
            FROM inventory 
            WHERE expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
            ORDER BY expiration_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// ========== FULL INVENTORY LIST ==========
function getFullInventory($db, $sortBy = 'name', $sortOrder = 'ASC') {
    $allowed = ['name', 'stock', 'price', 'barcode'];
    $sortBy = in_array($sortBy, $allowed) ? $sortBy : 'name';
    $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
    
    return $db->query("
        SELECT id, name, barcode, stock, price, (price * stock) as stock_value,
               CASE 
                   WHEN stock = 0 THEN 'Out of Stock'
                   WHEN stock < 10 THEN 'Low Stock'
                   WHEN stock < 50 THEN 'Medium Stock'
                   ELSE 'Well Stocked'
               END as status
        FROM inventory 
        ORDER BY $sortBy $sortOrder
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ========== STOCK ADJUSTMENT HISTORY ==========
function getAdjustmentHistory($db, $limit = 50) {
    try {
        return $db->query("
            SELECT sa.*, i.name as item_name, i.barcode, u.username
            FROM stock_adjustments sa
            JOIN inventory i ON sa.item_id = i.id
            JOIN users u ON sa.adjusted_by = u.id
            ORDER BY sa.created_at DESC
            LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$kpiData = getInventoryKPIs($db);
$stockDistribution = getStockLevelDistribution($db);
$restockItems = getRestockItems($db);
$expiringItems = getExpiringItems($db);
$adjustmentHistory = getAdjustmentHistory($db);

$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'ASC';
$fullInventory = getFullInventory($db, $sortBy, $sortOrder);

$page_title = 'Inventory Reports';
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

        /* ========== SIDEBAR - FULLY RESPONSIVE (matching other pages) ========== */
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

        /* KPI Cards (green theme) */
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
        .kpi-trend { font-size: 0.75rem; margin-top: 5px; opacity: 0.8; }

        /* Filter Bar */
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
        .filter-input:focus { outline: none; }
        .filter-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 40px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
            transition: var(--transition);
        }
        .filter-select:hover { border-color: var(--primary); }

        /* Tabs */
        .report-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        .report-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #64748b;
            font-weight: 600;
            padding: 1rem 1.5rem;
            transition: var(--transition);
        }
        .report-tabs .nav-link:hover,
        .report-tabs .nav-link.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            background: transparent;
        }

        /* Tables */
        .table-modern {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-modern th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-modern td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .table-modern tbody tr:hover { background: #fafbfc; }

        .stock-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .stock-out { background: #fee2e2; color: #991b1b; }
        .stock-low { background: #fef3c7; color: #92400e; }
        .stock-medium { background: #dbeafe; color: #1e40af; }
        .stock-healthy { background: #d1fae5; color: #065f46; }

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
            .report-tabs .nav-link { padding: 0.75rem 1rem; font-size: 0.85rem; }
        }
        
        @media (max-width: 768px) {
            .user-greeting { display: none; }
            .page-title { font-size: 1.2rem; }
            .report-tabs { overflow-x: auto; flex-wrap: nowrap; white-space: nowrap; }
            .report-tabs .nav-link { display: inline-block; float: none; }
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

        /* ========== ENHANCED PRINT STYLES – matches PDF look ========== */
        @media print {
            /* Hide all UI elements */
            .sidebar, .topbar, .filter-bar, .report-tabs, .btn, .no-print,
            .kpi-card, .action-bar, .menu-toggle, .sidebar-overlay,
            .nav-tabs, .tab-pane > .card .card-header .btn,
            .d-flex.gap-2, .user-menu, .page-title i,
            .badge, .stock-badge, .progress, .card-header .fw-bold i {
                display: none !important;
            }

            /* Show all tab panes as blocks */
            .tab-content > .tab-pane {
                display: block !important;
                opacity: 1 !important;
                visibility: visible !important;
                page-break-inside: avoid;
            }

            /* Force cards to show their content */
            .card-modern {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                border-radius: 4px !important;
                margin-bottom: 15px !important;
                page-break-inside: avoid;
            }

            .card-body {
                padding: 10px !important;
            }

            /* Main content area – remove margins and padding */
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                background: white !important;
            }

            .content-wrapper {
                padding: 0.5in !important;
            }

            /* Table styling – match PDF look (light green headers) */
            .table-modern {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 10pt !important;
            }

            .table-modern th,
            .table-modern td {
                border: 1px solid #333 !important;
                padding: 5px 8px !important;
                text-align: left !important;
                vertical-align: middle !important;
            }

            .table-modern th {
                background: #d4edda !important; /* light green */
                color: #000 !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                font-size: 9pt !important;
            }

            .table-modern tbody tr:hover {
                background: transparent !important;
            }

            /* Print-only header with logos */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding: 0 20px;
            }
            .print-header .logo-left,
            .print-header .logo-right {
                display: inline-block;
                width: 60px;
                height: 60px;
                vertical-align: middle;
            }
            .print-header .logo-left img,
            .print-header .logo-right img {
                max-width: 100%;
                max-height: 100%;
            }
            .print-header .title-block {
                display: inline-block;
                vertical-align: middle;
                padding: 0 20px;
                text-align: center;
            }
            .print-header .title-block .main-title {
                font-size: 18pt;
                font-weight: bold;
            }
            .print-header .title-block .sub-title {
                font-size: 14pt;
                font-weight: bold;
            }
            .print-header .title-block .report-title {
                font-size: 12pt;
                font-weight: bold;
            }
            .print-header .title-block .date {
                font-size: 10pt;
                margin-top: 2px;
            }
            .print-header hr {
                border: 1px solid #000;
                margin: 10px 0;
            }

            /* Hide KPI cards in print */
            .row.g-4.mb-5 {
                display: none !important;
            }

            /* Ensure the "Overview" tab appears first */
            #overviewTab {
                display: block !important;
            }
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
                <i class="bi bi-clipboard-data"></i>
                <span>Inventory Reports</span>
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
            <!-- Print-only header (visible only when printing) – matches PDF style -->
            <div class="print-header" style="display:none;">
                <div class="logo-left">
                    <img src="./images/debesmscat.png" alt="DEBESMSCAT Logo" style="width:60px; height:60px;">
                </div>
                <div class="title-block">
                    <div class="main-title">DEBESMSCAT</div>
                    <div class="sub-title">Supply Monitoring System</div>
                    <div class="report-title">Stock Health Report</div>
                    <div class="date">Generated on <?= date('F d, Y H:i') ?></div>
                </div>
                <div class="logo-right">
                    <img src="./images/Bagong_pilipinas.png" alt="Bagong Pilipinas" style="width:60px; height:60px;">
                </div>
                <hr>
            </div>

            <!-- Action Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-0">Stock Health & Reporting</h4>
                    <p class="text-muted small">Monitor inventory levels, restock needs, and valuation.</p>
                </div>
                <div class="d-flex gap-2">
                    <!-- Print Report – triggers browser print, now styled same as PDF button -->
                    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer me-2"></i>Print Report
                    </button>
                    
                    <!-- Export PDF – unchanged -->
                    <a href="print_stock_health_reporting.php" target="_blank" 
                       class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-file-pdf me-2"></i>Export PDF
                    </a>
                </div>
            </div>

            <!-- KPI Cards Row -->
            <div class="row g-4 mb-5">
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-primary">
                        <div>
                            <div class="kpi-value"><?= number_format($kpiData['totalItems']) ?></div>
                            <div class="kpi-label">Total Items</div>
                            <div class="kpi-trend"><?= $kpiData['healthyStock'] ?> healthy</div>
                        </div>
                        <i class="bi bi-box-seam kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-info">
                        <div>
                            <div class="kpi-value"><?= number_format($kpiData['totalStock']) ?></div>
                            <div class="kpi-label">Total Units</div>
                            <div class="kpi-trend">Avg <?= $kpiData['avgStock'] ?>/item</div>
                        </div>
                        <i class="bi bi-boxes kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-success">
                        <div>
                            <div class="kpi-value">₱<?= number_format($kpiData['inventoryValue']/1000, 0) ?>k</div>
                            <div class="kpi-label">Inventory Value</div>
                            <div class="kpi-trend">Total stock worth</div>
                        </div>
                        <i class="bi bi-currency-dollar kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-warning">
                        <div>
                            <div class="kpi-value"><?= $kpiData['lowStock'] ?></div>
                            <div class="kpi-label">Low Stock</div>
                            <div class="kpi-trend">Needs attention</div>
                        </div>
                        <i class="bi bi-exclamation-triangle kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-danger">
                        <div>
                            <div class="kpi-value"><?= $kpiData['outOfStock'] ?></div>
                            <div class="kpi-label">Out of Stock</div>
                            <div class="kpi-trend">Urgent restock</div>
                        </div>
                        <i class="bi bi-x-circle kpi-icon"></i>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="kpi-card kpi-primary">
                        <div>
                            <div class="kpi-value"><?= $kpiData['expiringSoon'] ?></div>
                            <div class="kpi-label">Expiring Soon</div>
                            <div class="kpi-trend">Within 30 days</div>
                        </div>
                        <i class="bi bi-calendar-exclamation kpi-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Report Tabs -->
            <ul class="nav nav-tabs report-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overviewTab">Overview</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#restockTab">Restock Needed</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#expiringTab">Expiring Soon</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fullInventoryTab">Full Inventory</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#historyTab">Adjustment History</a></li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overviewTab">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="card card-modern h-100">
                                <div class="card-header bg-white border-0 pt-4 pb-3">
                                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Stock Level Distribution</h5>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="table-responsive">
                                        <table class="table-modern">
                                            <thead><tr><th>Level</th><th>Items</th><th>Units</th><th>Value</th><th>% of Items</th></tr></thead>
                                            <tbody>
                                                <?php 
                                                $totalItems = $kpiData['totalItems'];
                                                foreach ($stockDistribution as $row): 
                                                    $pct = $totalItems > 0 ? round(($row['count'] / $totalItems) * 100, 1) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= $row['level'] ?></td>
                                                    <td class="fw-semibold"><?= $row['count'] ?></td>
                                                    <td><?= number_format($row['total_units']) ?></td>
                                                    <td>₱<?= number_format($row['total_value'], 2) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div></div>
                                                            <span class="small"><?= $pct ?>%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card card-modern h-100">
                                <div class="card-header bg-white border-0 pt-4 pb-3">
                                    <h5 class="fw-bold mb-0"><i class="bi bi-info-circle me-2 text-info"></i>Summary & Insights</h5>
                                </div>
                                <div class="card-body pt-0">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span>Healthy Stock (≥10)</span>
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2"><?= $kpiData['healthyStock'] ?> items</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span>Needs Restock</span>
                                            <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2"><?= $kpiData['lowStock'] + $kpiData['outOfStock'] ?> items</span>
                                        </li>
                                        <?php if ($kpiData['expiringSoon'] > 0): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span>Expiring within 30 days</span>
                                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2"><?= $kpiData['expiringSoon'] ?> items</span>
                                        </li>
                                        <?php endif; ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span>Recent Adjustments (30d)</span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2"><?= $kpiData['recentAdjustments'] ?> changes</span>
                                        </li>
                                    </ul>
                                    <hr class="my-3">
                                    <p class="text-muted small mb-0"><i class="bi bi-calendar3 me-1"></i>Report generated: <?= date('F d, Y H:i') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Restock Needed Tab -->
                <div class="tab-pane fade" id="restockTab">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-0 pt-4 pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Items Requiring Restock (Stock < 10)</h5>
                            <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2"><?= count($restockItems) ?> items</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table-modern">
                                    <thead><tr><th>Item</th><th>Barcode</th><th>Stock</th><th>Unit Price</th><th>Stock Value</th><th>Urgency</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($restockItems)): ?>
                                            <tr><td colspan="6" class="text-center py-5 text-success"><i class="bi bi-check-circle-fill fs-3 mb-2 d-block"></i>All items are well stocked!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($restockItems as $item): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                <td><code class="text-secondary"><?= htmlspecialchars($item['barcode'] ?? '—') ?></code></td>
                                                <td><span class="badge bg-<?= $item['stock'] == 0 ? 'danger' : 'warning' ?> px-3 py-2"><?= $item['stock'] ?></span></td>
                                                <td>₱<?= number_format($item['price'], 2) ?></td>
                                                <td>₱<?= number_format($item['stock_value'], 2) ?></td>
                                                <td><span class="stock-badge <?= $item['stock'] == 0 ? 'stock-out' : 'stock-low' ?>"><?= $item['stock'] == 0 ? 'OUT' : 'LOW' ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expiring Soon Tab -->
                <div class="tab-pane fade" id="expiringTab">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-0 pt-4 pb-3">
                            <h5 class="fw-bold mb-0"><i class="bi bi-calendar-x text-danger me-2"></i>Items Expiring Within 60 Days</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table-modern">
                                    <thead><tr><th>Item</th><th>Barcode</th><th>Stock</th><th>Expiration Date</th><th>Days Left</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($expiringItems)): ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-calendar-check fs-3 mb-2 d-block"></i>No expiring items found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($expiringItems as $item): ?>
                                                <?php $daysLeft = $item['days_left']; ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                <td><code><?= htmlspecialchars($item['barcode'] ?? '—') ?></code></td>
                                                <td><?= $item['stock'] ?></td>
                                                <td><?= date('M d, Y', strtotime($item['expiration_date'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $daysLeft < 0 ? 'danger' : ($daysLeft <= 30 ? 'warning' : 'info') ?> px-3 py-2">
                                                        <?= $daysLeft < 0 ? 'Expired' : $daysLeft . ' days' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full Inventory Tab -->
                <div class="tab-pane fade" id="fullInventoryTab">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-0 pt-4 pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Complete Inventory List</h5>
                            <div class="d-flex gap-2 no-print">
                                <select class="form-select form-select-sm w-auto rounded-pill" id="sortSelect" onchange="location.href='?sort='+this.value+'&order='+document.getElementById('orderSelect').value">
                                    <option value="name" <?= $sortBy=='name'?'selected':'' ?>>Name</option>
                                    <option value="stock" <?= $sortBy=='stock'?'selected':'' ?>>Stock</option>
                                    <option value="price" <?= $sortBy=='price'?'selected':'' ?>>Price</option>
                                    <option value="barcode" <?= $sortBy=='barcode'?'selected':'' ?>>Barcode</option>
                                </select>
                                <select class="form-select form-select-sm w-auto rounded-pill" id="orderSelect" onchange="location.href='?sort='+document.getElementById('sortSelect').value+'&order='+this.value">
                                    <option value="ASC" <?= $sortOrder=='ASC'?'selected':'' ?>>Ascending</option>
                                    <option value="DESC" <?= $sortOrder=='DESC'?'selected':'' ?>>Descending</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table-modern" id="printableInventory">
                                    <thead>
                                        <tr><th>#</th><th>Item Name</th><th>Barcode</th><th>Stock</th><th>Unit Price</th><th>Stock Value</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fullInventory as $index => $item): ?>
                                        <tr>
                                            <td class="text-secondary"><?= $index + 1 ?></td>
                                            <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($item['barcode'] ?? '—') ?></code></td>
                                            <td class="fw-semibold"><?= $item['stock'] ?></td>
                                            <td>₱<?= number_format($item['price'], 2) ?></td>
                                            <td>₱<?= number_format($item['stock_value'], 2) ?></td>
                                            <td>
                                                <span class="stock-badge <?= 
                                                    $item['stock'] == 0 ? 'stock-out' : 
                                                    ($item['stock'] < 10 ? 'stock-low' : 
                                                    ($item['stock'] < 50 ? 'stock-medium' : 'stock-healthy')) 
                                                ?>"><?= $item['status'] ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3">TOTAL</th>
                                            <th><?= number_format($kpiData['totalStock']) ?> units</th>
                                            <th></th>
                                            <th>₱<?= number_format($kpiData['inventoryValue'], 2) ?></th>
                                            <th><?= $kpiData['totalItems'] ?> items</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Adjustment History Tab -->
                <div class="tab-pane fade" id="historyTab">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-0 pt-4 pb-3">
                            <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-info"></i>Recent Stock Adjustments</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table-modern">
                                    <thead><tr><th>Date</th><th>Item</th><th>Old</th><th>New</th><th>Change</th><th>Type</th><th>Reason</th><th>User</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($adjustmentHistory)): ?>
                                            <tr><td colspan="8" class="text-center py-5 text-muted">No adjustments recorded yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($adjustmentHistory as $adj): ?>
                                            <tr>
                                                <td><?= date('M d, H:i', strtotime($adj['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($adj['item_name']) ?></td>
                                                <td><?= $adj['old_stock'] ?></td>
                                                <td><?= $adj['new_stock'] ?></td>
                                                <td class="<?= $adj['adjustment_type'] == 'increase' ? 'text-success' : 'text-danger' ?> fw-semibold">
                                                    <?= $adj['adjustment_type'] == 'increase' ? '+' : '-' ?><?= $adj['quantity'] ?>
                                                </td>
                                                <td><span class="badge bg-<?= $adj['adjustment_type'] == 'increase' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $adj['adjustment_type'] == 'increase' ? 'success' : 'danger' ?> px-3 py-2"><?= ucfirst($adj['adjustment_type']) ?></span></td>
                                                <td><?= htmlspecialchars($adj['reason'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($adj['username']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
    
    $('.nav-item').on('click', function() {
        if (window.innerWidth <= 1200) {
            closeSidebar();
        }
    });
    
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

    // Tooltip initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function exportInventoryCSV() {
    const rows = [];
    <?php foreach ($fullInventory as $item): ?>
    rows.push([
        "<?= addslashes($item['name']) ?>",
        "<?= addslashes($item['barcode'] ?? '') ?>",
        <?= $item['stock'] ?>,
        <?= $item['price'] ?>,
        <?= $item['stock_value'] ?>,
        "<?= $item['status'] ?>"
    ]);
    <?php endforeach; ?>
    
    let csv = "Item Name,Barcode,Stock,Unit Price,Stock Value,Status\n";
    rows.forEach(r => csv += r.map(c => `"${c}"`).join(',') + '\n');
    
    const blob = new Blob([csv], {type: 'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `inventory_report_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}
</script>
</body>
</html>