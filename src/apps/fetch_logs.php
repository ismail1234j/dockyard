<?php
// filepath: /mnt/53183b01-399f-4420-81fe-e9f60a4d7cd9/Website/apps/fetch_logs.php
require_once '../includes/auth.php'; // Add authentication check
require_once '../includes/functions.php'; // Add functions for permission check

header('Content-Type: text/plain');

if (!isset($_GET['name'])) {
    echo "Error: No container name provided.";
    exit();
}

// Check if user has permission to view logs for this container
$user_id = $_SESSION['user_id'] ?? null;
$container_name = $_GET['name'];

if (!$user_id || !check_container_permission($db, $user_id, $container_name, 'logs')) {
    echo "Error: You don't have permission to view logs for this container.";
    exit();
}

// Execute the logs command and output the result
echo shell_exec('bash ../private/manage_containers.sh logs' . ' ' . escapeshellarg($container_name));
?>