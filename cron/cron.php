<?php
/**
 * Docker Container Information Cron Job
 * 
 * This script communicates with the Docker daemon API to retrieve information about all containers
 * and updates the database with the relevant information.
 * 
 * Usage: 
 * - Call this script via cron or manually
 * - Pass the Docker daemon IP as a GET parameter, e.g., cron.php?docker_ip=192.168.1.100
 * - Optionally specify the Docker daemon port with docker_port parameter (default: 2375)
 */

// Get Docker daemon IP from GET parameter
$docker_ip = isset($_GET['docker_ip']) ? $_GET['docker_ip'] : null;
$docker_port = isset($_GET['docker_port']) ? $_GET['docker_port'] : 2375;

// Check if Docker IP is provided
if (empty($docker_ip)) {
    die("Error: Docker daemon IP address is required. Use ?docker_ip=X.X.X.X in the URL.");
}

// Initialize database connection
try {
    $db = new PDO('sqlite:../db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to make Docker API requests
function dockerApiRequest($endpoint, $docker_ip, $docker_port) {
    $url = "http://{$docker_ip}:{$docker_port}/v1.41{$endpoint}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        error_log("Docker API Error: HTTP code {$http_code} for URL {$url}");
        return null;
    }
    
    if (curl_errno($ch)) {
        error_log("Docker API Error: " . curl_error($ch));
        return null;
    }
    
    curl_close($ch);
    return json_decode($response, true);
}

// Get all containers (both running and stopped)
$containers = dockerApiRequest('/containers/json?all=true', $docker_ip, $docker_port);

if ($containers === null) {
    die("Error connecting to Docker daemon at {$docker_ip}:{$docker_port}. Make sure the Docker API is accessible and the daemon allows remote connections.");
}

// Arrays to track containers in database and Docker
$updated_containers = array();
$container_statuses = array();

// Process each container from Docker API
foreach ($containers as $container) {
    $container_id = $container['Id'];
    $container_name = ltrim($container['Names'][0], '/'); // Remove leading slash
    $image = $container['Image'];
    $image_parts = explode(':', $image);
    $image_name = $image_parts[0];
    $version = isset($image_parts[1]) ? $image_parts[1] : 'latest';
    
    // Determine container status
    $status = strtolower($container['State']);
    
    // Get more detailed container information
    $container_info = dockerApiRequest("/containers/{$container_id}/json", $docker_ip, $docker_port);
    if ($container_info !== null) {
        // Extract port mappings for URL
        $url = "";
        if (isset($container_info['NetworkSettings']['Ports'])) {
            foreach ($container_info['NetworkSettings']['Ports'] as $containerPort => $hostPorts) {
                if ($hostPorts !== null) {
                    foreach ($hostPorts as $hostBinding) {
                        $url = "http://{$docker_ip}:{$hostBinding['HostPort']}";
                        break 2; // Use the first port mapping found
                    }
                }
            }
        }
        
        // Check if this container already exists in the database
        $stmt = $db->prepare('SELECT ID FROM apps WHERE ContainerName = :containerName');
        $stmt->bindParam(':containerName', $container_name, PDO::PARAM_STR);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing container record
            try {
                $stmt = $db->prepare('UPDATE apps SET 
                    Image = :image, 
                    Version = :version, 
                    Status = :status, 
                    Url = :url 
                    WHERE ID = :id');
                $stmt->bindParam(':image', $image_name, PDO::PARAM_STR);
                $stmt->bindParam(':url', $url, PDO::PARAM_STR);
                $stmt->bindParam(':id', $existing['ID'], PDO::PARAM_INT);
                $stmt->execute();
                
                $updated_containers[] = $container_name;
                $container_statuses[$container_name] = $status;
            } catch (PDOException $e) {
                    error_log("Database update error: " . $e->getMessage());

            }
        } else {
            // Add new container to database
            // Get a default comment for new containers
            $comment = "Added automatically by cron job on " . date('Y-m-d H:i:s');
            
            try {
                $stmt = $db->prepare('INSERT INTO apps 
                    (ContainerName, Image, Comment, Url, Version, Status) 
                    VALUES (:containerName, :image, :comment, :url, :version, :status)');
                $stmt->bindParam(':containerName', $container_name, PDO::PARAM_STR);
                $stmt->bindParam(':image', $image_name, PDO::PARAM_STR);
                $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
                $stmt->bindParam(':url', $url, PDO::PARAM_STR);
                $stmt->execute();
                
                $updated_containers[] = $container_name;
                $container_statuses[$container_name] = $status;
            } catch (PDOException $e) {
                    error_log("Database insert error: " . $e->getMessage());
            }
        }
    }
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