<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/docker.php';

$db = get_db();
$docker = new Docker();

$action = $_GET['action'] ?? null;
$name   = $_GET['name'] ?? null;

$allowedActions = ['start', 'stop', 'logs', 'status'];

if (!in_array($action, $allowedActions, true) || empty($name)) {
    header('Location: ../apps.php?error=invalid_request');
    exit();
}

if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $name)) {
    header('Location: ../apps.php?error=invalid_container');
    exit();
}

if (!$user_id || !check_container_permission($db, $user_id, $name, $action)) {
    header('Location: ../apps.php?error=unauthorized');
    exit();
}

$escapedName = escapeshellarg($name);

$output = '';
$success = false;

switch ($action) {
    case 'start':
        /* Outdated shell process, use Docker class methods instead
        $output = shell_exec("bash $scriptPath start $escapedName 2>&1");
        $success = strpos($output, $name) !== false || empty(trim($output));
        */
        $result = $docker->start($name);
        $output = $result['output'];
        $success = $result['success'];
        break;

    case 'stop':
        /* Outdated shell process, use Docker class methods instead
        $output = shell_exec("bash $scriptPath stop $escapedName 2>&1");
        $success = strpos($output, $name) !== false || empty(trim($output));
        break;
        */
        $result = $docker->stop($name);
        $output = $result['output'];
        $success = $result['success'];
        break;

    // http://URL:Port/apps/action.php?action=logs&name=app&lines=2
    case 'logs':
        /* Outdated shell process, use Docker class methods instead
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 30;
        $lines = max(1, min($lines, 500));
        $output = shell_exec("bash $scriptPath logs $escapedName $lines 2>&1");
        header('Content-Type: text/plain; charset=UTF-8');
        echo $output;
        exit();
        */
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 30;
        $lines = max(1, min($lines, 500));
        $result = $docker->logs($name, $lines);
        $output = $result['output'];
        header('Content-Type: text/plain; charset=UTF-8');
        echo $output;
        exit();

    case 'status':
        /* Outdated shell process, use Docker class methods instead
        $output = shell_exec("bash $scriptPath status $escapedName 2>&1");
        echo $output;
        exit();
        */
        $result = $docker->status($name);
        $output = $result['output'];
        echo $output;
        exit();

    default:
        header('Location: container_info.php?name=' . urlencode($name) . '&error=invalid_action');
        exit();
}

?>