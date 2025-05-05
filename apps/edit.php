<?php
require_once '../includes/auth.php'; // Use centralized auth

$error_message = null;
$app_to_edit = null;

// Handle form submission for saving changes
if ($auth && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_app'])) { // Check for specific save button
    $id = $_POST['id'];
    $containerName = $_POST['containerName']; // Use containerName
    $image = $_POST['image'];
    $version = $_POST['version'];
    $url = $_POST['url']; // Use url
    $comment = $_POST['comment']; // Use comment

    try {
        $stmt = $db->prepare('UPDATE apps SET ContainerName = :ContainerName, Image = :Image, Version = :Version, Url = :Url, Comment = :Comment WHERE ID = :ID');
        $stmt->bindParam(':ContainerName', $containerName, PDO::PARAM_STR);
        $stmt->bindParam(':Image', $image, PDO::PARAM_STR);
        $stmt->bindParam(':Version', $version, PDO::PARAM_STR);
        $stmt->bindParam(':Url', $url, PDO::PARAM_STR);
        $stmt->bindParam(':Comment', $comment, PDO::PARAM_STR);
        $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
        $stmt->execute();
        header('Location: ../apps.php?updated=success'); // Redirect on success
        exit;
    } catch (PDOException $e) {
        // Log error: error_log("App Update DB Error: " . $e->getMessage());
        $error_message = "Failed to update app due to a database error.";
        // Re-fetch app data to display form again with error
        try {
            $stmt = $db->prepare('SELECT * FROM apps WHERE ID = :ID');
            $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
            $stmt->execute();
            $app_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $inner_e) {
             $error_message = "Failed to update app and could not reload data.";
        }
    }
}

// Handle form submission for selecting an app to edit
if ($auth && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_app'])) { // Check for specific edit button
    $id = $_POST['id'];
    try {
        $stmt = $db->prepare('SELECT * FROM apps WHERE ID = :ID');
        $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
        $stmt->execute();
        $app_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$app_to_edit) {
            $error_message = "Selected app not found.";
        }
    } catch (PDOException $e) {
        // Log error: error_log("App Edit Select DB Error: " . $e->getMessage());
        $error_message = "Failed to retrieve app details for editing due to a database error.";
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
        // Log error: error_log("App Edit Dropdown DB Error: " . $e->getMessage());
        $error_message = "Failed to retrieve app list due to a database error.";
    }
}
?>
<html>
<head>
    <title>Edit App</title>
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
                <h1>Edit App</h1>
              <button class="secondary" onclick="location.href='../apps.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <?php if ($error_message): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>

                <?php if (!empty($apps_list)): ?>
                    <!-- Form to select an app -->
                    <form method="post" style="<?php echo $app_to_edit ? 'display: none;' : ''; ?>">
                        <label for="id">Select an app to edit:</label>
                        <select id="id" name="id" required>
                            <?php
                            foreach ($apps_list as $app) {
                                echo "<option value='" . htmlspecialchars($app['ID']) . "'>" . htmlspecialchars($app['ContainerName']) . "</option>"; // Use ContainerName
                            }
                            ?>
                        </select>
                        <button type="submit" name="edit_app" class="btn">Edit</button>
                    </form>
                <?php elseif (!$error_message) : ?>
                     <p>No apps available to edit.</p>
                <?php endif; ?>

                <?php if ($app_to_edit): ?>
                    <!-- Form to edit the selected app -->
                    <form method="post">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($app_to_edit['ID']); ?>" />
                        <label for="containerName">Container Name</label> <!-- Use containerName -->
                        <input type="text" id="containerName" name="containerName" value="<?php echo htmlspecialchars($app_to_edit['ContainerName']); ?>" required />
                        <label for="image">Image</label>
                        <input type="text" id="image" name="image" value="<?php echo htmlspecialchars($app_to_edit['Image']); ?>" required />
                        <label for="version">Version</label>
                        <input type="text" id="version" name="version" value="<?php echo htmlspecialchars($app_to_edit['Version'] ?? ''); ?>" /> <!-- Handle potential missing Version -->
                        <label for="url">URL</label> <!-- Use url -->
                        <input type="text" id="url" name="url" value="<?php echo htmlspecialchars($app_to_edit['Url']); ?>" required />
                        <label for="comment">Comment</label> <!-- Use comment -->
                        <input type="text" id="comment" name="comment" value="<?php echo htmlspecialchars($app_to_edit['Comment']); ?>" />
                        <button type="submit" name="save_app" class="btn">Save Changes</button>
                        <button type="button" class="secondary" onclick="window.location.href='edit.php';">Cancel / Select Different App</button> 
                    </form>
                <?php endif; ?>
            </section>
        </main>
        <footer>
            <div class="container">
                <hr />
                <p>&copy; 2024</p>
            </div>
        </footer>
    </div>
</body>
</html>


