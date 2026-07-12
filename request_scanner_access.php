<?php
session_start();
require_once 'config.php';  // Your database connection (PDO)

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if already pending
$stmt = $db->prepare("SELECT id FROM scanner_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending request.']);
    exit;
}

// Insert new request
$stmt = $db->prepare("INSERT INTO scanner_requests (user_id, status) VALUES (?, 'pending')");
if ($stmt->execute([$user_id])) {
    echo json_encode(['success' => true, 'message' => 'Request sent to admin.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>