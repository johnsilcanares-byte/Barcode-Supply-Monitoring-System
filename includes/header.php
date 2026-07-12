<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'CISM System' ?></title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --sidebar-bg: linear-gradient(165deg, #1e1b4b 0%, #312e81 100%);
            --topbar-height: 70px;
            --transition: all 0.25s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }
        /* Sidebar will be included separately, but we set base styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            width: calc(100% - var(--sidebar-width));
            background: #f8fafc;
        }
        /* Top navigation bar inside main content */
        .topbar {
            height: var(--topbar-height);
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .page-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-greeting {
            font-weight: 500;
            color: #475569;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(145deg, var(--primary), #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid white;
            box-shadow: 0 4px 8px rgba(79,70,229,0.2);
        }
        .user-avatar:hover {
            transform: scale(1.05);
        }
        .content-wrapper {
            padding: 2rem;
        }
        /* Mobile responsiveness */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; width: 100%; }
            .topbar { padding: 0 1.5rem; }
        }
        @media (max-width: 576px) {
            .content-wrapper { padding: 1rem; }
            .page-title { font-size: 1.2rem; }
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar will be placed here via include -->