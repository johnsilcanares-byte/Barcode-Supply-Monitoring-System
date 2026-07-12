<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['request_id']) || !is_numeric($_POST['request_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

$request_id = (int)$_POST['request_id'];

$db->beginTransaction();
try {
    // Get user_id from the request
    $stmt = $db->prepare("SELECT user_id FROM scanner_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$req) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Request not found or already processed']);
        exit;
    }
    
    $user_id = $req['user_id'];
    
    // Update user: scanner_approved = 1
    $updateUser = $db->prepare("UPDATE users SET scanner_approved = 1 WHERE id = ?");
    $updateUser->execute([$user_id]);
    
    // Update request status to approved
    $updateReq = $db->prepare("UPDATE scanner_requests SET status = 'approved' WHERE id = ?");
    $updateReq->execute([$request_id]);
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Scanner access granted']);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>