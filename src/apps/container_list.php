<?php
require_once '../includes/auth.php'; // Use centralized auth
require_once '../includes/functions.php';
require_once '../includes/db.php';
$db = get_db();

$containerList = shell_exec("bash ../private/manage_containers.sh list");
$containers = explode("\n", $containerList);

// Get list of containers user has permission to view
$user_id = $_SESSION['user_id'] ?? null;
$allowed_containers = $user_id ? get_user_containers($db, $user_id) : [];

foreach ($containers as $containerInfo) {
    if (empty($containerInfo)) continue;

    $parts = explode(",", $containerInfo);

    if (count($parts) < 3) continue; // Ensure minimum required fields

    // Assign variables safely
    $name = htmlspecialchars($parts[0], ENT_QUOTES, 'UTF-8');
    
    // Skip containers user doesn't have permission to view (unless admin)
    $isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
    if (!$isAdmin && !in_array($parts[0], $allowed_containers)) {
        continue;
    }
    
    $status = htmlspecialchars($parts[1], ENT_QUOTES, 'UTF-8');
    $imagePart = $parts[2];

    // Extract image and version from part[2]
    $colonPos = strrpos($imagePart, ':');
    $image = $colonPos !== false ? substr($imagePart, 0, $colonPos) : $imagePart;
    $version = $colonPos !== false ? substr($imagePart, $colonPos + 1) : 'latest';

    $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
    $version = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');

    echo "<tr>
            <td>$name</td>
            <td>$image</td>
            <td>$version</td>
            <td>$status</td>";

    // Optional port field (part[3])
    if (!empty($parts[3])) {
        $port = htmlspecialchars($parts[3], ENT_QUOTES, 'UTF-8');
        echo "<td>$port</td>";
    } else {
        echo "<td>-</td>";
    }

    echo "<td><button onclick=\"location.href='apps.php?info=" . urlencode($name) . "';\">Info</button></td>
          </tr>";
}
