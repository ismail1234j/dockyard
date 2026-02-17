<?php
include_once '../includes/auth.php';
include_once '../includes/functions.php';
require_once '../includes/db.php';
$db = get_db();

$action = $_GET['action'] ?? null;
$name   = $_GET['name'] ?? null;

$allowedActions = ['start', 'stop', 'logs', 'status'];

if (!in_array($action, $allowedActions, true) || empty($name)) {
    header('Location: ../apps.php?error=invalid_request');
    exit();
}

// Check if user has permission to perform this action
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !check_container_permission($db, $user_id, $name, $action)) {
    header('Location: ../apps.php?error=unauthorized');
    exit();
}

if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $name)) {
    header('Location: ../apps.php?error=invalid_container');
    exit();
}

$escapedName = escapeshellarg($name);

$scriptPath = realpath('../private/manage_containers.sh');

if (!$scriptPath || !file_exists($scriptPath)) {
    header('Location: container_info.php?name=' . urlencode($name) . '&error=script_not_found');
    exit();
}

// Execute the appropriate action
$output = '';
$success = false;

switch ($action) {
    case 'start':
        $output = shell_exec("bash $scriptPath start $escapedName 2>&1");
        $success = strpos($output, $name) !== false || empty(trim($output));
        break;

    case 'stop':
        $output = shell_exec("bash $scriptPath stop $escapedName 2>&1");
        $success = strpos($output, $name) !== false || empty(trim($output));
        break;

    // http://URL:Port/apps/action.php?action=logs&name=app&lines=2
    case 'logs':
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 30;
        $lines = max(1, min($lines, 500));
        $output = shell_exec("bash $scriptPath logs $escapedName $lines 2>&1");
        header('Content-Type: text/plain; charset=UTF-8');
        echo $output;
        exit();

    case 'status':
        $output = shell_exec("bash $scriptPath status $escapedName 2>&1");
        echo $output;
        exit();

    default:
        header('Location: container_info.php?name=' . urlencode($name) . '&error=invalid_action');
        exit();
}

// For start/stop actions, redirect back with status
$status = $success ? 'success' : 'error';
header('Location: container_info.php?name=' . urlencode($name) . '&action_status=' . $status . '&action_type=' . $action);
exit();
?>