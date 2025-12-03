<?php
include_once '../includes/auth.php'; // Use centralized auth
include_once '../includes/functions.php';

// Check if the user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Validate and sanitize input
$action = isset($_GET['start']) ? 'start' : (isset($_GET['stop']) ? 'stop' : (isset($_GET['logs']) ? 'logs' : (isset($_GET['status']) ? 'status' : null)));
$name = isset($_GET['start']) ? $_GET['start'] : (isset($_GET['stop']) ? $_GET['stop'] : (isset($_GET['logs']) ? $_GET['logs'] : (isset($_GET['status']) ? $_GET['status'] : null)));

if (!$action || !$name) {
    header('Location: ../apps.php?error=invalid_request');
    exit();
}

// Check if user has permission to perform this action
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !check_container_permission($db, $user_id, $name, $action)) {
    header('Location: ../apps.php?error=unauthorized');
    exit();
}

$escapedName = escapeshellarg($name); // Escape the container name to prevent command injection
$name = trim($escapedName, "'"); // Remove single quotes added by escapeshellarg()
$scriptPath = realpath('../manage_containers.sh'); // Get the absolute path to the script

if (!$scriptPath || !file_exists($scriptPath)) {
    header('Location: container_info.php?name=' . urlencode($name) . '&error=script_not_found');
    exit();
}

// Execute the appropriate action
switch ($action) {
    case 'start':
        shell_exec("bash $scriptPath start $escapedName 2>&1");
        break;

    case 'stop':
        shell_exec("bash $scriptPath stop $escapedName 2>&1");
        break;

    case 'logs':
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 30; // Default to 30 lines
        shell_exec("bash $scriptPath logs $escapedName $lines 2>&1");
        break;

    case 'status':
        shell_exec("bash $scriptPath status $escapedName 2>&1");
        break;

    default:
        header('Location: container_info.php?name=' . urlencode($name) . '&error=invalid_action');
        exit();
}

// Redirect back to container_info.php
header('Location: container_info.php?name=' . urlencode($name));
exit();
?>