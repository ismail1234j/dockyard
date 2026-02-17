<?php
require_once '../includes/auth.php'; 
require_once '../includes/functions.php';
require_once '../includes/db.php';
$db = get_db();
require_admin();

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
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email address format.";
        } elseif (!empty($new_password)) {
            // Validate new password if provided
            if (strlen($new_password) < 8) {
                $error_message = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error_message = "Password must contain at least one uppercase letter.";
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $error_message = "Password must contain at least one lowercase letter.";
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $error_message = "Password must contain at least one number.";
            } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
                $error_message = "Password must contain at least one special character.";
            }
        }
        
        if (empty($error_message)) {
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <style>
        article {
            max-width: 700px;
            margin: 0 auto;
            box-shadow: var(--pico-card-sectioning-background-color);
        }
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--pico-spacing);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--pico-border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .form-footer {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <div class="header-nav">
                    <hgroup style="margin: 0;">
                        <h1>Edit User</h1>
                        <p><?php echo htmlspecialchars($user_data['username']); ?></p>
                    </hgroup>
                    <button class="secondary outline" onclick="location.href='../users.php';" style="width: auto;">
                        <i class="fa fa-arrow-left"></i> Back
                    </button>
                </div>
            </header>

            <main>
                <!-- PHP Message Handling -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fa fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="grid">
                        <label for="username">
                            Username
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                        </label>

                        <label for="email">
                            Email Address
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="user@example.com">
                        </label>
                    </div>

                    <label for="password">
                        New Password
                        <input type="password" id="password" name="password" placeholder="••••••••" aria-describedby="pw-helper">
                        <small id="pw-helper">Leave blank to keep the current password.</small>
                    </label>

                    <fieldset>
                        <label for="isAdmin">
                            <input type="checkbox" id="isAdmin" name="isAdmin" role="switch" value="1" <?php echo $user_data['IsAdmin'] ? 'checked' : ''; ?>>
                            Grant Administrator Privileges
                        </label>
                    </fieldset>

                    <div class="form-footer">
                        <button type="submit" class="contrast">
                            <i class="fa fa-save"></i> Save User Changes
                        </button>
                    </div>
                </form>
            </main>
        </article>
    </div>
</body>
</html>
