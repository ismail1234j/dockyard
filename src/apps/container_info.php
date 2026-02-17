<?php
if (!isset($_GET['name'])) {
    exit();
}
require_once '../includes/db.php';
require_once '../includes/auth.php'; 
require_once '../includes/functions.php';
$name = htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8');

// Check if user has permission to view this container
// Todo: Migrate to the func in functions.php
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !check_container_permission($db, $user_id, $name, 'view')) {
    header('Location: ../apps.php?error=unauthorized');
    exit();
}

// Fetch container status
$containerStatus = shell_exec("bash ../private/manage_containers.sh status $name");
$status = htmlspecialchars($containerStatus, ENT_QUOTES, 'UTF-8');

// Check user permissions for this container
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
$canStart = $isAdmin || check_container_permission($db, $user_id, $name, 'start');
$canStop = $isAdmin || check_container_permission($db, $user_id, $name, 'stop');

?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title><?php echo isset($name) ? htmlspecialchars($name) : 'Container Detail'; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <style>
        article {
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: var(--pico-card-sectioning-background-color);
        }
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--pico-spacing);
        }

        /* Status Badge Styling */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }
        
        .status-running { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-exited, .status-stopped { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .status-created, .status-unknown { background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }

        .action-group {
            display: flex;
            gap: 12px;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            width: auto;
            margin: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Logs Section */
        .logs-section {
            margin-top: 2rem;
            background: var(--pico-card-sectioning-background-color);
            border-radius: var(--pico-border-radius);
            padding: 1.5rem;
            border: 1px solid var(--pico-muted-border-color);
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .logs-container {
            background-color: #0d1117;
            color: #d1d5db;
            padding: 1.25rem;
            border-radius: 8px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #30363d;
        }

        /* Spinner for loading modal */
        .loader-spinner {
            width: 48px;
            height: 48px;
            border: 5px solid var(--pico-muted-border-color);
            border-bottom-color: var(--pico-primary);
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }

        @keyframes rotation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Confirmation Dialog Styles */
        dialog article {
            max-width: 450px;
        }
    </style>
</head>
<body>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <div class="header-nav">
                    <hgroup style="margin: 0;">
                        <h1><?php echo htmlspecialchars($name); ?></h1>
                        <p>Container Management Interface</p>
                    </hgroup>
                    <button class="secondary outline" onclick="location.href='../apps.php';" style="width: auto;">
                        <i class="fa fa-arrow-left"></i> Back to Apps
                    </button>
                </div>
            </header>

            <main>
                <!-- Status Row -->
                <div class="status-badge status-<?php echo strtolower(trim($status)); ?>">
                    <i class="fa fa-circle" style="font-size: 8px; margin-right: 8px;"></i>
                    <?php echo htmlspecialchars(trim($status)); ?>
                </div>

                <!-- Action Notifications -->
                <?php if (isset($_GET['action_status'])): ?>
                    <div style="padding: 1rem; border-radius: var(--pico-border-radius); margin-bottom: 1.5rem; border: 1px solid; 
                        <?php echo $_GET['action_status'] === 'success' ? 'background-color: #d1e7dd; color: #0f5132; border-color: #badbcc;' : 'background-color: #f8d7da; color: #842029; border-color: #f5c2c7;'; ?>">
                        <i class="fa <?php echo $_GET['action_status'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        <?php 
                        if ($_GET['action_status'] === 'success') {
                            echo 'Container ' . htmlspecialchars($_GET['action_type'] ?? 'action') . ' completed successfully.';
                        } else {
                            echo 'Failed to ' . htmlspecialchars($_GET['action_type'] ?? 'perform action') . ' container.';
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Controls -->
                <div class="action-group">
                    <?php if ($isAdmin): ?>
                    <button class="action-btn warning" onclick="location.href='container_edit.php?name=<?php echo urlencode($name); ?>';">
                        <i class="fa fa-pencil"></i> Configure
                    </button>
                    <?php endif; ?>
                    
                    <button class="action-btn success" onclick="confirmAction('start')" <?php echo $canStart ? '' : 'disabled'; ?>>
                        <i class="fa fa-play"></i> Start Instance
                    </button>
                    
                    <button class="action-btn error outline" onclick="confirmAction('stop')" <?php echo $canStop ? '' : 'disabled'; ?>>
                        <i class="fa fa-stop"></i> Stop Instance
                    </button>
                </div>

                <!-- Logs Explorer -->
                <div class="logs-section">
                    <div class="logs-header">
                        <h3 style="margin: 0; font-size: 1.1rem;"><i class="fa fa-terminal"></i> Console Output</h3>
                        <button onclick="refreshLogs()" class="secondary outline" style="width: auto; margin: 0; font-size: 0.8rem; padding: 0.25rem 0.75rem;">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>
                    <div id="logs-container" class="logs-container">Streaming logs...</div>
                </div>
            </main>

            <footer>
            </footer>
        </article>
    </div>

    <!-- Confirmation Modal -->
    <dialog id="confirm-modal">
        <article>
            <header>
                <a href="#close" aria-label="Close" class="close" onclick="closeModal('confirm-modal')"></a>
                <h3 id="modal-title">Confirm Action</h3>
            </header>
            <p id="modal-message">Are you sure you want to proceed?</p>
            <footer>
                <button class="secondary" onclick="closeModal('confirm-modal')">Cancel</button>
                <button id="modal-confirm-btn" onclick="executeAction()">Confirm</button>
            </footer>
        </article>
    </dialog>

    <!-- Loading Overlay -->
    <dialog id="loading-modal">
        <article style="text-align: center; padding: 3rem;">
            <span class="loader-spinner"></span>
            <h3 id="loading-title" style="margin-top: 1.5rem;">Processing Request</h3>
            <p id="loading-message">Communicating with the container daemon...</p>
        </article>
    </dialog>

    <script>
        const containerName = <?php echo json_encode($name); ?>;
        let pendingAction = null;

        function confirmAction(action) {
            pendingAction = action;
            const modal = document.getElementById('confirm-modal');
            const title = document.getElementById('modal-title');
            const message = document.getElementById('modal-message');
            const confirmBtn = document.getElementById('modal-confirm-btn');

            title.innerText = action === 'start' ? 'Start Container' : 'Stop Container';
            message.innerText = `Are you sure you want to ${action} "${containerName}"? This may affect connected services.`;
            
            confirmBtn.className = action === 'start' ? 'success' : 'error';
            modal.showModal();
        }

        function closeModal(id) {
            document.getElementById(id).close();
        }

        function executeAction() {
            if (!pendingAction) return;
            closeModal('confirm-modal');
            
            const loadModal = document.getElementById('loading-modal');
            document.getElementById('loading-title').innerText = pendingAction === 'start' ? 'Starting...' : 'Stopping...';
            loadModal.showModal();

            const url = `action.php?${pendingAction}=${encodeURIComponent(containerName)}`;
            window.location.href = url;
        }

        function refreshLogs() {
            const container = document.getElementById('logs-container');
            container.style.opacity = '0.5';
            
            fetch(`fetch_logs.php?name=${encodeURIComponent(containerName)}&lines=100`)
                .then(r => r.text())
                .then(data => {
                    container.innerText = data || 'No log output detected from this container.';
                    container.style.opacity = '1';
                    container.scrollTop = container.scrollHeight;
                })
                .catch(err => {
                    container.innerText = "Error syncing logs: " + err.message;
                    container.style.opacity = '1';
                });
        }

        // Auto-refresh logic
        window.addEventListener('DOMContentLoaded', () => {
            refreshLogs();
            setInterval(refreshLogs, 15000); // 15s refresh for performance balance
        });
    </script>
</body>
</html>