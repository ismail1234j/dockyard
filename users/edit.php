<?php
require_once '../includes/auth.php'; // Use centralized auth
require_once '../includes/functions.php';
require_admin(); // Ensure only admins can edit users

$error_message = '';
$success_message = '';
$user_data = null;

// Get user ID from query parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Fetch user data
try {
    $stmt = $db->prepare('SELECT ID, username, email, IsAdmin FROM users WHERE ID = :id');
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        header('Location: ../users.php');
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Database error. Please try again.";
    error_log("User fetch error: " . $e->getMessage());
}

// Update user
if ($auth && $_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Security validation failed. Please try again.";
    } else {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $isAdmin = isset($_POST['isAdmin']) ? 1 : 0;
        $new_password = $_POST['password'] ?? '';

        // Validate inputs
        if (empty($username)) {
            $error_message = "Username is required.";
        } else {
            try {
                // Check if username already exists for a different user
                $stmtCheck = $db->prepare('SELECT ID FROM users WHERE username = :username AND ID != :id');
                $stmtCheck->bindParam(':username', $username, PDO::PARAM_STR);
                $stmtCheck->bindParam(':id', $user_id, PDO::PARAM_INT);
                $stmtCheck->execute();
                if ($stmtCheck->fetch()) {
                    $error_message = "Username already exists.";
                } else {
                    // Prevent removing admin status from the currently logged-in user
                    if ($user_data['username'] === $_SESSION['username'] && $isAdmin == 0) {
                        $error_message = "You cannot remove your own admin privileges.";
                    } else {
                        // Update user
                        if (!empty($new_password)) {
                            // Update with new password
                            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare('UPDATE users SET username = :username, password = :password, email = :email, IsAdmin = :isAdmin WHERE ID = :id');
                            $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
                        } else {
                            // Update without changing password
                            $stmt = $db->prepare('UPDATE users SET username = :username, email = :email, IsAdmin = :isAdmin WHERE ID = :id');
                        }
                        
                        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                        $stmt->bindParam(':isAdmin', $isAdmin, PDO::PARAM_INT);
                        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();

                        $success_message = "User updated successfully.";
                        // Refresh user data
                        $user_data['username'] = $username;
                        $user_data['email'] = $email;
                        $user_data['IsAdmin'] = $isAdmin;
                    }
                }
            } catch (PDOException $e) {
                error_log("Database Error: " . $e->getMessage());
                $error_message = "Database error during user update. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
    />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<?php if ($auth && $user_data) : ?>
    <body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Edit User: <?php echo htmlspecialchars($user_data['username']); ?></h1>
                <button class="secondary" onclick="location.href='../users.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <?php if (!empty($error_message)): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
                <?php endif; ?>
                <div class="overflow-auto">
                    <form method="post">
                        <!-- Add CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
                        <label for="isAdmin" style="padding-bottom: 10px;">
                            <input type="checkbox" id="isAdmin" name="isAdmin" value="1" <?php echo $user_data['IsAdmin'] ? 'checked' : ''; ?>>
                            Is Admin?
                        </label>
                        <button type="submit">Update User</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
    </body>
<?php else : ?>
    <body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Unauthorized</h1>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <div class="overflow-auto">
                    <p>You are not authorized to view this page.</p>
                </div>
            </section>
        </main>
    </div>
    </body>
<?php endif; ?>
</html>
