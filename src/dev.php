<?php
// Database initialization script for web

// Function for HTML output
function output(string $message): void {
    echo htmlspecialchars($message) . "<br>\n";
}

// Database directory and file
$dbPath = __DIR__ . '/data';
$dbFile = $dbPath . '/db.sqlite';

// Ensure the database directory exists
if (!is_dir($dbPath)) {
    if (!mkdir($dbPath, 0755, true)) {
        output("Failed to create database directory: $dbPath");
        exit;
    }
    output("Created database directory: $dbPath");
}

// Ensure the database file exists
if (!file_exists($dbFile)) {
    if (!touch($dbFile)) {
        output("Failed to create database file: $dbFile");
        exit;
    }
    output("Created database file: $dbFile");
}

// Connect to SQLite
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
    output("Connected to database and enabled foreign keys.");
} catch (PDOException $e) {
    output("Database connection failed: " . $e->getMessage());
    exit;
}

// --- Table creation queries ---
$queries = [
    "Creating apps table..." => "
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
        )",
    "Creating users table..." => "
        CREATE TABLE IF NOT EXISTS users (
            ID INTEGER PRIMARY KEY AUTOINCREMENT, 
            username TEXT NOT NULL UNIQUE, 
            password TEXT NOT NULL,
            email TEXT,
            IsAdmin BOOLEAN NOT NULL DEFAULT 0,
            force_password_reset BOOLEAN NOT NULL DEFAULT 0
        )",
    "Creating container_permissions table..." => "
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
        )",
    "Creating notifications table..." => "
        CREATE TABLE IF NOT EXISTS notifications (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            UserID INTEGER,
            ContainerID INTEGER,
            Type TEXT NOT NULL,
            Message TEXT NOT NULL,
            IsRead BOOLEAN NOT NULL DEFAULT 0,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (UserID) REFERENCES users(ID) ON DELETE CASCADE,
            FOREIGN KEY (ContainerID) REFERENCES apps(ID) ON DELETE CASCADE
        )",
    "Creating admin_actions_log table..." => "
        CREATE TABLE IF NOT EXISTS admin_actions_log (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            AdminUserID INTEGER NOT NULL,
            TargetUserID INTEGER,
            Action TEXT NOT NULL,
            Details TEXT,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (AdminUserID) REFERENCES users(ID) ON DELETE CASCADE,
            FOREIGN KEY (TargetUserID) REFERENCES users(ID) ON DELETE SET NULL
        )",
    "Creating user_sessions table..." => "
        CREATE TABLE IF NOT EXISTS user_sessions (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            UserID INTEGER NOT NULL,
            SessionID TEXT NOT NULL UNIQUE,
            IPAddress TEXT,
            UserAgent TEXT,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            LastActivity TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (UserID) REFERENCES users(ID) ON DELETE CASCADE
        )",
    "Creating failed_login_attempts table..." => "
        CREATE TABLE IF NOT EXISTS failed_login_attempts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Username TEXT NOT NULL,
            IPAddress TEXT NOT NULL,
            AttemptTime TEXT DEFAULT CURRENT_TIMESTAMP,
            Success BOOLEAN NOT NULL DEFAULT 0
        )"
];

// Execute table creation queries
foreach ($queries as $message => $query) {
    output($message);
    $db->exec($query);
}

// Create indices
$indices = [
    'CREATE INDEX IF NOT EXISTS idx_container_permissions_user ON container_permissions(UserID)',
    'CREATE INDEX IF NOT EXISTS idx_container_permissions_container ON container_permissions(ContainerID)',
    'CREATE INDEX IF NOT EXISTS idx_apps_container_id ON apps(ContainerID)',
    'CREATE INDEX IF NOT EXISTS idx_apps_status ON apps(Status)',
    'CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(UserID)',
    'CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(IsRead)',
    'CREATE INDEX IF NOT EXISTS idx_admin_log_admin ON admin_actions_log(AdminUserID)',
    'CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_actions_log(TargetUserID)',
    'CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(UserID)',
    'CREATE INDEX IF NOT EXISTS idx_user_sessions_session ON user_sessions(SessionID)',
    'CREATE INDEX IF NOT EXISTS idx_failed_login_username ON failed_login_attempts(Username)',
    'CREATE INDEX IF NOT EXISTS idx_failed_login_ip ON failed_login_attempts(IPAddress)',
    'CREATE INDEX IF NOT EXISTS idx_failed_login_time ON failed_login_attempts(AttemptTime)'
];

output("Creating database indices...");
foreach ($indices as $idx) {
    $db->exec($idx);
}
output("Database indices created.");

// Create default admin user if missing
$adminExists = $db->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
if ($adminExists == 0) {
    output("Creating default admin user (admin/pass)...");
    $hashedPassword = password_hash('pass', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, email, IsAdmin) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $hashedPassword, '', 1]);
    output("Default admin user created.");
}

output("Database setup completed successfully!");
echo '<p><a href="login.php">Go to login page</a></p>';
