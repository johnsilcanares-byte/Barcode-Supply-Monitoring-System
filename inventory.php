<?php
require_once 'config.php';
require_login('admin');
require_once 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorSVG;

$generator = new BarcodeGeneratorSVG();

function generateSimpleBarcode() {
    $timestamp = substr(time(), -6);
    $random = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    return $timestamp . $random;
}

// ==================== AJAX HANDLERS ====================
if (isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name = trim($_POST['name']);
    $stock = intval($_POST['stock']);
    $price = floatval($_POST['price']);
    $expiration_date = !empty(trim($_POST['expiration_date'])) ? trim($_POST['expiration_date']) : null;
    
    if ($expiration_date && strtotime($expiration_date) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success' => false, 'error' => 'Expiration date must be today or in the future']);
        exit;
    }
    
    $barcode = isset($_POST['barcode']) && !empty(trim($_POST['barcode'])) 
        ? trim($_POST['barcode']) 
        : generateSimpleBarcode();
    
    try {
        $checkStmt = $db->prepare("SELECT id FROM inventory WHERE barcode = ?");
        $checkStmt->execute([$barcode]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Barcode already exists in database']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO inventory (name, stock, price, barcode, expiration_date) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $stock, $price, $barcode, $expiration_date]);
        $id = $db->lastInsertId();
        
        try {
            $barcodeSVG = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 2, 60);
            $barcodeDisplay = 'data:image/svg+xml;base64,' . base64_encode($barcodeSVG);
        } catch (Exception $e) {
            $barcodeDisplay = null;
        }
       
        echo json_encode([
            'success' => true,
            'id' => $id,
            'name' => htmlspecialchars($name),
            'stock' => $stock,
            'price' => number_format($price, 2),
            'barcode' => $barcode,
            'barcode_img' => $barcodeDisplay,
            'expiration_date' => $expiration_date ? date('M d, Y', strtotime($expiration_date)) : 'N/A'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to add item: ' . $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'lookup_item') {
    $barcode = trim($_POST['barcode']);
    try {
        $stmt = $db->prepare("SELECT * FROM inventory WHERE barcode = ?");
        $stmt->execute([$barcode]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            echo json_encode([
                'success' => true,
                'found' => true,
                'item' => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'stock' => $item['stock'],
                    'price' => $item['price'],
                    'barcode' => $item['barcode'],
                    'expiration_date' => $item['expiration_date'] ? date('M d, Y', strtotime($item['expiration_date'])) : 'N/A'
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'found' => false, 'barcode' => $barcode]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $id = intval($_POST['id']);
    $stock = intval($_POST['stock']);
    try {
        $stmt = $db->prepare("UPDATE inventory SET stock = ? WHERE id = ?");
        $stmt->execute([$stock, $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update stock']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'generate_barcode') {
    $id = intval($_POST['id']);
    $newBarcode = generateSimpleBarcode();
    
    try {
        $checkStmt = $db->prepare("SELECT id FROM inventory WHERE barcode = ? AND id != ?");
        $checkStmt->execute([$newBarcode, $id]);
        if ($checkStmt->fetch()) $newBarcode = generateSimpleBarcode();
        
        $stmt = $db->prepare("UPDATE inventory SET barcode = ? WHERE id = ?");
        $stmt->execute([$newBarcode, $id]);
        
        try {
            $barcodeSVG = $generator->getBarcode($newBarcode, $generator::TYPE_CODE_128, 2, 60);
            $barcodeDisplay = 'data:image/svg+xml;base64,' . base64_encode($barcodeSVG);
        } catch (Exception $e) {
            $barcodeDisplay = null;
        }
        
        echo json_encode([
            'success' => true,
            'barcode' => $newBarcode,
            'barcode_img' => $barcodeDisplay
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to generate barcode']);
    }
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: inventory.php" . (isset($_GET['page']) ? "?page=".$_GET['page'] : ""));
    exit;
}

// ==================== PAGINATION & DATA ====================
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$search = isset($_GET['search']) ? "%".trim($_GET['search'])."%" : "%";

$countStmt = $db->prepare("SELECT COUNT(*) FROM inventory WHERE name LIKE ? OR barcode LIKE ?");
$countStmt->execute([$search, $search]);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

$sql = "SELECT * FROM inventory WHERE name LIKE ? OR barcode LIKE ? ORDER BY id ASC LIMIT " . (int)$itemsPerPage . " OFFSET " . (int)$offset;
$stmt = $db->prepare($sql);
$stmt->execute([$search, $search]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== PAGE RENDERING ====================
$page_title = 'Inventory Management';
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

        /* Cards and Forms */
        .card-modern {
            background: white;
            border-radius: var(--border-radius-card);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .card-modern:hover {
            box-shadow: var(--card-hover-shadow);
        }

        /* Scanner & Barcode Styles */
        .camera-view { position: relative; width: 100%; height: 350px; background: #000; border-radius: 16px; overflow: hidden; }
        #scanner-container { width: 100%; height: 100%; }
        #scanner-container video, #scanner-container canvas { width: 100% !important; height: 100% !important; object-fit: cover; position: absolute; top: 0; left: 0; }
        .scan-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 2px solid #10b981; width: 280px; height: 100px; border-radius: 12px; background: rgba(16,185,129,0.1); z-index: 10; }
        .scan-line { position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #10b981; animation: scan 2s linear infinite; }
        @keyframes scan { 0% { transform: translateY(-50px); opacity: 0; } 50% { opacity: 1; } 100% { transform: translateY(50px); opacity: 0; } }
        .scan-stats { background: linear-gradient(135deg, #16a34a, #059669); color: white; border-radius: 12px; padding: 0.5rem 1rem; }
        .beep-indicator { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 40px; z-index: 9999; display: none; box-shadow: 0 4px 12px rgba(16,185,129,0.4); }
        .scan-result-item { border-left: 4px solid #10b981; background: #f9fafb; margin-bottom: 8px; padding: 12px; border-radius: 8px; }
        .barcode-cell { display: flex; align-items: center; gap: 8px; }
        .barcode-info { display: flex; flex-direction: column; }
        .barcode-img { transition: transform 0.2s; }
        .barcode-img:hover { transform: scale(1.05); }

        /* Form controls and buttons – green theme */
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22,163,74,0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #16a34a, #059669);
            border: none;
            border-radius: 40px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #15803d, #047857);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(22,163,74,0.35);
            color: white;
        }
        .btn-outline-primary {
            border-radius: 40px;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #16a34a, #059669);
            border-color: transparent;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(22,163,74,0.3);
        }
        .btn-outline-secondary {
            border-radius: 40px;
            border: 1.5px solid #94a3b8;
            color: #475569;
        }
        .btn-outline-secondary:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }
        .btn-danger {
            border-radius: 40px;
        }
        .btn-warning {
            border-radius: 40px;
        }
        .btn-info {
            border-radius: 40px;
            background: #0ea5e9;
            border: none;
        }
        .btn-success {
            border-radius: 40px;
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
            .sidebar-logo { width: 70px; height: 70px; }
            .sidebar-brand h4 { font-size: 0.85rem; }
            .sidebar-brand { padding: 1rem; }
            .nav-item { padding: 0.6rem 0.8rem; }
            .logout-btn { padding: 0.6rem 0.8rem; }
        }
        
        @media (max-width: 576px) {
            .content-wrapper { padding: 1rem; }
            .sidebar-logo { width: 60px; height: 60px; }
        }
        
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
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
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
        <!-- Topbar -->
        <div class="topbar">
            <div class="page-title">
                <i class="bi bi-box-seam"></i>
                <span>Inventory Management</span>
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
            <!-- Alert -->
            <div id="alertMessage" class="alert alert-dismissible fade show mb-4" role="alert" style="display: none;">
                <span id="alertText"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <div id="beepIndicator" class="beep-indicator"></div>

            <!-- Add Item Card -->
            <div class="card card-modern mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Product</h5>
                    <p class="text-muted small">Fill in details or use the scanner to auto‑fill barcode.</p>
                </div>
                <div class="card-body">
                    <form id="addForm" class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="itemName" name="name" placeholder="Item Name" required>
                                <label for="itemName">Item Name</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="itemStock" name="stock" placeholder="Stock" min="0" required>
                                <label for="itemStock">Stock</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating">
                                <input type="number" step="0.01" class="form-control" id="itemPrice" name="price" placeholder="0.00" min="0" required>
                                <label for="itemPrice">Price (₱)</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="expirationDate" name="expiration_date" placeholder="Expiration">
                                <label for="expirationDate">Expiration (opt)</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 h-100 d-flex align-items-center justify-content-center gap-2" id="addButton">
                                <span class="spinner-border spinner-border-sm d-none" id="addSpinner"></span>
                                <i class="bi bi-plus-lg"></i> Add Product
                            </button>
                        </div>
                    </form>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-primary" id="scannerBtn">
                            <i class="bi bi-upc-scan me-2"></i>Open Scanner (Stock Management)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <form method="get" class="d-flex flex-grow-1 me-3">
                    <div class="input-group" style="max-width: 400px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or barcode" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                    <?php if(isset($_GET['page'])): ?><input type="hidden" name="page" value="<?= $_GET['page'] ?>"><?php endif; ?>
                </form>
                <div class="text-muted"><i class="bi bi-database me-1"></i> <?= $totalItems ?> total items</div>
            </div>

            <!-- Inventory Table -->
            <div class="card card-modern">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th><th>Name</th><th>Stock</th><th>Price</th><th>Expiration</th><th>Barcode</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($items)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox display-4 d-block mb-3"></i>No items found</td></tr>
                        <?php else: ?>
                            <?php
                            $today = new DateTime();
                            $warningDate = (new DateTime())->modify('+7 days');
                            ?>
                            <?php foreach($items as $item): ?>
                                <?php
                                $expClass = 'bg-success';
                                $expText = $item['expiration_date'] ? date('M d, Y', strtotime($item['expiration_date'])) : 'N/A';
                                if ($item['expiration_date']) {
                                    $expDate = new DateTime($item['expiration_date']);
                                    if ($expDate < $today) $expClass = 'bg-danger';
                                    elseif ($expDate <= $warningDate) $expClass = 'bg-warning text-dark';
                                }
                                ?>
                                <tr data-id="<?= $item['id'] ?>">
                                    <td><span class="fw-semibold">#<?= $item['id'] ?></span></td>
                                    <td class="fw-medium"><?= htmlspecialchars($item['name']) ?></td>
                                    <td>
                                        <?php if($item['stock'] <= 5): ?>
                                            <span class="badge bg-danger"><?= $item['stock'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $item['stock'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>₱<?= number_format($item['price'], 2) ?></strong></td>
                                    <td><span class="badge <?= $expClass ?>"><?= $expText ?></span></td>
                                    <td>
                                        <div class="barcode-cell">
                                            <?php
                                            try {
                                                $barcodeSVG = $generator->getBarcode($item['barcode'], $generator::TYPE_CODE_128, 2, 50);
                                                $b64 = base64_encode($barcodeSVG);
                                                echo '<img class="barcode-img" src="data:image/svg+xml;base64,'.$b64.'" alt="barcode" onclick="showBarcodeModal(\''.htmlspecialchars($item['barcode']).'\', \''.$b64.'\')" style="cursor:pointer; max-height:40px;">';
                                            } catch (Exception $e) {
                                                echo '<span class="text-danger small">Error</span>';
                                            }
                                            ?>
                                            <div class="barcode-info">
                                                <code class="small"><?= $item['barcode'] ?></code>
                                                <button type="button" class="btn btn-link btn-sm p-0 ms-1 regenerate-barcode-btn" data-id="<?= $item['id'] ?>" title="Regenerate barcode">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-danger delete-item-btn" data-id="<?= $item['id'] ?>" data-page="<?= isset($_GET['page']) ? $_GET['page'] : '' ?>" data-search="<?= isset($_GET['search']) ? urlencode($_GET['search']) : '' ?>">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">Showing <?= min($offset+1, $totalItems) ?> to <?= min($offset+$itemsPerPage, $totalItems) ?> of <?= $totalItems ?></div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPage<=1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $currentPage-1 ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>"><i class="bi bi-chevron-left"></i></a></li>
                        <?php for($i=max(1,$currentPage-2); $i<=min($totalPages,$currentPage+2); $i++): ?>
                            <li class="page-item <?= $i==$currentPage ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage>=$totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $currentPage+1 ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>"><i class="bi bi-chevron-right"></i></a></li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Scanner Modal (fully functional) -->
<div id="scannerModal" class="modal fade" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upc-scan"></i> Barcode Scanner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="btn-group">
                        <button id="startScanBtn" class="btn btn-success"><i class="bi bi-play-fill"></i> Start</button>
                        <button id="stopScanBtn" class="btn btn-danger" disabled><i class="bi bi-stop-fill"></i> Stop</button>
                        <button id="switchCameraBtn" class="btn btn-info" disabled><i class="bi bi-camera-reels"></i> Switch</button>
                    </div>
                    <div class="scan-stats"><small>Scanned: <span id="scanCount">0</span> | Found: <span id="foundCount">0</span></small></div>
                </div>
                <div class="camera-view mb-3">
                    <div id="scanner-container"></div>
                    <div class="scan-overlay"><div class="scan-line"></div></div>
                </div>
                <div class="card bg-light p-3">
                    <h6><i class="bi bi-keyboard"></i> Manual Entry</h6>
                    <div class="input-group">
                        <input type="text" id="manualBarcode" class="form-control" placeholder="Enter barcode">
                        <button id="manualScanBtn" class="btn btn-secondary"><i class="bi bi-search"></i> Lookup</button>
                    </div>
                </div>
                <div id="scanResults" class="mt-3" style="max-height:200px; overflow-y:auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="clearResultsBtn" class="btn btn-warning"><i class="bi bi-arrow-clockwise"></i> Clear</button>
            </div>
        </div>
    </div>
</div>

<!-- Barcode View Modal -->
<div id="barcodeModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-upc"></i> Barcode</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <img id="barcodeModalImage" class="img-fluid p-3 bg-white border rounded" src="" alt="Barcode">
                <div id="barcodeModalText" class="mt-2 fw-bold font-monospace"></div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Update Modal (for scanner found item) -->
<div id="updateStockModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="updateItemId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Item Name</label>
                    <input type="text" id="updateItemName" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Current Stock</label>
                    <input type="text" id="updateCurrentStock" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Stock Quantity</label>
                    <input type="number" id="updateNewStock" class="form-control" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmUpdateStockBtn" class="btn btn-primary">Update Stock</button>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
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

    // ========== REPLACE CONFIRM/ALERT WITH SWEETALERT2 ==========
    
    // Delete item with SweetAlert2
    $(document).on('click', '.delete-item-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const itemId = $btn.data('id');
        const page = $btn.data('page');
        const search = $btn.data('search');
        let deleteUrl = `?delete=${itemId}`;
        if (page) deleteUrl += `&page=${page}`;
        if (search) deleteUrl += `&search=${search}`;
        
        Swal.fire({
            title: 'Delete Item?',
            text: 'This action cannot be undone. Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Yes, delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    });
    
    // Regenerate barcode with SweetAlert2
    $(document).on('click', '.regenerate-barcode-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const id = $btn.data('id');
        
        Swal.fire({
            title: 'Generate New Barcode?',
            text: 'The current barcode will be replaced. This action can be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Yes, regenerate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Generating...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                $.post('inventory.php', { action: 'generate_barcode', id: id }, function(resp) {
                    if (resp.success) {
                        const row = $(`tr[data-id="${id}"]`);
                        row.find('.barcode-cell').html(`
                            <img class="barcode-img" src="${resp.barcode_img}" onclick="showBarcodeModal('${resp.barcode}', '${resp.barcode_img.split(',')[1]}')" style="cursor:pointer; max-height:40px;">
                            <div class="barcode-info">
                                <code class="small">${resp.barcode}</code>
                                <button class="btn btn-link btn-sm p-0 regenerate-barcode-btn" data-id="${id}"><i class="bi bi-arrow-repeat"></i></button>
                            </div>
                        `);
                        Swal.fire({
                            title: 'Success!',
                            text: 'Barcode regenerated successfully',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', resp.error || 'Failed to regenerate barcode', 'error');
                    }
                }, 'json');
            }
        });
    });
    
    // ========== SCANNER MODAL LOGIC (updated to use SweetAlert2 for confirm/prompt) ==========
    let isScanning = false, lastScanTime = 0, currentFacingMode = 'user';
    let scanStats = { scanned: 0, found: 0 }, scanResults = [];

    window.showBarcodeModal = function(barcode, b64) {
        $('#barcodeModalImage').attr('src', 'data:image/svg+xml;base64,' + b64);
        $('#barcodeModalText').text(barcode);
        $('#barcodeModal').modal('show');
    };

    // Add form
    $('#addForm').on('submit', function(e){
        e.preventDefault();
        const expDate = $('input[name="expiration_date"]').val();
        if (expDate && new Date(expDate) < new Date(new Date().setHours(0,0,0,0))) {
            Swal.fire('Invalid Date', 'Expiration date must be today or in the future', 'error');
            return;
        }
        $('#addSpinner').removeClass('d-none');
        $('#addButton').prop('disabled', true);
        let formData = $(this).serialize() + '&action=add_item';
        const scannedBarcode = $(this).data('scanned-barcode');
        if (scannedBarcode) {
            formData += '&barcode=' + encodeURIComponent(scannedBarcode);
            $(this).removeData('scanned-barcode');
        }
        $.post('inventory.php', formData, function(resp) {
            if (resp.success) {
                $('#addForm')[0].reset();
                Swal.fire('Success', 'Item added: ' + resp.barcode, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', resp.error || 'Failed to add item', 'error');
            }
        }, 'json').always(() => { $('#addSpinner').addClass('d-none'); $('#addButton').prop('disabled', false); });
    });

    // Scanner
    $('#scannerBtn').click(() => $('#scannerModal').modal('show'));
    $('#scannerModal').on('hidden.bs.modal', () => { if (isScanning) stopScanner(); });
    $('#startScanBtn').click(startScanner);
    $('#stopScanBtn').click(stopScanner);
    $('#switchCameraBtn').click(() => { if (isScanning) { currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user'; stopScanner(); setTimeout(startScanner, 500); } });
    $('#manualScanBtn').click(() => { let code = $('#manualBarcode').val().trim(); if (code) { processBarcode(code, true); $('#manualBarcode').val(''); } });
    $('#manualBarcode').keypress(e => { if (e.which === 13) $('#manualScanBtn').click(); });
    $('#clearResultsBtn').click(() => { scanResults = []; scanStats = { scanned:0, found:0 }; updateScanUI(); });

    function startScanner() {
        if (isScanning) return;
        $('#startScanBtn').prop('disabled', true);
        $('#stopScanBtn, #switchCameraBtn').prop('disabled', false);
        Quagga.init({
            inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#scanner-container'), constraints: { facingMode: currentFacingMode } },
            decoder: { readers: ["code_128_reader", "ean_reader", "code_39_reader", "upc_reader"] },
            locate: true,
            frequency: 5
        }, err => {
            if (err) { Swal.fire('Camera Error', 'Could not access camera', 'error'); resetScannerBtns(); return; }
            Quagga.start();
            isScanning = true;
        });
        Quagga.onDetected(result => {
            const code = result.codeResult.code;
            const now = Date.now();
            if (!code || code.length < 6 || now - lastScanTime < 1500) return;
            lastScanTime = now;
            processBarcode(code, false);
            playBeep();
            $('#beepIndicator').show().delay(800).fadeOut();
        });
    }

    function stopScanner() { if (isScanning) { Quagga.stop(); isScanning = false; } resetScannerBtns(); }
    function resetScannerBtns() { $('#startScanBtn').prop('disabled', false); $('#stopScanBtn, #switchCameraBtn').prop('disabled', true); }

    function processBarcode(code, manual) {
        scanStats.scanned++;
        updateScanStats();
        $.post('inventory.php', { action: 'lookup_item', barcode: code }, resp => {
            if (resp.success) {
                if (resp.found) { scanStats.found++; handleFound(resp.item, code, manual); }
                else handleNotFound(code, manual);
            }
            updateScanStats();
            updateScanUI();
        }, 'json');
    }

    // SweetAlert2 based stock update instead of confirm+prompt
    function handleFound(item, code, manual) {
        Swal.fire({
            title: 'Item Found',
            html: `<strong>${item.name}</strong><br>Current Stock: <strong>${item.stock}</strong><br><br>Do you want to update the stock?`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-pencil-square"></i> Update Stock',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#16a34a'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show modal to input new stock
                Swal.fire({
                    title: 'Update Stock',
                    html: `
                        <div style="text-align:left">
                            <label class="form-label fw-semibold">Item: ${item.name}</label>
                            <input type="number" id="newStockValue" class="swal2-input" placeholder="New Stock" value="${item.stock}" min="0" step="1" style="width:100%">
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Update',
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const newStock = parseInt(document.getElementById('newStockValue').value, 10);
                        if (isNaN(newStock) || newStock < 0) {
                            Swal.showValidationMessage('Please enter a valid non-negative number');
                            return false;
                        }
                        return newStock;
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value !== undefined) {
                        const newStock = result.value;
                        if (newStock !== item.stock) {
                            $.post('inventory.php', { action: 'update_stock', id: item.id, stock: newStock }, resp => {
                                if (resp.success) {
                                    $(`tr[data-id="${item.id}"] td:nth-child(3)`).html(newStock <= 5 ? `<span class="badge bg-danger">${newStock}</span>` : `<span class="badge bg-success">${newStock}</span>`);
                                    Swal.fire('Updated', `Stock changed from ${item.stock} to ${newStock}`, 'success');
                                    addResult(code, item, manual, true, `Updated ${item.stock}→${newStock}`);
                                } else {
                                    Swal.fire('Error', 'Failed to update stock', 'error');
                                }
                            }, 'json');
                        } else {
                            addResult(code, item, manual, true, 'Viewed (no change)');
                        }
                    } else {
                        addResult(code, item, manual, true, 'Viewed');
                    }
                });
            } else {
                addResult(code, item, manual, true, 'Viewed');
            }
        });
    }

    function handleNotFound(code, manual) {
        Swal.fire({
            title: 'Barcode Not Found',
            text: `No item found with barcode: ${code}`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-plus-lg"></i> Add as New Product',
            cancelButtonText: 'Close'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#scannerModal').modal('hide');
                $('input[name="name"]').focus();
                $('#addForm').data('scanned-barcode', code);
                Swal.fire('Ready', 'Fill in the product details and submit. Barcode will be auto-filled.', 'info');
            }
            addResult(code, null, manual, false, 'Not Found');
        });
    }

    function addResult(barcode, item, manual, found, action) {
        scanResults.unshift({ barcode, item, timestamp: new Date(), manual, found, action });
        updateScanUI();
    }

    function updateScanUI() {
        let html = scanResults.slice(0,8).map(r => `
            <div class="scan-result-item">
                <div class="d-flex justify-content-between">
                    <strong>${r.found ? r.item.name : 'Unknown'}</strong>
                    <small>${r.timestamp.toLocaleTimeString()}</small>
                </div>
                <code>${r.barcode}</code>
                <span class="badge bg-${r.found ? 'success' : 'danger'}">${r.action}</span>
            </div>
        `).join('');
        $('#scanResults').html(html || '<div class="text-muted text-center p-3">No scans</div>');
    }

    function updateScanStats() { $('#scanCount').text(scanStats.scanned); $('#foundCount').text(scanStats.found); }
    function playBeep() { try { let a = new (window.AudioContext||window.webkitAudioContext)(); let o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value=800; g.gain.setValueAtTime(0.3,a.currentTime); g.gain.exponentialRampToValueAtTime(0.01,a.currentTime+0.1); o.start(); o.stop(a.currentTime+0.1); } catch(e){} }
    function showAlert(msg, type) {
        // kept for compatibility, but we now use Swal for most actions
        $('#alertMessage').removeClass('alert-success alert-danger alert-warning alert-info').addClass('alert-'+type);
        $('#alertText').text(msg);
        $('#alertMessage').show();
        setTimeout(() => $('#alertMessage').fadeOut(), 4000);
    }
});
</script>
</body>
</html>