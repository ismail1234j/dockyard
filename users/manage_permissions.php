<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin privileges
require_admin();

// Get user ID from query string
if (!isset($_GET['id'])) {
    header('Location: ../users.php');
    exit;
}

$userId = intval($_GET['id']);

// Get user info
try {
    $stmt = $db->prepare('SELECT ID, username, email FROM users WHERE ID = :id');
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: ../users.php?error=user_not_found');
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Security validation failed. Please try again.";
    } else {
        switch ($_POST['action']) {
            case 'update_permissions':
                if (isset($_POST['container_id'], $_POST['permissions'])) {
                    $containerId = intval($_POST['container_id']);
                    $permissions = $_POST['permissions'];
                    
                    try {
                        // Check if a record already exists
                        $stmt = $db->prepare('SELECT ID FROM container_permissions WHERE UserID = :user_id AND ContainerID = :container_id');
                        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                        $stmt->bindParam(':container_id', $containerId, PDO::PARAM_INT);
                        $stmt->execute();
                        $existing_id = $stmt->fetchColumn();
                        
                        // Define permission values
                        $can_view = in_array('view', $permissions) ? 1 : 0;
                        $can_start = in_array('start', $permissions) ? 1 : 0;
                        $can_stop = in_array('stop', $permissions) ? 1 : 0;
                        
                        if ($existing_id) {
                            // Update existing record
                            $stmt = $db->prepare('
                                UPDATE container_permissions 
                                SET CanView = :can_view, CanStart = :can_start, CanStop = :can_stop 
                                WHERE ID = :id
                            ');
                            $stmt->bindParam(':id', $existing_id, PDO::PARAM_INT);
                        } else {
                            // Create new record
                            $stmt = $db->prepare('
                                INSERT INTO container_permissions (UserID, ContainerID, CanView, CanStart, CanStop)
                                VALUES (:user_id, :container_id, :can_view, :can_start, :can_stop)
                            ');
                            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                            $stmt->bindParam(':container_id', $containerId, PDO::PARAM_INT);
                        }
                        
                        $stmt->bindParam(':can_view', $can_view, PDO::PARAM_INT);
                        $stmt->bindParam(':can_start', $can_start, PDO::PARAM_INT);
                        $stmt->bindParam(':can_stop', $can_stop, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        $success_message = "Permissions updated successfully.";
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Missing required parameters.";
                }
                break;
                
            case 'delete_permission':
                if (isset($_POST['permission_id'])) {
                    $permission_id = intval($_POST['permission_id']);
                    
                    try {
                        $stmt = $db->prepare('DELETE FROM container_permissions WHERE ID = :id AND UserID = :user_id');
                        $stmt->bindParam(':id', $permission_id, PDO::PARAM_INT);
                        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                        $stmt->execute();
                        $success_message = "Permission removed successfully.";
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Missing permission ID.";
                }
                break;
        }
    }
}

// Get all containers
$containers = [];
try {
    $stmt = $db->prepare('SELECT ID, ContainerName FROM apps ORDER BY ContainerName');
    $stmt->execute();
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error loading containers: " . $e->getMessage();
}

// Get existing permissions for this user
$permissions = [];
try {
    $stmt = $db->prepare('
        SELECT cp.ID, cp.ContainerID, cp.CanView, cp.CanStart, cp.CanStop, a.ContainerName
        FROM container_permissions cp
        JOIN apps a ON cp.ContainerID = a.ID
        WHERE cp.UserID = :user_id
        ORDER BY a.ContainerName
    ');
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error loading permissions: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Manage Container Permissions - <?= htmlspecialchars($user['username']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .checkbox-group {
            display: flex;
            gap: 1rem;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Manage Container Permissions</h1>
                <h3>User: <?= htmlspecialchars($user['username']) ?></h3>
                <button class="secondary" onclick="location.href='../users.php';">Back to Users</button>
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
            
            <!-- Add new permission form -->
            <section>
                <h2>Add Container Permission</h2>
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_permissions">
                    
                    <label for="container_id">Container</label>
                    <select id="container_id" name="container_id" required>
                        <option value="">Select a container...</option>
                        <?php foreach ($containers as $container): ?>
                            <option value="<?= htmlspecialchars($container['ID']) ?>">
                                <?= htmlspecialchars($container['ContainerName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <fieldset>
                        <legend>Permissions</legend>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="permissions[]" value="view" checked>
                                View
                            </label>
                            <label>
                                <input type="checkbox" name="permissions[]" value="start">
                                Start
                            </label>
                            <label>
                                <input type="checkbox" name="permissions[]" value="stop">
                                Stop
                            </label>
                        </div>
                    </fieldset>
                    
                    <button type="submit">Save Permissions</button>
                </form>
            </section>
            
            <!-- Current permissions list -->
            <section style="margin-top: 2rem;">
                <h2>Current Permissions</h2>
                <?php if (empty($permissions)): ?>
                    <p>No container permissions configured for this user.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Container</th>
                                <th>View</th>
                                <th>Start</th>
                                <th>Stop</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $perm): ?>
                                <tr>
                                    <td><?= htmlspecialchars($perm['ContainerName']) ?></td>
                                    <td><?= $perm['CanView'] ? '✓' : '✕' ?></td>
                                    <td><?= $perm['CanStart'] ? '✓' : '✕' ?></td>
                                    <td><?= $perm['CanStop'] ? '✓' : '✕' ?></td>
                                    <td>
                                        <form method="post" style="margin: 0" onsubmit="return confirm('Are you sure you want to delete this permission?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_permission">
                                            <input type="hidden" name="permission_id" value="<?= htmlspecialchars($perm['ID']) ?>">
                                            <button type="submit" class="pico-background-red-500" style="padding: 0.25rem 0.5rem;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
