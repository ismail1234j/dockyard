<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Require admin privileges
require_admin();

header('Content-Type: application/json');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

// Validate input
if (!isset($_POST['user_id']) || !isset($_POST['enabled'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$userId = intval($_POST['user_id']);
$enabled = $_POST['enabled'] === '1' ? 1 : 0;

try {
    // Get target user info
    $stmt = $db->prepare('SELECT username FROM users WHERE ID = :id');
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Update force_password_reset flag
    $stmt = $db->prepare('UPDATE users SET force_password_reset = :enabled WHERE ID = :id');
    $stmt->bindParam(':enabled', $enabled, PDO::PARAM_INT);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Log admin action
    $adminUserId = $_SESSION['user_id'];
    $action = $enabled ? 'ENABLE_FORCE_PASSWORD_RESET' : 'DISABLE_FORCE_PASSWORD_RESET';
    $details = "Force password reset " . ($enabled ? "enabled" : "disabled") . " for user: " . $targetUser['username'];
    log_admin_action($db, $adminUserId, $userId, $action, $details);
    
    $message = $enabled 
        ? "Force password reset enabled for " . $targetUser['username'] 
        : "Force password reset disabled for " . $targetUser['username'];
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    error_log("Error toggling force reset: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
