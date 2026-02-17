<?php
/**
 * Docker Container Information and Monitoring Cron Job
 * 
 * This script retrieves information about all Docker containers using the manage_containers.sh script
 * and updates the database with the relevant information. It also checks the availability of 
 * container services via ping.
 * 
 * Usage: 
 * - Call this script via cron or manually
 * - Set check_links=true to test container URLs
 */

include_once '../includes/db.php';

// Path to the container management script
$scriptPath = dirname(__DIR__) . '/private/manage_containers.sh';
if (!file_exists($scriptPath)) {
    die("Error: manage_containers.sh not found at: $scriptPath");
}

// Initialize database connection
$db = get_db();

// Check if we should check container URLs
$check_links = isset($_GET['check_links']) && $_GET['check_links'] === 'true';

// Arrays to track containers in database and Docker
$updated_containers = array();
$container_statuses = array();
$docker_container_ids = array(); // Track Docker container IDs for cleanup

// Function to ping a URL and check if it's available
function check_url_availability($url) {
    if (empty($url)) {
        return false;
    }
    
    // Add http:// if not present
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        $url = 'http://' . $url;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 400;
}

// Get container information using the bash script
$output = shell_exec("bash " . escapeshellarg($scriptPath) . " list 2>&1");

if ($output === null || $output === false) {
    die("Error: Failed to execute manage_containers.sh list command");
}

// Parse the output - format is: name,status,image,ports (one per line)
$lines = explode("\n", trim($output));
$containers = array();

// Get container information using the bash script
$output = shell_exec("bash " . escapeshellarg($scriptPath) . " list 2>&1");

if ($output === null || $output === false) {
    die("Error: Failed to execute manage_containers.sh list command");
}

// Parse the output - format is: name,status,image,ports (one per line)
$lines = explode("\n", trim($output));
$containers = array();

foreach ($lines as $line) {
    if (empty($line)) continue;
    
    $parts = explode(',', $line);
    if (count($parts) < 3) continue;
    
    // Parse container info from bash script output
    $container = array(
        'name' => trim($parts[0]),
        'status' => trim($parts[1]),
        'image' => trim($parts[2]),
        'ports' => isset($parts[3]) ? trim($parts[3]) : ''
    );
    
    $containers[] = $container;
}

if (empty($containers)) {
    echo "No containers found or error parsing output.<br>";
    exit(0);
}

// Process each container from bash script output
foreach ($containers as $container) {
    $container_name = $container['name'];
    $image_full = $container['image'];
    $image_parts = explode(':', $image_full);
    $image_name = $image_parts[0];
    $version = isset($image_parts[1]) ? $image_parts[1] : 'latest';
    
    // Parse status - manage_containers.sh returns format like "Up 2 hours" or "Exited (0) 5 minutes ago"
    $status_raw = $container['status'];
    if (stripos($status_raw, 'Up') === 0) {
        $status = 'running';
    } elseif (stripos($status_raw, 'Exited') === 0) {
        $status = 'exited';
    } elseif (stripos($status_raw, 'Created') === 0) {
        $status = 'created';
    } elseif (stripos($status_raw, 'Restarting') === 0) {
        $status = 'restarting';
    } elseif (stripos($status_raw, 'Paused') === 0) {
        $status = 'paused';
    } else {
        $status = 'unknown';
    }
    
    // Get container ID for tracking (we'll use the name as fallback since bash script doesn't return ID)
    // Docker CLI output format doesn't include container ID in simple list
    $container_id = null; // Will be NULL for bash script method
    
    // Parse ports
    $port = $container['ports'];
    $url = "";
    if (!empty($port)) {
        // Extract first port for URL
        $port_parts = explode(',', $port);
        if (!empty($port_parts[0])) {
            $url = "localhost:" . trim($port_parts[0]);
        }
    }
    
    // Check if this container already exists in the database (by ContainerName since we don't have ID)
    $stmt = $db->prepare('SELECT ID, Url, ContainerID FROM apps WHERE ContainerName = :containerName');
    $stmt->bindParam(':containerName', $container_name, PDO::PARAM_STR);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing container record
        try {
            // Use database transaction for consistency
            $db->beginTransaction();
            
            $url = !empty($existing['Url']) ? $existing['Url'] : $url;
            
            /*

            // Update container ID if we have it and it's not set
            if (!empty($container_id) && empty($existing['ContainerID'])) {
                $stmt = $db->prepare('UPDATE apps SET ContainerID = :containerId WHERE ID = :id');
                $stmt->bindParam(':containerId', $container_id, PDO::PARAM_STR);
                $stmt->bindParam(':id', $existing['ID'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
            */

            // Check URL availability if requested
            $pingStatus = null;
            $pingTime = null;
            
            if ($check_links && !empty($url)) {
                $pingStatus = check_url_availability($url) ? 1 : 0;
                $pingTime = date('Y-m-d H:i:s');
            }
            
            $stmt = $db->prepare('UPDATE apps SET 
                Image = :image, 
                Version = :version, 
                Status = :status,
                Port = :port,
                UpdatedAt = CURRENT_TIMESTAMP
                ' . ($check_links ? ', LastPingStatus = :pingStatus, LastPingTime = :pingTime' : '') . '
                WHERE ID = :id');
                
            $stmt->bindParam(':image', $image_name, PDO::PARAM_STR);
            $stmt->bindParam(':version', $version, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':port', $port, PDO::PARAM_STR);
            $stmt->bindParam(':id', $existing['ID'], PDO::PARAM_INT);
            
            if ($check_links) {
                $stmt->bindParam(':pingStatus', $pingStatus, PDO::PARAM_INT);
                $stmt->bindParam(':pingTime', $pingTime, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            $db->commit();
            
            $updated_containers[] = $container_name;
            $container_statuses[$container_name] = $status;
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Database update error: " . $e->getMessage());
        }
    } else {
        // Add new container to database
        $comment = "Added automatically by cron job on " . date('Y-m-d H:i:s');
        
        try {
            // Use database transaction for consistency
            $db->beginTransaction();
            
            $stmt = $db->prepare('INSERT INTO apps 
                (ContainerName, ContainerID, Image, Version, Status, Comment, Url, Port) 
                VALUES (:containerName, :containerId, :image, :version, :status, :comment, :url, :port)');
                
            $stmt->bindParam(':containerName', $container_name, PDO::PARAM_STR);
            $stmt->bindParam(':containerId', $container_id, PDO::PARAM_STR); // Will be NULL
            $stmt->bindParam(':image', $image_name, PDO::PARAM_STR);
            $stmt->bindParam(':version', $version, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':url', $url, PDO::PARAM_STR);
            $stmt->bindParam(':port', $port, PDO::PARAM_STR);
            $stmt->execute();
            
            $db->commit();
            
            $updated_containers[] = $container_name;
            $container_statuses[$container_name] = $status;
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Database insert error: " . $e->getMessage());
        }
    }
}

// Clean up containers that no longer exist in Docker
// Get all container names from database
try {
    $stmt = $db->prepare('SELECT ID, ContainerName FROM apps');
    $stmt->execute();
    $db_containers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $current_container_names = array_column($containers, 'name');
    
    foreach ($db_containers as $db_container) {
        if (!in_array($db_container['ContainerName'], $current_container_names)) {
            // Container no longer exists, delete it (will cascade to permissions)
            $deleteStmt = $db->prepare('DELETE FROM apps WHERE ID = :id');
            $deleteStmt->bindParam(':id', $db_container['ID'], PDO::PARAM_INT);
            $deleteStmt->execute();
            error_log("Removed container from database: " . $db_container['ContainerName']);
        }
    }
} catch (PDOException $e) {
    error_log("Container cleanup error: " . $e->getMessage());
}

// Output summary of changes
echo "Docker container synchronization completed at " . date('Y-m-d H:i:s') . "<br>";
echo "Total containers processed: " . count($updated_containers) . "<br>";
echo "<hr>";
echo "<h3>Container Status Summary:</h3>";
echo "<ul>";
foreach ($container_statuses as $name => $status) {
    echo "<li>{$name}: {$status}</li>";
}
echo "</ul>";

if ($check_links) {
    echo "<hr>";
    echo "<h3>Container Link Status:</h3>";
    echo "<ul>";
    
    $stmt = $db->prepare("SELECT ContainerName, Url, LastPingStatus, LastPingTime FROM apps WHERE LastPingTime IS NOT NULL ORDER BY LastPingTime DESC");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['LastPingStatus'] ? 'Available' : 'Unavailable';
        $statusColor = $row['LastPingStatus'] ? 'green' : 'red';
        echo "<li>{$row['ContainerName']} ({$row['Url']}): <span style='color:{$statusColor};'>{$status}</span> (Last checked: {$row['LastPingTime']})</li>";
    }
    
    echo "</ul>";
}