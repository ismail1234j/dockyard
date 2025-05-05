<?php
if (!isset($_GET['name'])) {
    exit();
}
require_once '../includes/auth.php'; // Use centralized auth
require_once '../includes/functions.php';
$name = htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8');
// Check if the user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../login.php');
    exit();
}
// Fetch container status
$containerStatus = shell_exec("bash ../manage_containers.sh status $name");
$status = htmlspecialchars($containerStatus, ENT_QUOTES, 'UTF-8');

?>

<!DOCTYPE html>
<html data-theme="light">
    <head>
        <title><?php echo $name; ?></title>
      <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"
      />
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <style>
            .action-button i { margin-right: 5px; }
            .action-start { background-color:rgb(50, 119, 54); color: white; }
            .action-stop { background-color:rgb(203, 54, 54); color: white; }
            .action-logs { background-color:rgb(114, 174, 214); color: white; }
        </style>
        <div class="container" style="margin-top: 8%;">
            <h1><?php echo $name; ?> | <?php echo $status; ?></h1>
            <hr />
            <div class="action-buttons">
                <button class="secondary" onclick="location.href='../apps.php';">Back</button>
                <!-- Start button -->
                <button class="action-button action-start" onclick="startContainer('<?php echo $name; ?>')">
                    <i class="fa fa-play"></i> Start
                </button>
                <!-- Stop button -->
                <button class="action-button action-stop" onclick="stopContainer('<?php echo $name; ?>')">
                    <i class="fa fa-stop
                    "></i> Stop
                </button>
                <!-- Logs button -->
                <button class="action-button action-logs" onclick="viewLogs('<?php echo $name; ?>')">
                    <i class="fa fa-file
                    "></i> View Logs
                </button>
            </div>
            <!-- Use the PHP Modal function to open confirmation dialogues -->
            <script>
                function startContainer(name) {
                    if (confirm("Are you sure you want to start the container " + name + "?")) {
                        window.location.href = "action.php?start=" + encodeURIComponent(name);
                    }
                }

                function stopContainer(name) {
                    if (confirm("Are you sure you want to stop the container " + name + "?")) {
                        window.location.href = "action.php?stop=" + encodeURIComponent(name);
                    }
                }

                function fetchLogs(name) {
                    const logsContainer = document.getElementById('container-logs');
                    logsContainer.innerHTML = '<div class="spinner"></div>'; // Show spinner
                    fetch('action.php?logs=' + encodeURIComponent(name))
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('container-logs').innerText = data;
                        })
                        .catch(error => {
                            console.error('Error fetching logs:', error);
                            document.getElementById('container-logs').innerText = 'Error fetching logs.';
                        });
                }

                function closeModal() {
                    const modal = document.getElementById('view-logs-modal');
                    modal.style.display = 'none';
                }
                function refreshLogs() {
                    const name = document.getElementById('logs-title').innerText.split(' ')[0];
                    fetchLogs(name);
                }
            </script>
        </div>