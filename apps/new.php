<?php
require_once '../includes/auth.php'; // Use centralized auth

$error_message = null;

if ($auth) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Security validation failed. Please try again.";
        } else {
            // Validate and sanitize inputs
            $containerName = trim($_POST['containerName']);
            $image = trim($_POST['image']);
            $version = trim($_POST['version']);
            $status = 'Unknown'; // Default status
            $url = trim($_POST['url']);
            $comment = trim($_POST['comment']);
            
            // Input validation
            if (empty($containerName)) {
                $error_message = "Container name is required";
            } elseif (empty($image)) {
                $error_message = "Image name is required";
            } elseif (empty($url)) {
                $error_message = "URL is required";
            } elseif (strlen($containerName) > 100 || strlen($image) > 100 || strlen($url) > 100) {
                $error_message = "Input fields must be less than 100 characters";
            } else {
                try {
                    // Note: setup.php doesn't define Version or Status columns by default.
                    // Assuming they exist or will be added:
                    $stmt = $db->prepare('INSERT INTO apps (ContainerName, Image, Version, Status, Comment, Url) VALUES (:ContainerName, :Image, :Version, :Status, :Comment, :Url)');
                    $stmt->bindParam(':ContainerName', $containerName, PDO::PARAM_STR);
                    $stmt->bindParam(':Image', $image, PDO::PARAM_STR);
                    $stmt->bindParam(':Version', $version, PDO::PARAM_STR);
                    $stmt->bindParam(':Status', $status, PDO::PARAM_STR);
                    $stmt->bindParam(':Comment', $comment, PDO::PARAM_STR);
                    $stmt->bindParam(':Url', $url, PDO::PARAM_STR);
                    $stmt->execute();
                    header('Location: ../apps.php?created=success'); // Redirect on success
                    exit;
                } catch (PDOException $e) {
                    // Log error: error_log("App Creation DB Error: " . $e->getMessage());
                    if (strpos($e->getMessage(), 'no such column: Version') !== false || strpos($e->getMessage(), 'no such column: Status') !== false) {
                         $error_message = "Database error: The 'apps' table schema might be missing 'Version' or 'Status' columns. Please run setup.php or update the table.";
                    } else {
                        $error_message = "Failed to create app due to a database error.";
                    }
                }
            }
        }
    }
}
?>
<html>
<head>
    <title>New App</title>
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
                <h1>Create New App</h1>
              <button class="secondary" onclick="location.href='../apps.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                 <?php if ($error_message): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>
                <div class="overflow-auto">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <label for="containerName">Container Name</label>
                        <input type="text" id="containerName" name="containerName" required maxlength="100" value="<?php echo isset($_POST['containerName']) ? htmlspecialchars($_POST['containerName']) : ''; ?>">
                        <label for="image">Image</label>
                        <input type="text" id="image" name="image" required maxlength="100" value="<?php echo isset($_POST['image']) ? htmlspecialchars($_POST['image']) : ''; ?>">
                        <label for="version">Version</label>
                        <input type="text" id="version" name="version" maxlength="50" value="<?php echo isset($_POST['version']) ? htmlspecialchars($_POST['version']) : ''; ?>">
                        <label for="url">URL</label>
                        <input type="text" id="url" name="url" required maxlength="100" value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>">
                        <label for="comment">Comment</label>
                        <input type="text" id="comment" name="comment" maxlength="255" value="<?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?>">
                        <button type="submit">Create</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
