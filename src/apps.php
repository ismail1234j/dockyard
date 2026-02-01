<?php
require_once 'includes/auth.php'; // Use centralized auth
require_once 'includes/functions.php';

$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
$user_id = $_SESSION['user_id'] ?? null;

if (isset($_GET['info'])) {
    $name = htmlspecialchars($_GET['info']);
    header("Location: apps/container_info.php?name=$name");
    exit();
}

?>
<!DOCTYPE html>
<html data-theme="light">
<head>
  <title>Container Manager</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .status-running { color: green; }
    .status-exited, .status-stopped { color: red; }
    .status-created, .status-unknown { color: orange; }
    .action-buttons { display: flex; gap: 8px; }
    .action-button { 
        cursor: pointer; 
        padding: 5px 10px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 2px;
    }
    .action-button i { margin-right: 5px; }
    .action-start { background-color: #50C878; color: white; }
    .action-stop { background-color: #FF6B6B; color: white; }
    .action-logs { background-color: #5DADE2; color: white; }
    .view-logs-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); }
    .modal-content { background-color: #13171f; margin: 5% auto; padding: 20px; width: 90%; max-width: 1000px; max-height: 80vh; overflow-y: auto; border-radius: 8px; }
    .container-logs { background-color: #1E1E1E; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; max-height: 60vh; overflow-y: auto; color: #CCCCCC; }
    .close-button { color: #aaaaaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .close-button:hover { color: white; }
    .spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
    .refresh-table { margin-left: auto; margin-right: 10px; }
    .table-header { display: flex; align-items: center; margin-bottom: 10px; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="container" style="margin-top: 6%">
    <header>
      <section>
        <h1>Container Manager</h1>
        <div class="" style="display: flex; gap: 10px; align-items: center;">
          <button class="secondary" onclick="location.href='index.php';">Back</button>
          <?php if ($isAdmin): ?>
            <button class="pico-background-amber-200" onclick="location.href='permissions.php';">Manage Permissions</button>
          <?php endif; ?>
        </div>
      </section>
    </header>
    <hr />
    <main>
      <section>
        <div class="table-header">
          <h2>Containers</h2>
          <button class="refresh-table" onclick="refreshTable()">
            <i class="fa fa-refresh"></i> Refresh
          </button>
        </div>
        
        <div id="container-table-container" class="overflow-auto">
          <table>
            <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Image</th>
              <th scope="col">Version</th>
              <th scope="col">Status</th>
              <th scope="col">Port</th>
              <th scope="col"></th>
            </tr>
            </thead>
            <tbody id="container-table-body">
            </tbody>
          </table>
        </div>
      </section>
    </main>
    
    <!-- Logs Modal -->
    <div id="logs-modal" class="view-logs-modal">
      <div class="modal-content">
        <span class="close-button" onclick="closeLogsModal()">&times;</span>
        <h2 id="logs-title">Container Logs</h2>
        <div id="container-logs" class="container-logs">Loading logs...</div>
        <br>
        <button onclick="refreshLogs()">Refresh Logs</button>
      </div>
    </div>
    
    <footer>
      <hr />
      <section>
        <p>&copy; 2024 Container Manager</p>
      </section>
    </footer>
  </div>
  
  <script>window.csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;</script>
  <script src="/functions.js"></script>
  <script>refreshTable();</script>
</body>
</html>
