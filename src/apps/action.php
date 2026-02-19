<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/docker.php';

$db = get_db();
$docker = new Docker();

function respond_error($msg) {
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        htmx_error($msg);
    } else {
        json_error($msg);
    }
    exit();
}

$action = $_GET['action'] ?? null;
$name   = $_GET['name'] ?? null;

$allowedActions = ['start', 'stop', 'logs', 'status'];

if (!in_array($action, $allowedActions, true) || empty($name)) {
    respond_error("Invalid action or container name");
}

if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $name)) {
    respond_error("Invalid container name format");
}

if (empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    if (!$user_id || !check_container_permission($db, $user_id, $name, $action)) {
        respond_error("You do not have permission to perform this action on the container");
    }
}

$escapedName = escapeshellarg($name);

$output = '';
$success = false;

switch ($action) {
    case 'start':
        $result = $docker->start($name);
        $output = $result['output'];
        $success = $result['success'];
        break;

    case 'stop':
        $result = $docker->stop($name);
        $output = $result['output'];
        $success = $result['success'];
        break;

    // http://URL:Port/apps/action.php?action=logs&name=app&lines=2
    case 'logs':
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 30;
        $lines = max(1, min($lines, 500));
        $result = $docker->logs($name, $lines);
        $output = $result['output'];
        header('Content-Type: text/plain; charset=UTF-8');
        echo $output;
        exit();

    case 'status':
        $result = $docker->status($name);
        $output = $result['output'];
        echo $output;
        exit();

    default:
        respond_error("Invalid action");
}

if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    if ($success) {
        echo '<div class="pico-background-green-100 pico-color-green-900"
     style="padding: 0.75rem 1rem; border-radius: 8px; margin-top: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
    <i class="fa fa-check-circle"></i>
    <span>Container action completed successfully.</span>
</div>
';
    } else {
        htmx_error("Error: " . htmlspecialchars($output));
    }
    exit();
} else {
    if ($success) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Container action completed successfully.']);
    } else {
        json_error("Error: " . $output);
    }
    exit();
}

?>