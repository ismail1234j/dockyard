<?php
require_once 'includes/auth.php';
require_once 'includes/db.php'; 
$db = get_db();

// Require admin privileges for user deletion
require_admin();

$error_message = null;
$success_message = null;

if ($auth) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token for AJAX requests
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403); // Forbidden
            echo 'Error: Security validation failed.';
            exit;
        }
        
        if (isset($_POST['username'])) {
            $usernameToDelete = $_POST['username'];

            // Prevent deleting the currently logged-in user
            if ($usernameToDelete === $_SESSION['username']) {
                http_response_code(400); // Bad Request
                echo 'Error: Cannot delete the currently logged-in user.';
                exit;
            }

            try {
                $stmt = $db->prepare('DELETE FROM users WHERE username = :username');
                $stmt->bindParam(':username', $usernameToDelete, PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    echo 'User deleted successfully';
                } else {
                    http_response_code(404); // Not Found
                    echo 'Error: User not found.';
                }
            } catch (PDOException $e) {
                // Log error: error_log("User Deletion DB Error: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                echo 'Error: Database error during user deletion.';
            }
        } else {
            http_response_code(400); // Bad Request
            echo 'Error: No username provided.';
        }
    } else {
        http_response_code(405); // Method Not Allowed
        echo 'Error: Invalid request method.';
    }
} else {
    // Auth check failed (handled by auth.php redirect, but added for completeness)
    http_response_code(401); // Unauthorized
    echo 'Error: Unauthorized.';
}
?>
