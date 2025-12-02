<?php
// filepath: /mnt/53183b01-399f-4420-81fe-e9f60a4d7cd9/Website/apps/fetch_logs.php
require_once '../includes/auth.php'; // Add authentication check

header('Content-Type: text/plain');

if (!isset($_GET['name'])) {
    echo "Error: No container name provided.";
    exit();
}

// Execute the logs command and output the result
echo shell_exec('bash ../manage_containers.sh logs' . ' ' . escapeshellarg($_GET['name']));
?>