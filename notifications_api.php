<?php
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Handle different actions
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $notifications = get_user_notifications($db, $userId, $unreadOnly, $limit);
        echo json_encode($notifications);
        break;
        
    case 'count':
        $count = get_unread_notification_count($db, $userId);
        echo json_encode(['count' => $count]);
        break;
        
    case 'mark_read':
        if (!isset($_POST['notification_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
            exit;
        }
        $notificationId = intval($_POST['notification_id']);
        $success = mark_notification_read($db, $notificationId);
        echo json_encode(['success' => $success]);
        break;
        
    case 'mark_all_read':
        $success = mark_all_notifications_read($db, $userId);
        echo json_encode(['success' => $success]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
