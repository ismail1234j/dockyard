<?php
// Database initialization script

// Determine if this is being run via CLI or web
$isWeb = isset($_SERVER['REQUEST_METHOD']);
$outputFormat = $isWeb ? 'html' : 'cli';

// Function for consistent output across CLI and web
function output($message, $format = null) {
    global $outputFormat;
    $format = $format ?? $outputFormat;
    
    if ($format === 'html') {
        echo htmlspecialchars($message) . "<br>\n";
    } else {
        echo $message . "\n";
    }
}

// Database path - the file should already exist with correct permissions
$dbPath = __DIR__ . '/data';
$dbFile = $dbPath . '/db.sqlite';

// Verify database file exists - should have been created by Docker
if (!file_exists($dbFile)) {
    output("Database file does not exist. It should have been created by Docker.", 'cli');
    output("Please check the Docker container setup.", 'cli');
    exit(1);
}


output("Connecting to database...", 'cli');
$db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create or modify apps table with enhanced fields for container management
    output("Creating apps table...", 'cli');
    $createAppsTableQuery = "
        CREATE TABLE IF NOT EXISTS apps (
            ID INTEGER PRIMARY KEY AUTOINCREMENT, 
            ContainerName TEXT NOT NULL UNIQUE,
            Image TEXT DEFAULT '',
            Version TEXT DEFAULT 'latest',
            Status TEXT DEFAULT 'unknown',
            Comment TEXT DEFAULT '',
            Port TEXT DEFAULT '',
            Url TEXT DEFAULT '',
            LastPingStatus INTEGER DEFAULT NULL,
            LastPingTime TEXT DEFAULT NULL
        )";

    // Create users table
    output("Creating users table...", 'cli');
    $createUsersTableQuery = "
        CREATE TABLE IF NOT EXISTS users (
            ID INTEGER PRIMARY KEY AUTOINCREMENT, 
            username TEXT NOT NULL UNIQUE, 
            password TEXT NOT NULL,
            email TEXT,
            IsAdmin BOOLEAN NOT NULL DEFAULT 0
        )";

    // Create new container permissions table to manage user access
    output("Creating container permissions table...", 'cli');
    $createContainerPermissionsQuery = "
        CREATE TABLE IF NOT EXISTS container_permissions (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            UserID INTEGER NOT NULL,
            ContainerID INTEGER NOT NULL,
            CanView BOOLEAN NOT NULL DEFAULT 1,
            CanStart BOOLEAN NOT NULL DEFAULT 0,
            CanStop BOOLEAN NOT NULL DEFAULT 0,
            FOREIGN KEY (UserID) REFERENCES users(ID) ON DELETE CASCADE,
            FOREIGN KEY (ContainerID) REFERENCES apps(ID) ON DELETE CASCADE,
            UNIQUE(UserID, ContainerID)
        )";

    // Execute the queries
    $db->exec($createAppsTableQuery);
    $db->exec($createUsersTableQuery);
    $db->exec($createContainerPermissionsQuery);

    // Check if default admin user exists, if not create it
    $adminCheckQuery = "SELECT COUNT(*) FROM users WHERE username = 'admin'";
    $adminExists = $db->query($adminCheckQuery)->fetchColumn();
    
    if ($adminExists == 0) {
        output("Creating default admin user (admin/pass)...", 'cli');
        $hashedPassword = password_hash('pass', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, IsAdmin) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, '', 1]);
        output("Default admin user created.");
    }
    
    output("Database schema setup completed successfully!");
    
    if ($isWeb) {
        // If running via web, add a link to go to the login page
        echo '<p><a href="login.php">Go to login page</a></p>';
    }