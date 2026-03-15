<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

$db = get_db();

function respond_error($msg) {
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        htmx_error($msg);
    } else {
        json_error($msg);
    }
    exit();
}

if (empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    respond_error("You do not have permission to perform this action");   
}

$action = $_GET['action'] ?? null;
$id   = $_GET['id'] ?? null;

$output = '';
$success = false;

switch ($action) {
    case 'edit':

        break;

    case 'delete':
        $result = $docker->stop($name);
        $output = $result['output'];
        $success = $result['success'];
        break;

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