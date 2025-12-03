<?php
/**
 * Docker Container Information and Monitoring Cron Job
 * 
 * This script communicates with the Docker daemon API to retrieve information about all containers
 * and updates the database with the relevant information. It also checks the availability of 
 * container services via ping.
 * 
 * Usage: 
 * - Call this script via cron or manually
 * - Optionally pass docker_ip parameter (defaults to using Docker socket)
 * - Set check_links=true to test container URLs
 */

// Include Docker API functions
require_once dirname(__DIR__) . '/includes/docker.php';

// Initialize database connection
try {
    $db = new PDO('sqlite:' . dirname(__DIR__) . '/data/db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Enable foreign key constraints
    $db->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if we should use Docker socket (default) or HTTP API
$useSocket = !isset($_GET['docker_ip']);
$docker_ip = isset($_GET['docker_ip']) ? $_GET['docker_ip'] : 'localhost';
$docker_port = isset($_GET['docker_port']) ? $_GET['docker_port'] : 2375;
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

// Get container information from Docker
if ($useSocket) {
    // Use Docker socket API via our includes/docker.php functions
    try {
        $containers = docker_request('GET', '/containers/json?all=true');
    } catch (Exception $e) {
        die("Error connecting to Docker daemon via socket: " . $e->getMessage());
    }
} else {
    // Use Docker HTTP API (remote daemon)
    $url = "http://{$docker_ip}:{$docker_port}/v1.41/containers/json?all=true";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        die("Docker API Error: HTTP code {$http_code} for URL {$url}");
    }
    
    if (curl_errno($ch)) {
        die("Docker API Error: " . curl_error($ch));
    }
    
    curl_close($ch);
    $containers = json_decode($response, true);
    
    if ($containers === null) {
        die("Error connecting to Docker daemon at {$docker_ip}:{$docker_port}. Make sure the Docker API is accessible.");
    }
}

// Process each container from Docker API
foreach ($containers as $container) {
    $container_id = $container['Id'];
    $container_name = ltrim($container['Names'][0], '/'); // Remove leading slash
    $image = $container['Image'];
    $image_parts = explode(':', $image);
    $image_name = $image_parts[0];
    $version = isset($image_parts[1]) ? $image_parts[1] : 'latest';
    
    // Track Docker container IDs for cleanup
    $docker_container_ids[] = $container_id;
    
    // Determine container status
    $status = strtolower($container['State']);
    
    // Get port mappings for URL and Port field
    $url = "";
    $port = "";
    $ports = [];
    
    if (isset($container['Ports']) && !empty($container['Ports'])) {
        $port_mappings = [];
        foreach ($container['Ports'] as $port_info) {
            if (isset($port_info['PublicPort'])) {
                $port_mappings[] = "{$port_info['PublicPort']}";
                $ports[] = "{$docker_ip}:{$port_info['PublicPort']}";
                if (empty($url)) {
                    $url = "{$docker_ip}:{$port_info['PublicPort']}";
                }
            }
        }
        $port = implode(', ', $port_mappings);
    }
    
    // Check if this container already exists in the database (by ContainerID)
    $stmt = $db->prepare('SELECT ID, Url FROM apps WHERE ContainerID = :containerId');
    $stmt->bindParam(':containerId', $container_id, PDO::PARAM_STR);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing container record
        try {
            // Use database transaction for consistency
            $db->beginTransaction();
            
            $url = !empty($existing['Url']) ? $existing['Url'] : $url;
            
            // Check URL availability if requested
            $pingStatus = null;
            $pingTime = null;
            
            if ($check_links && !empty($url)) {
                $pingStatus = check_url_availability($url) ? 1 : 0;
                $pingTime = date('Y-m-d H:i:s');
            }
            
            $stmt = $db->prepare('UPDATE apps SET 
                ContainerName = :containerName,
                Image = :image, 
                Version = :version, 
                Status = :status,
                Port = :port,
                UpdatedAt = CURRENT_TIMESTAMP
                ' . ($check_links ? ', LastPingStatus = :pingStatus, LastPingTime = :pingTime' : '') . '
                WHERE ID = :id');
                
            $stmt->bindParam(':containerName', $container_name, PDO::PARAM_STR);
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
            $stmt->bindParam(':containerId', $container_id, PDO::PARAM_STR);
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
// This maintains database consistency and removes orphaned permission records
try {
    if (!empty($docker_container_ids)) {
        $placeholders = implode(',', array_fill(0, count($docker_container_ids), '?'));
        $stmt = $db->prepare("SELECT ID, ContainerName FROM apps WHERE ContainerID NOT IN ($placeholders) AND ContainerID IS NOT NULL");
        $stmt->execute($docker_container_ids);
        $removed_containers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($removed_containers)) {
            foreach ($removed_containers as $removed) {
                // Delete will cascade to container_permissions due to foreign key constraint
                $deleteStmt = $db->prepare('DELETE FROM apps WHERE ID = :id');
                $deleteStmt->bindParam(':id', $removed['ID'], PDO::PARAM_INT);
                $deleteStmt->execute();
                error_log("Removed container from database: " . $removed['ContainerName']);
            }
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