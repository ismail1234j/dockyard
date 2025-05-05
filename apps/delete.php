<?php
require_once '../includes/auth.php'; // Use centralized auth

$error_message = null;
$success_message = null;

if ($auth) { // auth.php sets $auth
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Security validation failed. Please try again.";
        } else {
            $id = $_POST['id'];
            try {
                $stmt = $db->prepare('DELETE FROM apps WHERE ID = :ID');
                $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
                $stmt->execute();
                // Redirect back to apps.php after successful deletion
                header('Location: ../apps.php?deleted=success'); // Add query param for feedback
                exit;
            } catch (PDOException $e) {
                // Log error: error_log("App Deletion DB Error: " . $e->getMessage());
                $error_message = "Failed to delete app due to a database error.";
            }
        }
    }
}

// Fetch apps for the dropdown
$apps_list = [];
if ($auth) {
    try {
        $stmt = $db->prepare('SELECT ID, ContainerName FROM apps'); // Use ContainerName
        $stmt->execute();
        $apps_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error: error_log("App Deletion Dropdown DB Error: " . $e->getMessage());
        $error_message = "Failed to retrieve app list due to a database error.";
    }
}
?>
<html>
<head>
    <title>Delete App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"/>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
    />
</head>
<body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Delete App</h1>
              <button class="secondary" onclick="location.href='../apps.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <?php if ($error_message): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>
                <?php if (empty($apps_list) && !$error_message): ?>
                    <p>No apps available to delete.</p>
                <?php elseif (!$error_message): ?>
                    <div class="overflow-auto">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <label for="id">Select an app to delete:</label>
                            <select id="id" name="id" required>
                                <?php
                                foreach ($apps_list as $app) {
                                    echo "<option value='" . htmlspecialchars($app['ID']) . "'>" . htmlspecialchars($app['ContainerName']) . "</option>"; // Use ContainerName
                                }
                                ?>
                            </select>
                            <input class="pico-background-red-500" type="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this app?');">
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        <footer>
            <hr />
            <section>
                <p>&copy; 2021 Apps</p>
            </section>
        </footer>
    </div>
</body>
</html>
