<?php
session_start();
require_once 'includes/db.php';
$db = get_db();

// Remove session from database
try {
    $sessionId = session_id();
    if ($sessionId) {
        $stmt = $db->prepare('DELETE FROM user_sessions WHERE SessionID = :session_id');
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->execute();
    }
} catch (PDOException $e) {
    // Log error but continue with logout
    error_log("Error removing session from database: " . $e->getMessage());
}

session_unset();
// Remove the cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();
header('Location: login.php');
?>