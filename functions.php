<?php
// Require login
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

// Require admin role
function require_admin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: pos.php"); // redirect non-admins
        exit;
    }
}
