<?php
// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar {
        width: var(--sidebar-width, 260px);
        background: var(--sidebar-bg, linear-gradient(165deg, #1e1b4b 0%, #312e81 100%));
        color: rgba(255,255,255,0.9);
        min-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1050;
        box-shadow: 4px 0 20px rgba(0,0,0,0.12);
        display: flex;
        flex-direction: column;
        transition: var(--transition, all 0.25s);
        border-right: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-brand {
        padding: 1.8rem 1.5rem 1.2rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 1.5rem;
    }
    .sidebar-brand h3 {
        font-weight: 700;
        letter-spacing: -0.02em;
        color: white;
        margin: 0;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .sidebar-brand i {
        font-size: 1.8rem;
        color: #a5b4fc;
    }
    .sidebar-nav {
        flex: 1;
        padding: 0 1rem;
    }
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0.75rem 1rem;
        margin: 4px 0;
        color: rgba(255,255,255,0.75);
        text-decoration: none;
        border-radius: 14px;
        font-weight: 500;
        transition: all 0.2s cubic-bezier(0.2,0,0,1);
        position: relative;
    }
    .nav-item i {
        font-size: 1.3rem;
        width: 24px;
        text-align: center;
        opacity: 0.8;
    }
    .nav-item:hover {
        background: rgba(255,255,255,0.1);
        color: white;
        padding-left: 1.5rem;
    }
    .nav-item.active {
        background: rgba(255,255,255,0.2);
        color: white;
        font-weight: 600;
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }
    .nav-item.active i {
        opacity: 1;
        color: #c7d2fe;
    }
    .sidebar-footer {
        padding: 1.5rem 1rem 2rem;
        border-top: 1px solid rgba(255,255,255,0.08);
        margin-top: 1rem;
    }
    .logout-btn {
        background: rgba(255,255,255,0.05);
        color: white;
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 40px;
        padding: 0.7rem 1.2rem;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
    }
    .logout-btn:hover {
        background: rgba(239,68,68,0.9);
        border-color: transparent;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(239,68,68,0.3);
    }
    /* Mobile adjustments handled by header media query */
</style>

<div class="sidebar">
    <div class="sidebar-brand">
        <h3>
            <i class="bi bi-boxes"></i> 
            <span>CISMS</span>
        </h3>
        <div style="font-size:0.75rem; opacity:0.6; margin-top:6px;">Admin Portal</div>
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
        <a href="purchase_orders.php" class="nav-item <?= $current_page == 'purchase_orders.php' ? 'active' : '' ?>">
            <i class="bi bi-cart-check-fill"></i>
            <span>Purchase Orders</span>
        </a>
        <a href="reports.php" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i>
            <span>Reports</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
        </a>
    </div>
</div>