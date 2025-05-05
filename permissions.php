<?php
require_once 'includes/auth.php'; // Use centralized auth
require_once 'includes/functions.php';

// Require admin privileges
require_admin();

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
                if (isset($_POST['user_id'], $_POST['container_id'], $_POST['permissions'])) {
                    $user_id = $_POST['user_id'];
                    $container_id = $_POST['container_id'];
                    $permissions = $_POST['permissions'];
                    
                    try {
                        // Check if a record already exists
                        $stmt = $db->prepare('SELECT ID FROM container_permissions WHERE UserID = :user_id AND ContainerID = :container_id');
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->bindParam(':container_id', $container_id, PDO::PARAM_INT);
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
                            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                            $stmt->bindParam(':container_id', $container_id, PDO::PARAM_INT);
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
                    $permission_id = $_POST['permission_id'];
                    
                    try {
                        $stmt = $db->prepare('DELETE FROM container_permissions WHERE ID = :id');
                        $stmt->bindParam(':id', $permission_id, PDO::PARAM_INT);
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

// Get all users (except current admin)
$users = [];
try {
    $stmt = $db->prepare('SELECT ID, username FROM users WHERE username != :current_user ORDER BY username');
    $stmt->bindParam(':current_user', $_SESSION['username'], PDO::PARAM_STR);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error loading users: " . $e->getMessage();
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

// Get existing permissions
$permissions = [];
try {
    $stmt = $db->prepare('
        SELECT cp.ID, cp.UserID, cp.ContainerID, cp.CanView, cp.CanStart, cp.CanStop,
               u.username, a.ContainerName
        FROM container_permissions cp
        JOIN users u ON cp.UserID = u.ID
        JOIN apps a ON cp.ContainerID = a.ID
        ORDER BY u.username, a.ContainerName
    ');
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error loading permissions: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Container Permissions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        .permission-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #dee2e6;
        }
        .permission-list {
            margin-top: 2rem;
        }
        .permission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .permission-actions {
            display: flex;
            gap: 0.5rem;
        }
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
                <h1>Container Permissions</h1>
                <button class="secondary" onclick="location.href='apps.php';">Back</button>
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
                <h2>Add New Permission</h2>
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_permissions">
                    
                    <div class="grid">
                        <div>
                            <label for="user_id">User</label>
                            <select id="user_id" name="user_id" required>
                                <option value="">Select a user...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['ID']) ?>">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="container_id">Container</label>
                            <select id="container_id" name="container_id" required>
                                <option value="">Select a container...</option>
                                <?php foreach ($containers as $container): ?>
                                    <option value="<?= htmlspecialchars($container['ID']) ?>">
                                        <?= htmlspecialchars($container['ContainerName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
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
            <section class="permission-list">
                <h2>Current Permissions</h2>
                <?php if (empty($permissions)): ?>
                    <p>No permissions configured yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
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
                                    <td><?= htmlspecialchars($perm['username']) ?></td>
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