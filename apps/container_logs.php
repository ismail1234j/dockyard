<?php
require_once '../includes/auth.php'; // Use centralized auth

// Get container name from query parameter
$container = isset($_GET['container']) ? $_GET['container'] : '';
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 30;

// Validate container name to prevent command injection
if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $container)) {
    http_response_code(400);
    echo "Invalid container name";
    exit;
}

// Limit lines to a reasonable number
$lines = max(5, min(100, $lines));

// Check if the user has permission to view this container
$user_id = $_SESSION['user_id'] ?? null;
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

if (!$isAdmin && $user_id) {
    $stmt = $db->prepare('
        SELECT cp.CanView 
        FROM container_permissions cp
        JOIN apps a ON cp.ContainerID = a.ID
        WHERE a.ContainerName = :containerName AND cp.UserID = :userID
    ');
    $stmt->bindParam(':containerName', $container, PDO::PARAM_STR);
    $stmt->bindParam(':userID', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permission || !$permission['CanView']) {
        http_response_code(403);
        echo "Permission denied";
        exit;
    }
}

// Check if container exists in database
$stmt = $db->prepare('SELECT ID FROM apps WHERE ContainerName = :containerName');
$stmt->bindParam(':containerName', $container, PDO::PARAM_STR);
$stmt->execute();
if (!$stmt->fetch()) {
    http_response_code(404);
    echo "Container not found";
    exit;
}

try {
    // Get the correct path to the script (relative to the current file)
    $script_path = dirname(dirname(__FILE__)) . '/manage_containers.sh';
    
    // Make sure the script exists and is executable
    if (!file_exists($script_path)) {
        throw new Exception("Container management script not found at: $script_path");
    }
    
    // Make sure the script is executable
    if (!is_executable($script_path)) {
        chmod($script_path, 0755);
    }
    
    // Execute command to get logs
    $output = shell_exec("$script_path logs " . escapeshellarg($container) . " " . escapeshellarg($lines) . " 2>&1");
    
    if ($output !== null) {
        // Syntax highlight common log elements
        $output = htmlspecialchars($output);
        
        // Highlight errors and warnings
        $output = preg_replace('/\b(ERROR|FATAL|CRITICAL)\b/i', '<span style="color: #FF5252;">$1</span>', $output);
        $output = preg_replace('/\b(WARNING|WARN)\b/i', '<span style="color: #FFD740;">$1</span>', $output);
        $output = preg_replace('/\b(INFO|NOTICE)\b/i', '<span style="color: #64B5F6;">$1</span>', $output);
        
        // Highlight timestamps
        $output = preg_replace('/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', '<span style="color: #80CBC4;">$0</span>', $output);
        
        echo $output;
    } else {
        echo "No logs available or unable to retrieve logs";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>