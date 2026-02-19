<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/docker.php';

class clist
{
    private $db;
    private $docker;
    private $user_id;
    private $isAdmin;
    private $allowed_containers;

    public function __construct()
    {
        $this->db = get_db();
        $this->docker = new Docker();
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
        $this->allowed_containers = $this->user_id ? get_user_containers($this->db, $this->user_id) : [];
    }

    public function render()
    {
        $containers = $this->docker->list();

        foreach ($containers as $containerInfo) {
            if (empty($containerInfo)) continue;

            $parts = explode(",", $containerInfo);
            if (count($parts) < 3) continue;

            $name = htmlspecialchars($parts[0], ENT_QUOTES, 'UTF-8');
            if (!$this->isAdmin && !in_array($parts[0], $this->allowed_containers)) continue;

            $status = htmlspecialchars($parts[1], ENT_QUOTES, 'UTF-8');
            $imagePart = $parts[2];
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
    }
}