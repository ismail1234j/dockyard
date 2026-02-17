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

// CRITICAL: Enable foreign key constraints in SQLite
$db->exec('PRAGMA foreign_keys = ON');
output("Foreign key constraints enabled.", 'cli');

    // Create or modify apps table with enhanced fields for container management
    output("Creating apps table...", 'cli');
    $createAppsTableQuery = "
        CREATE TABLE IF NOT EXISTS apps (
            ID INTEGER PRIMARY KEY AUTOINCREMENT, 
            ContainerName TEXT NOT NULL UNIQUE,
            ContainerID TEXT UNIQUE,
            Image TEXT DEFAULT '',
            Version TEXT DEFAULT 'latest',
            Status TEXT DEFAULT 'unknown',
            Comment TEXT DEFAULT '',
            Port TEXT DEFAULT '',
            Url TEXT DEFAULT '',
            LastPingStatus INTEGER DEFAULT NULL,
            LastPingTime TEXT DEFAULT NULL,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
        )";

    // Create users table
    output("Creating users table...", 'cli');
    $createUsersTableQuery = "
        CREATE TABLE IF NOT EXISTS users (
            ID INTEGER PRIMARY KEY AUTOINCREMENT, 
            username TEXT NOT NULL UNIQUE, 
            password TEXT NOT NULL,
            email TEXT,
            IsAdmin BOOLEAN NOT NULL DEFAULT 0,
            force_password_reset BOOLEAN NOT NULL DEFAULT 0
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
    
    // Create admin actions log table
    output("Creating admin_actions_log table...", 'cli');
    $createAdminActionsLogQuery = "
        CREATE TABLE IF NOT EXISTS admin_actions_log (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            AdminUserID INTEGER NOT NULL,
            TargetUserID INTEGER,
            Action TEXT NOT NULL,
            Details TEXT,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (AdminUserID) REFERENCES users(ID) ON DELETE CASCADE,
            FOREIGN KEY (TargetUserID) REFERENCES users(ID) ON DELETE SET NULL
        )";
    $db->exec($createAdminActionsLogQuery);
    
    // Create user sessions table for tracking active sessions
    output("Creating user_sessions table...", 'cli');
    $createUserSessionsTableQuery = "
        CREATE TABLE IF NOT EXISTS user_sessions (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            UserID INTEGER NOT NULL,
            SessionID TEXT NOT NULL UNIQUE,
            IPAddress TEXT,
            UserAgent TEXT,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            LastActivity TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (UserID) REFERENCES users(ID) ON DELETE CASCADE
        )";
    $db->exec($createUserSessionsTableQuery);
    
    // Create failed login attempts table for rate limiting
    output("Creating failed_login_attempts table...", 'cli');
    $createFailedLoginAttemptsQuery = "
        CREATE TABLE IF NOT EXISTS failed_login_attempts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Username TEXT NOT NULL,
            IPAddress TEXT NOT NULL,
            AttemptTime TEXT DEFAULT CURRENT_TIMESTAMP,
            Success BOOLEAN NOT NULL DEFAULT 0
        )";
    $db->exec($createFailedLoginAttemptsQuery);
    
    // Create indices for better query performance
    output("Creating database indices...", 'cli');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_container_permissions_user ON container_permissions(UserID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_container_permissions_container ON container_permissions(ContainerID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_apps_container_id ON apps(ContainerID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_apps_status ON apps(Status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_admin_log_admin ON admin_actions_log(AdminUserID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_actions_log(TargetUserID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(UserID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_user_sessions_session ON user_sessions(SessionID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_failed_login_username ON failed_login_attempts(Username)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_failed_login_ip ON failed_login_attempts(IPAddress)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_failed_login_time ON failed_login_attempts(AttemptTime)');
    output("Database indices created.", 'cli');

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