<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

$db = get_db();

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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <style>
        article {
            max-width: 900px;
            margin: 0 auto;
            box-shadow: var(--pico-card-sectioning-background-color);
        }
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--pico-spacing);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--pico-border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .checkbox-group {
            display: flex;
            gap: 2rem;
            background: var(--pico-card-sectioning-background-color);
            padding: 1rem;
            border-radius: var(--pico-border-radius);
            margin-bottom: 1rem;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            margin: 0;
            font-weight: 500;
        }

        .status-icon {
            font-size: 1.1rem;
        }
        
        .status-check { color: #388e3c; }
        .status-cross { color: #d32f2f; }

        .btn-delete {
            background-color: var(--pico-red-500);
            border-color: var(--pico-red-500);
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            width: auto;
            margin: 0;
        }

        .btn-delete:hover {
            background-color: var(--pico-red-600);
            border-color: var(--pico-red-600);
        }

        table {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <div class="header-nav">
                    <hgroup style="margin: 0;">
                        <h1>Container Access</h1>
                        <p>User: <strong><?= htmlspecialchars($user['username']) ?></strong></p>
                    </hgroup>
                    <button class="secondary outline" onclick="location.href='../users.php';" style="width: auto;">
                        <i class="fa fa-arrow-left"></i> Back to Users
                    </button>
                </div>
            </header>

            <main>
                <!-- PHP Message Handling -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i>
                        <span><?= htmlspecialchars($success_message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fa fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Add Permission Section -->
                <section>
                    <h2 style="font-size: 1.25rem;"><i class="fa fa-plus-circle"></i> Add Container Permission</h2>
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_permissions">
                        
                        <div class="grid">
                            <label for="container_id">
                                Target Container
                                <select id="container_id" name="container_id" required>
                                    <option value="" disabled selected>Select a container...</option>
                                    <?php foreach ($containers as $container): ?>
                                        <option value="<?= htmlspecialchars($container['ID']) ?>">
                                            <?= htmlspecialchars($container['ContainerName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div style="visibility: hidden; height: 0; width: 0;">Spacer for Grid</div>
                        </div>
                        
                        <fieldset>
                            <legend style="font-weight: bold; margin-bottom: 0.5rem;">Assigned Roles</legend>
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
                        
                        <button type="submit" class="contrast">
                            <i class="fa fa-save"></i> Save Permissions
                        </button>
                    </form>
                </section>

                <hr style="margin: 2.5rem 0;" />
                
                <!-- Current Permissions Section -->
                <section>
                    <h2 style="font-size: 1.25rem;"><i class="fa fa-list"></i> Current Access Privileges</h2>
                    <?php if (empty($permissions)): ?>
                        <div style="text-align: center; padding: 2rem; border: 2px dashed var(--pico-muted-border-color); border-radius: var(--pico-border-radius);">
                            <p style="margin: 0; color: var(--pico-muted-color);">No container permissions configured for this user.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-auto">
                            <table class="striped">
                                <thead>
                                    <tr>
                                        <th>Container</th>
                                        <th style="text-align: center;">View</th>
                                        <th style="text-align: center;">Start</th>
                                        <th style="text-align: center;">Stop</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permissions as $perm): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($perm['ContainerName']) ?></td>
                                            <td style="text-align: center;">
                                                <i class="status-icon <?= $perm['CanView'] ? 'fa fa-check status-check' : 'fa fa-times status-cross' ?>"></i>
                                            </td>
                                            <td style="text-align: center;">
                                                <i class="status-icon <?= $perm['CanStart'] ? 'fa fa-check status-check' : 'fa fa-times status-cross' ?>"></i>
                                            </td>
                                            <td style="text-align: center;">
                                                <i class="status-icon <?= $perm['CanStop'] ? 'fa fa-check status-check' : 'fa fa-times status-cross' ?>"></i>
                                            </td>
                                            <td style="text-align: right;">
                                                <form method="post" style="margin: 0; display: inline-block;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="action" value="delete_permission">
                                                    <input type="hidden" name="permission_id" value="<?= htmlspecialchars($perm['ID']) ?>">
                                                    <button type="submit" class="btn-delete" onclick="return confirmDelete(event)">
                                                        <i class="fa fa-trash"></i> Revoke
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </main>

            <footer>
            </footer>
        </article>
    </div>
    
    <script>
        function confirmDelete(event) {
            // Using a standard confirm since custom modals aren't requested, 
            // but stylized for better UX via standard JS alert flow
            return confirm('Are you sure you want to revoke these container permissions? This action cannot be undone.');
        }
    </script>
</body>
</html>