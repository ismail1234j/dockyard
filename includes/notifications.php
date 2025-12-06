<?php
/**
 * Notification System Utilities
 * 
 * Functions for managing in-app notifications
 */

require_once __DIR__ . '/auth.php';

/**
 * Create a new notification
 * 
 * @param PDO $db Database connection
 * @param int|null $userId User ID (null for all admins)
 * @param int|null $containerId Container ID
 * @param string $type Notification type (info, warning, error, success)
 * @param string $message Notification message
 * @return bool Success status
 */
function create_notification($db, $userId, $containerId, $type, $message) {
    try {
        $stmt = $db->prepare('
            INSERT INTO notifications (UserID, ContainerID, Type, Message, IsRead, CreatedAt)
            VALUES (:user_id, :container_id, :type, :message, 0, CURRENT_TIMESTAMP)
        ');
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':container_id', $containerId, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param bool $unreadOnly Only fetch unread notifications
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function get_user_notifications($db, $userId, $unreadOnly = false, $limit = 50) {
    try {
        $isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
        
        if ($isAdmin) {
            // Admins see all notifications
            $sql = '
                SELECT n.*, a.ContainerName, u.username
                FROM notifications n
                LEFT JOIN apps a ON n.ContainerID = a.ID
                LEFT JOIN users u ON n.UserID = u.ID
                WHERE 1=1
            ';
        } else {
            // Regular users only see notifications for containers they have access to
            $sql = '
                SELECT n.*, a.ContainerName, u.username
                FROM notifications n
                LEFT JOIN apps a ON n.ContainerID = a.ID
                LEFT JOIN users u ON n.UserID = u.ID
                LEFT JOIN container_permissions cp ON a.ID = cp.ContainerID AND cp.UserID = :user_id
                WHERE (n.UserID = :user_id OR (cp.CanView = 1 AND n.UserID IS NULL))
            ';
        }
        
        if ($unreadOnly) {
            $sql .= ' AND n.IsRead = 0';
        }
        
        $sql .= ' ORDER BY n.CreatedAt DESC LIMIT :limit';
        
        $stmt = $db->prepare($sql);
        if (!$isAdmin) {
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 * 
 * @param PDO $db Database connection
 * @param int $notificationId Notification ID
 * @return bool Success status
 */
function mark_notification_read($db, $notificationId) {
    try {
        $stmt = $db->prepare('UPDATE notifications SET IsRead = 1 WHERE ID = :id');
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @return bool Success status
 */
function mark_all_notifications_read($db, $userId) {
    try {
        $stmt = $db->prepare('UPDATE notifications SET IsRead = 1 WHERE UserID = :user_id');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @return int Unread count
 */
function get_unread_notification_count($db, $userId) {
    try {
        $isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
        
        if ($isAdmin) {
            $sql = 'SELECT COUNT(*) FROM notifications WHERE IsRead = 0';
            $stmt = $db->prepare($sql);
        } else {
            $sql = '
                SELECT COUNT(DISTINCT n.ID)
                FROM notifications n
                LEFT JOIN apps a ON n.ContainerID = a.ID
                LEFT JOIN container_permissions cp ON a.ID = cp.ContainerID AND cp.UserID = :user_id
                WHERE n.IsRead = 0 AND (n.UserID = :user_id OR (cp.CanView = 1 AND n.UserID IS NULL))
            ';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting unread notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Delete old notifications
 * 
 * @param PDO $db Database connection
 * @param int $daysOld Delete notifications older than this many days
 * @return bool Success status
 */
function cleanup_old_notifications($db, $daysOld = 30) {
    try {
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE IsRead = 1 
            AND datetime(CreatedAt) < datetime('now', '-' || :days || ' days')
        ");
        $stmt->bindParam(':days', $daysOld, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error cleaning up old notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an admin action
 * 
 * @param PDO $db Database connection
 * @param int $adminUserId Admin user ID
 * @param int|null $targetUserId Target user ID (if applicable)
 * @param string $action Action performed
 * @param string|null $details Additional details
 * @return bool Success status
 */
function log_admin_action($db, $adminUserId, $targetUserId, $action, $details = null) {
    try {
        $stmt = $db->prepare('
            INSERT INTO admin_actions_log (AdminUserID, TargetUserID, Action, Details, CreatedAt)
            VALUES (:admin_user_id, :target_user_id, :action, :details, CURRENT_TIMESTAMP)
        ');
        
        $stmt->bindParam(':admin_user_id', $adminUserId, PDO::PARAM_INT);
        $stmt->bindParam(':target_user_id', $targetUserId, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}

?>
