<?php
if (!isset($_GET['name'])) {
    exit();
}
require_once '../includes/db.php';
require_once '../includes/auth.php'; 
require_once '../includes/functions.php';
require_once '../includes/docker.php';

$db = get_db();
$name = htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8');

// Check if user has permission to view this container
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !check_container_permission($db, $user_id, $name, 'view')) {
    header('Location: ../apps.php?error=unauthorized');
    exit();
}

// Fetch container status
$docker = new Docker();
$containerData = $docker->inspect($name);
if (!$containerData || empty($containerData[0])) {
    $status = 'Unknown';
} else {
    $container = $containerData[0];
    $status = $container['State']['Status'] ?? 'Unknown';
}

// Check user permissions for this container
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
$canStart = $isAdmin || check_container_permission($db, $user_id, $name, 'start');
$canStop = $isAdmin || check_container_permission($db, $user_id, $name, 'stop');

?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title><?php echo $name ? htmlspecialchars($name) : 'Container Detail'; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../custom.css" />
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <div class="header-nav">
                    <hgroup style="margin: 0;">
                        <h1><?php echo htmlspecialchars($name); ?></h1>
                    </hgroup>
                    <button class="secondary outline" onclick="location.href='../apps.php';" style="width: auto;">
                        <i class="fa fa-arrow-left"></i> Back to Apps
                    </button>
                </div>
            </header>

            <main>
                <!-- Action Notifications -->
                <div id="action-feedback" style="margin-top: 1rem;"></div>

                <!-- Status Row -->
                <div id="status-badge"
                    hx-get="action.php?action=status&name=<?= urlencode($name) ?>"
                    hx-trigger="load, every 1s"
                    hx-swap="innnerHTML"
                    hx-on::after-on-load="
                        const status = event.detail.xhr.responseText.trim().toLowerCase();
                        this.className = 'status-badge status-' + status;
                    "
                    class="status-badge status-<?= strtolower(trim($status)) ?>">
                    <i class="fa fa-circle" style="font-size: 8px; margin-right: 8px;"></i>
                </div>

                <!-- Controls -->
                <div class="action-group">
                    <?php if ($isAdmin): ?>
                    <button class="action-btn warning" onclick="location.href='edit.php?name=<?php echo urlencode($name); ?>';">
                        <i class="fa fa-pencil"></i> Configure
                    </button>
                    <?php endif; ?>
                    
                    <button 
                        class="action-btn success"
                        <?= !$canStart ? 'disabled style="opacity:0.5;"' : '' ?>
                        onclick="openConfirmModal('start')">
                        <i class="fa fa-play"></i> Start
                    </button>
                    <button 
                        class="action-btn error outline"
                        <?= !$canStop ? 'disabled style="opacity:0.5;"' : '' ?>
                        onclick="openConfirmModal('stop')">
                        <i class="fa fa-stop"></i> Stop
                    </button>
                </div>

                <div class="logs-section">
                    <div class="logs-header" style="display: flex; align-items: center; justify-content: space-between;">
                        <h3 style="margin: 0; font-size: 1.1rem;"><i class="fa fa-terminal"></i> Console Output</h3>
                        <button 
                            class="secondary outline"
                            hx-get="action.php?action=logs&name=<?= urlencode($name) ?>&lines=50"
                            hx-target="#logs-container"
                            hx-swap="innerHTML"
                            style="width: auto; margin: 0; font-size: 0.8rem; padding: 0.25rem 0.75rem;">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>
                    <div id="logs-container" class="logs-container"
                        hx-get="action.php?action=logs&name=<?= urlencode($name) ?>&lines=50"
                        hx-trigger="load, every 15s"
                        hx-swap="innerHTML"
                        style="background: #181c23; color: #d1d5db; font-family: 'Fira Code', monospace; padding: 1rem; border-radius: 8px; min-height: 200px; margin-top: 0.5rem; font-size: 0.95rem;">
                        Loading logs...
                    </div>
                </div>

            <footer>
            </footer>
        </article>
    </div>

    <!-- Confirmation Modal -->
    <dialog id="confirm-modal">
        <form 
            id="modal-form"
            hx-get="action.php"
            hx-target="#action-feedback"
            hx-swap="innerHTML"
            hx-on::after-request="this.closest('dialog').close()"
            style="text-align:center;"
        >
            <p id="modal-message">Are you sure?</p>

            <input type="hidden" name="action" id="modal-action" />
            <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>" />

            <button type="button" class="secondary" onclick="this.closest('dialog').close()">
                Cancel
            </button>

            <button type="submit" class="contrast" onclick="this.closest('dialog').close()">
                Confirm
            </button>
        </form>
    </dialog>

    
    <script>
        function openConfirmModal(action) {
            document.getElementById('modal-action').value = action;

            const message = action === 'start'
                ? 'Start this container?'
                : 'Stop this container?';

            document.getElementById('modal-message').innerText = message;

            document.getElementById('confirm-modal').showModal();
        }
    </script>
</body>
</html>