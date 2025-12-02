<?php
// Remove duplicate session management and use centralized auth
require_once '../includes/auth.php';

$error_message = '';
$success_message = '';

// Change the users password
if ($auth) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Security validation failed. Please try again.";
        } else {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Password complexity and validation
            if (strlen($new_password) < 8) {
                $error_message = "Password must be at least 8 characters long";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "Passwords do not match";
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error_message = "Password must contain at least one uppercase letter";
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $error_message = "Password must contain at least one lowercase letter";
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $error_message = "Password must contain at least one number";
            } else {
                try {
                    $stmt = $db->prepare('UPDATE users SET password = :password WHERE username = :username');
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
                    $stmt->bindParam(':username', $_SESSION['username'], PDO::PARAM_STR);
                    $stmt->execute();
                    $success_message = "Password changed successfully. You will be redirected to login...";
                    // Redirect after 3 seconds
                    header("Refresh: 3; URL=../logout.php");
                } catch (PDOException $e) {
                    $error_message = "Database error. Please try again.";
                    // Log the actual error
                    error_log("Password change error: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Change Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
    />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<?php if ($auth) : ?>
    <body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Change Password</h1>
                <button class="secondary" onclick="location.href='../users.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <p class="password-requirements">
                        Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.
                    </p>
                    <button type="submit">Change Password</button>
                </form>
            </section>
        </main>
    </div>
    </body>
</html>
<?php else : ?>
    <body>
    <div class="container" style="margin-top: 6%">
        <header>
            <section>
                <h1>Change Password</h1>
                <button class="secondary" onclick="location.href='../users.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <p>Unauthorized</p>
            </section>
        </main>
    </div>
    </body>
</html>
<?php endif; ?>
