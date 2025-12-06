<?php
// Database migration script for new features
// Run this once to add new columns and tables

$dbPath = __DIR__ . '/data';
$dbFile = $dbPath . '/db.sqlite';

if (!file_exists($dbFile)) {
    die("Database file does not exist. Please run setup.php first.\n");
}

echo "Starting database migration...\n";

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
    
    // Check and add force_password_reset column to users table
    echo "Checking users table structure...\n";
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasForcePasswordReset = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'force_password_reset') {
            $hasForcePasswordReset = true;
            break;
        }
    }
    
    if (!$hasForcePasswordReset) {
        echo "Adding force_password_reset column to users table...\n";
        $db->exec('ALTER TABLE users ADD COLUMN force_password_reset BOOLEAN NOT NULL DEFAULT 0');
        echo "✓ force_password_reset column added\n";
    } else {
        echo "✓ force_password_reset column already exists\n";
    }
    
    // Create notifications table
    echo "Creating notifications table...\n";
    $db->exec("
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
        )
    ");
    echo "✓ Notifications table created\n";
    
    // Create admin actions log table
    echo "Creating admin_actions_log table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_actions_log (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            AdminUserID INTEGER NOT NULL,
            TargetUserID INTEGER,
            Action TEXT NOT NULL,
            Details TEXT,
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (AdminUserID) REFERENCES users(ID) ON DELETE CASCADE,
            FOREIGN KEY (TargetUserID) REFERENCES users(ID) ON DELETE SET NULL
        )
    ");
    echo "✓ Admin actions log table created\n";
    
    // Create indices for performance
    echo "Creating database indices...\n";
    $db->exec('CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(UserID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(IsRead)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_admin_log_admin ON admin_actions_log(AdminUserID)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_actions_log(TargetUserID)');
    echo "✓ Database indices created\n";
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
