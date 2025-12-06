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

// Check if user has permission to view this container
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !check_container_permission($db, $user_id, $name, 'view')) {
    header('Location: ../apps.php?error=unauthorized');
    exit();
}

// Fetch container status
$containerStatus = shell_exec("bash ../manage_containers.sh status $name");
$status = htmlspecialchars($containerStatus, ENT_QUOTES, 'UTF-8');

// Check user permissions for this container
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
$canStart = $isAdmin || check_container_permission($db, $user_id, $name, 'start');
$canStop = $isAdmin || check_container_permission($db, $user_id, $name, 'stop');

?>

<!DOCTYPE html>
<html data-theme="light">
    <head>
        <title><?php echo $name; ?></title>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>
        .action-button { margin: 5px; }
        .action-button i { margin-right: 5px; }
        .action-start { background-color:rgb(50, 119, 54); color: white; }
        .action-stop { background-color:rgb(203, 54, 54); color: white; }
        .action-edit { background-color:rgb(255, 165, 0); color: white; }
        .action-logs { background-color:rgb(114, 174, 214); color: white; }
        
        /* Custom Modal Styles */
        .custom-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background-color: #13171f;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            animation: slideIn 0.3s;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-body {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Logs section */
        .logs-section {
            margin-top: 30px;
        }
        
        .logs-container {
            background-color: #1E1E1E;
            border-radius: 5px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            color: #CCCCCC;
            font-size: 0.9em;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-running { background-color: #28a745; color: white; }
        .status-exited, .status-stopped { background-color: #dc3545; color: white; }
        .status-created, .status-unknown { background-color: #ffc107; color: black; }
      </style>
    </head>
    <body>
        <div class="container" style="margin-top: 8%;">
            <h1><?php echo $name; ?></h1>
            <span class="status-badge status-<?php echo strtolower(trim($status)); ?>">
                <?php echo trim($status); ?>
            </span>
            <hr />
            
            <?php if (isset($_GET['action_status'])): ?>
                <div style="padding: 1rem; border-radius: 4px; margin-bottom: 1rem; <?php echo $_GET['action_status'] === 'success' ? 'background-color: #d1e7dd; color: #0f5132;' : 'background-color: #f8d7da; color: #842029;'; ?>">
                    <?php 
                    if ($_GET['action_status'] === 'success') {
                        echo 'Container ' . htmlspecialchars($_GET['action_type'] ?? 'action') . ' completed successfully.';
                    } else {
                        echo 'Failed to ' . htmlspecialchars($_GET['action_type'] ?? 'perform action') . ' container.';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <button class="secondary" onclick="location.href='../apps.php';">Back</button>
                
                <?php if ($isAdmin): ?>
                <button class="action-button action-edit" onclick="location.href='container_edit.php?name=<?php echo urlencode($name); ?>';">
                    <i class="fa fa-edit"></i> Edit
                </button>
                <?php endif; ?>
                
                <button class="action-button action-start" onclick="showConfirmModal('start', <?php echo json_encode($name); ?>)" <?php echo $canStart ? '' : 'disabled'; ?>>
                    <i class="fa fa-play"></i> Start
                </button>
                
                <button class="action-button action-stop" onclick="showConfirmModal('stop', <?php echo json_encode($name); ?>)" <?php echo $canStop ? '' : 'disabled'; ?>>
                    <i class="fa fa-stop"></i> Stop
                </button>
            </div>
            
            <!-- Logs Section -->
            <div class="logs-section">
                <div class="logs-header">
                    <h3>Container Logs</h3>
                    <button onclick="refreshLogs()" class="action-button action-logs">
                        <i class="fa fa-refresh"></i> Refresh Logs
                    </button>
                </div>
                <div id="logs-container" class="logs-container">
                    Loading logs...
                </div>
            </div>
        </div>
        
        <!-- Confirmation Modal -->
        <div id="confirm-modal" class="custom-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">Confirm Action</h3>
                </div>
                <div class="modal-body">
                    <p id="modal-message">Are you sure?</p>
                </div>
                <div class="modal-footer">
                    <button class="secondary" onclick="closeModal('confirm-modal')">Cancel</button>
                    <button id="modal-confirm-btn" onclick="executeAction()">Confirm</button>
                </div>
            </div>
        </div>
        
        <!-- Loading Modal -->
        <div id="loading-modal" class="custom-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="loading-title">Processing</h3>
                </div>
                <div class="modal-body">
                    <div class="spinner"></div>
                    <p id="loading-message">Please wait...</p>
                </div>
            </div>
        </div>
        
        <!-- Result Modal -->
        <div id="result-modal" class="custom-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="result-title">Result</h3>
                </div>
                <div class="modal-body">
                    <p id="result-message">Action completed</p>
                </div>
                <div class="modal-footer">
                    <button onclick="closeResultModal()">OK</button>
                </div>
            </div>
        </div>
        
        <script>
            const containerName = <?php echo json_encode($name); ?>;
            let pendingAction = null;
            
            function showConfirmModal(action, name) {
                pendingAction = action;
                const modal = document.getElementById('confirm-modal');
                const title = document.getElementById('modal-title');
                const message = document.getElementById('modal-message');
                const confirmBtn = document.getElementById('modal-confirm-btn');
                
                title.textContent = action === 'start' ? 'Start Container' : 'Stop Container';
                message.textContent = `Are you sure you want to ${action} the container "${name}"?`;
                confirmBtn.className = action === 'start' ? 'action-start' : 'action-stop';
                
                modal.style.display = 'block';
            }
            
            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
                pendingAction = null;
            }
            
            function executeAction() {
                if (!pendingAction) return;
                
                closeModal('confirm-modal');
                showLoadingModal(pendingAction);
                
                // Execute the action
                const url = `action.php?${pendingAction}=${encodeURIComponent(containerName)}`;
                window.location.href = url;
            }
            
            function showLoadingModal(action) {
                const modal = document.getElementById('loading-modal');
                const title = document.getElementById('loading-title');
                const message = document.getElementById('loading-message');
                
                title.textContent = action === 'start' ? 'Starting Container' : 'Stopping Container';
                message.textContent = `Please wait while the container is ${action === 'start' ? 'starting' : 'stopping'}...`;
                
                modal.style.display = 'block';
            }
            
            function closeResultModal() {
                closeModal('result-modal');
                location.reload();
            }
            
            function refreshLogs() {
                const logsContainer = document.getElementById('logs-container');
                logsContainer.textContent = 'Loading logs...';
                
                fetch('action.php?logs=' + encodeURIComponent(containerName) + '&lines=50')
                    .then(response => response.text())
                    .then(data => {
                        logsContainer.textContent = data || 'No logs available';
                    })
                    .catch(error => {
                        console.error('Error fetching logs:', error);
                        logsContainer.textContent = 'Error fetching logs: ' + error.message;
                    });
            }
            
            // Load logs on page load
            window.addEventListener('DOMContentLoaded', function() {
                refreshLogs();
                
                // Auto-refresh logs every 10 seconds
                setInterval(refreshLogs, 10000);
            });
        </script>
    </body>
</html>