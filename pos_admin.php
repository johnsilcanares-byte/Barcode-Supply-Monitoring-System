<?php
session_start();
require_once 'config.php';

// Access control: admin and user only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    die("❌ Access denied.");
}

// Ensure stock_adjustments table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS stock_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        old_stock INT NOT NULL,
        new_stock INT NOT NULL,
        quantity INT NOT NULL,
        adjustment_type ENUM('increase','decrease') NOT NULL,
        reason VARCHAR(255),
        adjusted_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Failed to create stock_adjustments table: " . $e->getMessage());
}

// Handle manual stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $item_id = (int)$_POST['item_id'];
    $adjustment_type = $_POST['adjustment_type'] ?? 'decrease';
    $quantity = (int)$_POST['quantity'];
    $reason = trim($_POST['reason'] ?? 'Manual adjustment');
    
    if ($quantity <= 0) {
        $error_message = "Quantity must be positive.";
    } else {
        $stmt = $db->prepare("SELECT id, name, stock, price, barcode FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $old_stock = $item['stock'];
            $new_stock = $adjustment_type === 'increase' ? $old_stock + $quantity : $old_stock - $quantity;
            
            if ($new_stock < 0) {
                $error_message = "Stock cannot be negative. Current stock: {$old_stock}.";
            } else {
                try {
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("UPDATE inventory SET stock = ? WHERE id = ?");
                    $stmt->execute([$new_stock, $item_id]);
                    
                    $logStmt = $db->prepare("INSERT INTO stock_adjustments (item_id, old_stock, new_stock, quantity, adjustment_type, reason, adjusted_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $logStmt->execute([$item_id, $old_stock, $new_stock, $quantity, $adjustment_type, $reason, $_SESSION['user_id']]);
                    
                    $db->commit();
                    $success_message = "Stock updated: {$old_stock} → {$new_stock} for '{$item['name']}'.";
                    
                    // Refresh item details after adjustment
                    $itemDetails = $item;
                    $itemDetails['stock'] = $new_stock;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error_message = "Failed to update stock: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "Item not found.";
        }
    }
}

// Fetch all inventory items for dropdown
$items = $db->query("SELECT id, name, stock, price, barcode FROM inventory ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Barcode lookup (manual entry or camera scan) – NO auto-adjust
$itemDetails = null;
$scannedBarcode = null;
if (isset($_POST['barcode']) || isset($_POST['camera_barcode'])) {
    $barcode = $_POST['barcode'] ?? $_POST['camera_barcode'];
    $scannedBarcode = $barcode;
    $stmt = $db->prepare("SELECT id, name, stock, price, barcode FROM inventory WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $itemDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$itemDetails) {
        $error_message = "No item found with barcode: " . htmlspecialchars($barcode);
    }
}

// Recent adjustments for activity log
try {
    $recentAdjustments = $db->query("
        SELECT sa.*, i.name AS item_name, u.username 
        FROM stock_adjustments sa 
        JOIN inventory i ON sa.item_id = i.id 
        JOIN users u ON sa.adjusted_by = u.id 
        ORDER BY sa.created_at DESC 
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentAdjustments = [];
}

$page_title = 'Stock Adjustment';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">
    <title><?= $page_title ?> · Inventory Monitor</title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            /* ── Dark Green Palette ── */
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
            /* Sidebar: deep forest greens */
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

        /* ========== SIDEBAR - FULLY RESPONSIVE, ALL ITEMS VISIBLE ========== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: rgba(255,255,255,0.92);
            height: 100vh;                     /* fixed viewport height */
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
            overflow: hidden;                  /* prevent outer scroll */
        }
        
        /* Sidebar Brand - stays at top */
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
            overflow-y: auto;                 /* enables scroll when many items */
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
        
        .logout-btn-side {
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
        
        .logout-btn-side:hover {
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

        /* Custom scrollbar — green tones */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f0fdf4; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #16a34a, #059669); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #15803d, #047857); }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1055;
            display: none;
        }

        /* Main Layout */
        .app-main {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }
        
        /* Alerts */
        .alert-wrapper {
            grid-column: 1 / -1;
        }
        .alert-custom {
            border: none;
            border-radius: 60px;
            padding: 12px 24px;
            font-weight: 500;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(8px);
        }
        .alert-custom.success { background: linear-gradient(135deg, #16a34a, #059669); color: white; }
        .alert-custom.danger  { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        
        /* Left Panel */
        .left-panel {
            background: white;
            border-radius: var(--border-radius-card);
            box-shadow: var(--card-shadow);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
            border: 1px solid rgba(22,163,74,0.1);
        }
        
        /* Scanner Card */
        .scanner-card {
            background: linear-gradient(145deg, #f0fdf4, #ffffff);
            border-radius: 18px;
            padding: 20px;
            border: 1px solid rgba(22,163,74,0.15);
        }
        .camera-viewport {
            background: #0f172a;
            border-radius: 16px;
            height: 240px;
            width: 100%;
            max-width: 380px;
            margin: 0 auto 16px;
            position: relative;
            border: 3px solid var(--primary);
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(22,163,74,0.2);
        }
        .camera-viewport.active { border-color: var(--success); box-shadow: 0 4px 16px rgba(16,185,129,0.3); }
        #camera-preview { width: 100%; height: 100%; object-fit: cover; }
        .camera-overlay {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 80%; height: 30%;
            border: 2px solid var(--success);
            box-shadow: 0 0 0 1000px rgba(0,0,0,0.3);
            pointer-events: none;
            display: none;
        }
        .camera-viewport.active .camera-overlay { display: block; }
        .scanner-toggle {
            background: linear-gradient(135deg, #16a34a, #059669);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 10px rgba(22,163,74,0.3);
        }
        .scanner-toggle:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(22,163,74,0.4); }
        .scanner-toggle.active { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 10px rgba(239,68,68,0.3); }
        .scanner-status {
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 40px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-inactive { background: #dcfce7; color: #15803d; }
        .status-active   { background: linear-gradient(135deg, #16a34a, #059669); color: white; }
        .status-scanning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .status-error    { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        
        /* Input Cards */
        .input-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid rgba(22,163,74,0.12);
            transition: all 0.3s ease;
        }
        
        .input-card:hover {
            box-shadow: 0 4px 16px rgba(22,163,74,0.1);
            border-color: rgba(22,163,74,0.3);
        }
        
        /* Right Panel */
        .right-panel {
            background: white;
            border-radius: var(--border-radius-card);
            box-shadow: var(--card-shadow);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
            border: 1px solid rgba(22,163,74,0.1);
        }
        .item-detail-card {
            background: linear-gradient(145deg, #f0fdf4, #ffffff);
            border-radius: 18px;
            padding: 20px;
            border: 1px solid rgba(22,163,74,0.15);
        }
        .adjustment-card {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(22,163,74,0.06);
            border: 1px solid rgba(22,163,74,0.12);
        }
        .activity-log {
            max-height: 350px;
            overflow-y: auto;
        }
        .log-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0fdf4;
            transition: all 0.3s ease;
        }
        
        .log-item:hover {
            background: rgba(22,163,74,0.04);
            padding-left: 8px;
            border-radius: 12px;
        }
        
        .log-badge {
            padding: 4px 10px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .log-badge.increase { background: #dcfce7; color: #15803d; }
        .log-badge.decrease { background: #fee2e2; color: #991b1b; }
        
        /* Form elements */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1.5px solid #d1fae5;
            padding: 10px 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22,163,74,0.15);
            outline: none;
        }

        /* Buttons — green gradient */
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

        /* Stock badge */
        .badge-stock {
            background: linear-gradient(135deg, #dcfce7, #d1fae5);
            color: #15803d;
            padding: 6px 14px;
            border-radius: 40px;
            font-weight: 700;
            border: 1px solid rgba(22,163,74,0.2);
        }

        /* Camera switch */
        .btn-switch-camera {
            background: linear-gradient(135deg, #475569, #334155);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-left: 8px;
        }
        .btn-switch-camera:hover {
            background: linear-gradient(135deg, #334155, #1e293b);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .btn-switch-camera:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }

        .scan-indicator {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: none;
        }
        .camera-viewport.active .scan-indicator { display: block; }

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
            .app-main { grid-template-columns: 1fr; }
            .topbar { padding: 0 1.2rem; }
            .content-wrapper { padding: 1.2rem; }
        }
        
        @media (max-width: 768px) {
            .user-greeting { display: none; }
            .page-title { font-size: 1.2rem; }
            .camera-viewport { height: 200px; }
            .topbar { padding: 0 1rem; }
            .sidebar-logo { width: 70px; height: 70px; }
            .sidebar-brand h4 { font-size: 0.85rem; }
            .sidebar-brand { padding: 1rem; }
            .nav-item { padding: 0.6rem 0.8rem; }
            .logout-btn-side { padding: 0.6rem 0.8rem; }
        }
        
        @media (max-width: 576px) {
            .content-wrapper { padding: 1rem; }
            .left-panel, .right-panel { padding: 15px; }
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
            <div class="brand-badge"><?= ucfirst($_SESSION['role'] ?? 'User') ?> Portal</div>
        </div>
        
        <div class="sidebar-nav">
            <?php if ($_SESSION['role'] === 'admin'): ?>
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
            <?php endif; ?>
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
                <i class="fas fa-boxes"></i>
                <span>Stock Adjustment</span>
            </div>
            <div class="user-menu">
                <span class="user-greeting">
                    <i class="fas fa-user-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                </span>
               
                <!-- Burger Menu Button -->
                <button class="menu-toggle" id="menuToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Alert Wrapper -->
            <div class="alert-wrapper mb-3">
                <?php if (isset($success_message)): ?>
                    <div class="alert-custom success" id="alert-message">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    </div>
                <?php elseif (isset($error_message)): ?>
                    <div class="alert-custom danger" id="alert-message">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted small py-1">Scan a barcode or select an item</div>
                <?php endif; ?>
            </div>

            <!-- Main Grid -->
            <div class="app-main">
                <!-- Left Panel: Scanner & Input -->
                <div class="left-panel">
                    <!-- Scanner Section -->
                    <div class="scanner-card">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="fw-bold mb-0"><i class="fas fa-camera me-2" style="color:var(--primary)"></i>Barcode Scanner</h5>
                            <div class="d-flex align-items-center gap-2">
                                <span id="scanner-status" class="scanner-status status-inactive">Inactive</span>
                                <button id="scanner-toggle" class="scanner-toggle"><i class="fas fa-play me-1"></i>Start</button>
                                <button id="switch-camera" class="btn-switch-camera" disabled><i class="fas fa-sync-alt me-1"></i>Switch</button>
                            </div>
                        </div>
                        <div class="camera-viewport" id="camera-viewport">
                            <video id="camera-preview" autoplay playsinline muted></video>
                            <div class="camera-overlay"></div>
                            <div class="scan-indicator" id="scan-indicator">
                                <i class="fas fa-barcode me-1"></i><span id="scan-feedback">Ready to scan</span>
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-2"><i class="fas fa-lightbulb me-1"></i>Hold barcode steady - scanner works even with unclear barcodes</p>
                        <canvas id="barcode-canvas" style="display:none;"></canvas>
                    </div>

                    <!-- Manual Barcode Entry -->
                    <div class="input-card">
                        <h6 class="fw-semibold mb-3"><i class="fas fa-keyboard me-2" style="color:var(--primary)"></i>Manual Entry</h6>
                        <form method="post">
                            <div class="input-group">
                                <input type="text" name="barcode" class="form-control" placeholder="Type or paste barcode" autofocus>
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Lookup</button>
                            </div>
                        </form>
                    </div>

                    <!-- Select Item from List -->
                    <div class="input-card">
                        <h6 class="fw-semibold mb-3"><i class="fas fa-list-ul me-2" style="color:var(--primary)"></i>Select Item</h6>
                        <form method="post">
                            <select name="barcode" class="form-select mb-3" required>
                                <option value="">Choose an item...</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= htmlspecialchars($item['barcode']) ?>">
                                        <?= htmlspecialchars($item['name']) ?> (Stock: <?= $item['stock'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary w-100">Load Item Details</button>
                        </form>
                    </div>
                </div>

                <!-- Right Panel: Details, Adjustment & Log -->
                <div class="right-panel">
                    <?php if (isset($itemDetails) && $itemDetails): ?>
                        <div class="item-detail-card">
                            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                                <div>
                                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($itemDetails['name']) ?></h4>
                                    <code class="text-secondary"><?= htmlspecialchars($itemDetails['barcode']) ?></code>
                                </div>
                                <span class="badge-stock">
                                    <i class="fas fa-cubes me-1"></i>Stock: <?= $itemDetails['stock'] ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <small class="text-secondary text-uppercase">Price</small>
                                <div class="fs-4 fw-bold" style="color:var(--primary)">₱<?= number_format($itemDetails['price'], 2) ?></div>
                            </div>

                            <!-- Adjustment Form -->
                            <div class="adjustment-card">
                                <h6 class="fw-bold mb-3"><i class="fas fa-sliders-h me-2" style="color:var(--primary)"></i>Adjust Stock</h6>
                                <form method="post">
                                    <input type="hidden" name="item_id" value="<?= $itemDetails['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Adjustment Type</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="adjustment_type" id="decrease" value="decrease" checked>
                                            <label class="btn btn-outline-danger" for="decrease"><i class="fas fa-minus-circle me-1"></i>Decrease</label>
                                            <input type="radio" class="btn-check" name="adjustment_type" id="increase" value="increase">
                                            <label class="btn btn-outline-success" for="increase"><i class="fas fa-plus-circle me-1"></i>Increase</label>
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <label class="form-label fw-semibold">Quantity</label>
                                            <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-semibold">Reason</label>
                                            <select class="form-select" name="reason">
                                                <option value="Stock count">Stock count</option>
                                                <option value="Received shipment">Received</option>
                                                <option value="Damaged/Expired">Damaged</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" name="adjust_stock" class="btn btn-primary w-100 py-2">
                                        <i class="fas fa-check me-2"></i>Apply Adjustment
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif (isset($scannedBarcode) && !$itemDetails): ?>
                        <div class="alert alert-warning border-0 rounded-4 p-4">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            No item found with barcode <strong><?= htmlspecialchars($scannedBarcode) ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-upc-scan fs-1 mb-3 opacity-50"></i>
                            <h5>Ready to adjust stock</h5>
                            <p class="small">Scan a barcode, enter manually, or select from list</p>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Activity Log -->
                    <div class="input-card">
                        <h6 class="fw-semibold mb-3"><i class="fas fa-history me-2" style="color:var(--primary)"></i>Recent Adjustments</h6>
                        <div class="activity-log">
                            <?php if (empty($recentAdjustments)): ?>
                                <p class="text-muted text-center py-3">No adjustments recorded.</p>
                            <?php else: ?>
                                <?php foreach ($recentAdjustments as $log): ?>
                                    <div class="log-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($log['item_name']) ?></strong>
                                            <div class="small text-secondary">
                                                <?= $log['old_stock'] ?> → <?= $log['new_stock'] ?> 
                                                (<?= $log['adjustment_type'] == 'increase' ? '+' : '-' ?><?= $log['quantity'] ?>)
                                            </div>
                                            <div class="small text-secondary">
                                                <?= htmlspecialchars($log['username']) ?> · <?= date('M d, H:i', strtotime($log['created_at'])) ?>
                                            </div>
                                        </div>
                                        <span class="log-badge <?= $log['adjustment_type'] == 'increase' ? 'increase' : 'decrease' ?>">
                                            <?= $log['adjustment_type'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Hidden audio element for beep sound -->
<audio id="beep-sound" preload="auto" style="display: none;">
    <source src="sounds/beep.mp3" type="audio/mpeg">
    <source src="sounds/beep.wav" type="audio/wav">
    <source src="sounds/beep.ogg" type="audio/ogg">
</audio>
<audio id="beep-fallback" preload="auto" style="display: none;">
    <source src="sounds/beep.mp3" type="audio/mpeg">
</audio>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

let cameraActive = false, lastScannedCode = '', scanCooldown = false, currentStream = null;
let currentFacingMode = 'environment';
let scanAttempts = 0;
let lastScanTime = 0;
const SCAN_COOLDOWN_MS = 2000;
const MIN_SCAN_CONFIDENCE = 0.6;

const scannerToggle = document.getElementById('scanner-toggle');
const switchCameraBtn = document.getElementById('switch-camera');
const cameraPreview = document.getElementById('camera-preview');
const scannerStatus = document.getElementById('scanner-status');
const cameraViewport = document.querySelector('.camera-viewport');
const scanFeedback = document.getElementById('scan-feedback');

const beepSound = document.getElementById('beep-sound');
const beepFallback = document.getElementById('beep-fallback');

function loadBeepSound() {
    beepSound.load();
    beepFallback.load();
}
loadBeepSound();

function setScannerStatus(text, type = 'inactive') {
    scannerStatus.textContent = text;
    scannerStatus.className = `scanner-status status-${type}`;
}

function updateScanFeedback(text, isSuccess = false) {
    if (scanFeedback) {
        scanFeedback.textContent = text;
        if (isSuccess) {
            scanFeedback.style.color = '#10b981';
            setTimeout(() => scanFeedback.style.color = '', 500);
        }
    }
}

scannerToggle.addEventListener('click', () => cameraActive ? stopScanner() : startScanner());

switchCameraBtn.addEventListener('click', () => {
    if (cameraActive) {
        switchCamera();
    }
});

function switchCamera() {
    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
    
    if (typeof Quagga !== 'undefined') {
        Quagga.stop();
    }
    if (currentStream) {
        currentStream.getTracks().forEach(t => t.stop());
        currentStream = null;
    }
    
    setScannerStatus('Switching...', 'scanning');
    updateScanFeedback('Switching camera...');
    
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            width: { ideal: 1280 }, 
            height: { ideal: 720 }, 
            facingMode: currentFacingMode 
        } 
    })
    .then(stream => {
        cameraPreview.srcObject = stream;
        currentStream = stream;
        
        cameraPreview.addEventListener('loadedmetadata', () => {
            initializeQuagga();
        }, { once: true });
    })
    .catch(() => { 
        setScannerStatus('Camera switch failed', 'error'); 
        updateScanFeedback('Camera switch failed');
    });
}

function initializeQuagga() {
    Quagga.init({
        inputStream: { 
            name: "Live", 
            type: "LiveStream", 
            target: cameraPreview,
            constraints: {
                width: { min: 640, ideal: 1280, max: 1920 },
                height: { min: 480, ideal: 720, max: 1080 },
                facingMode: currentFacingMode
            }
        },
        locator: {
            patchSize: "medium",
            halfSample: true
        },
        numOfWorkers: 4,
        decoder: { 
            readers: [
                "code_128_reader",
                "ean_reader",
                "ean_8_reader",
                "code_39_reader",
                "code_39_vin_reader",
                "codabar_reader",
                "upc_reader",
                "upc_e_reader",
                "i2of5_reader",
                "2of5_reader",
                "code_93_reader"
            ],
            multiple: false
        },
        locate: true,
        frequency: 10
    }, (err) => {
        if (err) { 
            setScannerStatus('Error', 'error'); 
            updateScanFeedback('Scanner initialization failed');
            return; 
        }
        Quagga.start();
        setScannerStatus('Scanning...', 'scanning');
        updateScanFeedback('Ready to scan');
        
        Quagga.onDetected(result => {
            handleBarcodeDetection(result);
        });
        
        Quagga.onProcessed(result => {
            if (result && result.codeResult) {
                handleBarcodeDetection(result);
            }
        });
    });
}

function handleBarcodeDetection(result) {
    if (!result || !result.codeResult) return;
    
    const code = result.codeResult.code;
    const confidence = result.codeResult.confidence || 1.0;
    const currentTime = Date.now();
    
    if (currentTime - lastScanTime < SCAN_COOLDOWN_MS) {
        return;
    }
    
    if (code === lastScannedCode && scanCooldown) {
        return;
    }
    
    if (confidence < MIN_SCAN_CONFIDENCE) {
        updateScanFeedback(`Detecting... ${Math.round(confidence * 100)}%`);
        return;
    }
    
    lastScannedCode = code;
    scanCooldown = true;
    lastScanTime = currentTime;
    
    cameraViewport.style.borderColor = '#10b981';
    cameraViewport.style.transition = 'border-color 0.3s';
    setTimeout(() => cameraViewport.style.borderColor = '', 300);
    
    playBeepSound();
    
    updateScanFeedback(`✓ Scanned: ${code}`, true);
    setScannerStatus(`Detected: ${code}`, 'active');
    
    submitBarcodeLookup(code);
    
    setTimeout(() => { 
        scanCooldown = false; 
        if (cameraActive) {
            setScannerStatus('Scanning...', 'scanning');
            updateScanFeedback('Ready to scan');
        }
    }, SCAN_COOLDOWN_MS);
}

function startScanner() {
    setScannerStatus('Requesting...', 'active');
    updateScanFeedback('Starting camera...');
    cameraViewport.classList.add('active');
    
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            width: { min: 640, ideal: 1280, max: 1920 },
            height: { min: 480, ideal: 720, max: 1080 },
            facingMode: currentFacingMode 
        } 
    })
    .then(stream => {
        cameraPreview.srcObject = stream;
        currentStream = stream;
        cameraPreview.addEventListener('loadedmetadata', () => {
            cameraActive = true;
            scannerToggle.innerHTML = '<i class="fas fa-stop me-1"></i>Stop';
            scannerToggle.classList.add('active');
            switchCameraBtn.disabled = false;
            setScannerStatus('Initializing...', 'scanning');
            updateScanFeedback('Initializing scanner...');
            
            initializeQuagga();
        }, { once: true });
    })
    .catch(() => { 
        setScannerStatus('Camera denied', 'error'); 
        updateScanFeedback('Camera access denied');
        cameraViewport.classList.remove('active'); 
    });
}

function stopScanner() {
    if (typeof Quagga !== 'undefined') Quagga.stop();
    if (currentStream) { currentStream.getTracks().forEach(t => t.stop()); currentStream = null; }
    cameraPreview.srcObject = null;
    cameraActive = false;
    scannerToggle.innerHTML = '<i class="fas fa-play me-1"></i>Start';
    scannerToggle.classList.remove('active');
    switchCameraBtn.disabled = true;
    setScannerStatus('Inactive', 'inactive');
    updateScanFeedback('Scanner stopped');
    cameraViewport.classList.remove('active');
    currentFacingMode = 'environment';
    scanCooldown = false;
    lastScannedCode = '';
}

function playBeepSound() {
    try {
        const audioElement = beepSound;
        audioElement.currentTime = 0;
        audioElement.volume = 0.5;
        
        const playPromise = audioElement.play();
        
        if (playPromise !== undefined) {
            playPromise.catch(error => {
                beepFallback.currentTime = 0;
                beepFallback.volume = 0.5;
                beepFallback.play().catch(() => {
                    playFallbackBeep();
                });
            });
        }
    } catch (e) {
        playFallbackBeep();
    }
}

function playFallbackBeep() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15);
        
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.15);
    } catch (e) {
        console.log('Beep sound unavailable');
    }
}

function submitBarcodeLookup(code) {
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = `<input type="hidden" name="camera_barcode" value="${code}">`;
    document.body.appendChild(form);
    form.submit();
}

setTimeout(() => {
    const alert = document.getElementById('alert-message');
    if (alert) { alert.style.opacity = '0'; setTimeout(() => alert?.remove(), 400); }
}, 3500);

document.querySelector('input[name="barcode"]')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); e.target.closest('form').submit(); }
});

window.addEventListener('beforeunload', () => { if (cameraActive) stopScanner(); });

window.addEventListener('load', () => {
    console.log('Scanner ready - beep sound loaded from sounds/beep');
});
</script>
</body>
</html>