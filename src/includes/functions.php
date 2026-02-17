<?php

/// Function to display a modal for any argument passed to it
function Modal(string $title, string $message): void {
        echo <<<HTML
        <dialog open id='errorDialog'>
            <article>
                <header>
                <button aria-label='Close' rel='prev' id='closeErrorDialog'></button>
                <p>
                    <strong>$title</strong>
                </p>
                </header>
                <p>
                <strong>$message</strong>
            </article>
        </dialog>
        <script>
          const errorDialog = document.getElementById('errorDialog');
          const closeButton = document.getElementById('closeErrorDialog');
          
          if (closeButton && errorDialog) {
            closeButton.addEventListener('click', () => {
              errorDialog.close();
            });
          }
        </script>
HTML;
}

/**
 * Check if a user has permission to perform an action on a container
 * 
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param string $container_name Container name
 * @param string $action Action to check (view, start, stop)
 * @return bool True if user has permission, false otherwise
 */
function check_container_permission(PDO $db, int $user_id, string $container_name, string $action): bool {
    global $_SESSION;
    
    // Admins have full access to all containers
    if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) {
        return true;
    }
    
    try {
        // Get container ID from name
        $stmt = $db->prepare('SELECT ID FROM apps WHERE ContainerName = :name');
        $stmt->bindParam(':name', $container_name, PDO::PARAM_STR);
        $stmt->execute();
        $container = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$container) {
            return false; // Container doesn't exist
        }
        
        // Check permissions
        $stmt = $db->prepare('SELECT CanView, CanStart, CanStop FROM container_permissions WHERE UserID = :user_id AND ContainerID = :container_id');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':container_id', $container['ID'], PDO::PARAM_INT);
        $stmt->execute();
        $perms = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$perms) {
            return false; // No permissions set for this user/container
        }
        
        // Check specific action
        switch (strtolower($action)) {
            case 'view':
            case 'logs':
            case 'status':
                return (bool)$perms['CanView'];
            case 'start':
                return (bool)$perms['CanStart'];
            case 'stop':
                return (bool)$perms['CanStop'];
            default:
                return false;
        }
    } catch (PDOException $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all containers that a user has view permission for
 * 
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return array Array of container names
 */
function get_user_containers(PDO $db, int $user_id): array {
    global $_SESSION;
    
    // Admins can see all containers
    if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) {
        try {
            $stmt = $db->prepare('SELECT ContainerName FROM apps ORDER BY ContainerName');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (PDOException $e) {
            error_log("Error fetching containers: " . $e->getMessage());
            return [];
        }
    }
    
    // For non-admins, get containers they have view permission for
    try {
        $stmt = $db->prepare('
            SELECT a.ContainerName 
            FROM apps a
            INNER JOIN container_permissions cp ON a.ID = cp.ContainerID
            WHERE cp.UserID = :user_id AND cp.CanView = 1
            ORDER BY a.ContainerName
        ');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        error_log("Error fetching user containers: " . $e->getMessage());
        return [];
    }
}

?>