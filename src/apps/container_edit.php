<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
$db = get_db();

// Require admin privileges
require_admin();

if (!isset($_GET['name'])) {
    header('Location: ../apps.php');
    exit();
}

$containerName = $_GET['name'];
$escapedName = escapeshellarg($containerName);

// Get container details from Docker
$containerInfo = shell_exec("docker inspect " . $escapedName . " 2>&1");
$containerData = json_decode($containerInfo, true);

if (!$containerData || empty($containerData[0])) {
    header('Location: ../apps.php?error=container_not_found');
    exit();
}

$container = $containerData[0];
$currentName = ltrim($container['Name'], '/');
$containerId = $container['Id'];

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Security validation failed. Please try again.";
    } else {
        $newName = trim($_POST['new_name'] ?? '');
        
        // Validate new name
        if (empty($newName)) {
            $error_message = "Container name cannot be empty.";
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $newName)) {
            $error_message = "Container name can only contain letters, numbers, underscores, dots, and hyphens.";
        } elseif ($newName === $currentName) {
            $error_message = "New name is the same as current name.";
        } else {
            // Rename the container
            $renameCmd = "docker rename " . escapeshellarg($currentName) . " " . escapeshellarg($newName) . " 2>&1";
            $output = shell_exec($renameCmd);
            
            if (strpos($output, 'Error') === false && empty(trim($output))) {
                // Update database
                try {
                    $stmt = $db->prepare('UPDATE apps SET ContainerName = :new_name WHERE ContainerName = :old_name');
                    $stmt->bindParam(':new_name', $newName, PDO::PARAM_STR);
                    $stmt->bindParam(':old_name', $currentName, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    $success_message = "Container renamed successfully from '$currentName' to '$newName'.";
                    $currentName = $newName;
                    $containerName = $newName;
                } catch (PDOException $e) {
                    $error_message = "Container renamed in Docker but failed to update database: " . $e->getMessage();
                }
            } else {
                $error_message = "Failed to rename container: " . htmlspecialchars($output);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Edit Container - <?= htmlspecialchars($currentName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .info-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .info-value {
            color: #212529;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Edit Container</h1>
                <button class="secondary" onclick="location.href='container_info.php?name=<?= urlencode($containerName) ?>';">Back to Container</button>
            </section>
        </header>
        <hr />
        <main>
            <?php if ($success_message): ?>
                <div style="background-color: #d1e7dd; color: #0f5132; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div style="background-color: #f8d7da; color: #842029; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Container Information -->
            <section>
                <h2>Container Information</h2>
                <div class="info-section">
                    <div class="info-item">
                        <span class="info-label">Container ID:</span>
                        <span class="info-value"><?= htmlspecialchars(substr($containerId, 0, 12)) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Name:</span>
                        <span class="info-value"><?= htmlspecialchars($currentName) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Image:</span>
                        <span class="info-value"><?= htmlspecialchars($container['Config']['Image'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><?= htmlspecialchars($container['State']['Status'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($container['Created'] ?? 'now'))) ?></span>
                    </div>
                </div>
            </section>
            
            <!-- Rename Container Form -->
            <section>
                <h2>Rename Container</h2>
                <p style="color: #6c757d; margin-bottom: 1rem;">
                    <strong>Note:</strong> Renaming a container changes its name in Docker. 
                    Some properties defined in docker-compose files cannot be changed here.
                </p>
                
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <label for="new_name">New Container Name</label>
                    <input 
                        type="text" 
                        id="new_name" 
                        name="new_name" 
                        value="<?= htmlspecialchars($currentName) ?>" 
                        required
                        pattern="[a-zA-Z0-9_.-]+"
                        title="Container name can only contain letters, numbers, underscores, dots, and hyphens"
                    />
                    <small>Only letters, numbers, underscores, dots, and hyphens are allowed.</small>
                    
                    <button type="submit">Rename Container</button>
                </form>
            </section>
            
            <!-- Warning Section -->
            <section style="margin-top: 2rem;">
                <div style="background-color: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 1rem; border-radius: 4px;">
                    <strong>⚠️ Important Notes:</strong>
                    <ul style="margin-top: 0.5rem; margin-bottom: 0;">
                        <li>Renaming a container does not affect its configuration or data</li>
                        <li>The container must be stopped to rename it safely</li>
                        <li>Some orchestration tools may recreate containers with original names</li>
                        <li>Container properties defined in docker-compose files cannot be modified here</li>
                    </ul>
                </div>
            </section>
        </main>
        <footer>
            <hr />
            <section>
                <p>&copy; 2024 Container Manager</p>
            </section>
        </footer>
    </div>
</body>
</html>
