<?php
session_start();

// Check if this is a valid force reset session
if (!isset($_SESSION['force_reset_session']) || $_SESSION['force_reset_session'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Check session timeout (5 minutes)
$sessionTimeout = 300; // 5 minutes
if (!isset($_SESSION['force_reset_time']) || (time() - $_SESSION['force_reset_time']) > $sessionTimeout) {
    // Session expired
    session_unset();
    session_destroy();
    header('Location: ../login.php?error=session_expired');
    exit;
}

// Update last activity
$_SESSION['force_reset_time'] = time();

// Database connection
try {
    $db = new PDO('sqlite:../data/db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die("Database connection failed. Please try again later.");
}

$error_message = '';
$success = false;

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Security validation failed. Please try again.";
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate passwords match
        if ($newPassword !== $confirmPassword) {
            $error_message = "Passwords do not match.";
        }
        // Validate password strength
        elseif (strlen($newPassword) < 8) {
            $error_message = "Password must be at least 8 characters long.";
        }
        elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $error_message = "Password must contain at least one uppercase letter.";
        }
        elseif (!preg_match('/[a-z]/', $newPassword)) {
            $error_message = "Password must contain at least one lowercase letter.";
        }
        elseif (!preg_match('/[0-9]/', $newPassword)) {
            $error_message = "Password must contain at least one number.";
        }
        elseif (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $error_message = "Password must contain at least one special character.";
        } else {
            try {
                // Get user info
                $userId = $_SESSION['temp_user_id'];
                $username = $_SESSION['temp_username'];
                
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password and clear force reset flag
                $stmt = $db->prepare('UPDATE users SET password = :password, force_password_reset = 0 WHERE ID = :id');
                $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
                $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                
                // Clear temporary session variables
                unset($_SESSION['force_reset_session']);
                unset($_SESSION['force_reset_time']);
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_username']);
                
                // Create a full authenticated session
                $stmt = $db->prepare('SELECT * FROM users WHERE ID = :id');
                $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['username'] = $user['username'];
                $_SESSION['authenticated'] = true;
                $_SESSION['isAdmin'] = (bool)$user['IsAdmin'];
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['LAST_ACTIVITY'] = time();
                
                // Redirect to index
                header('Location: ../index.php?message=password_reset_success');
                exit;
                
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error_message = "An error occurred while resetting your password. Please try again.";
            }
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Force Password Reset</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .password-requirements {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .password-requirements h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
        .session-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 8%;">
        <h1>Password Reset Required</h1>
        <hr />
        
        <div class="session-warning">
            <strong>⚠️ Security Notice:</strong> An administrator has required you to reset your password. 
            You must create a new password before accessing the system. This session will expire in 5 minutes.
        </div>
        
        <?php if ($error_message): ?>
            <div style="background-color: #f8d7da; color: #842029; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <div class="password-requirements">
            <h4>Password Requirements:</h4>
            <ul>
                <li>Minimum 8 characters long</li>
                <li>At least one uppercase letter (A-Z)</li>
                <li>At least one lowercase letter (a-z)</li>
                <li>At least one number (0-9)</li>
                <li>At least one special character (!@#$%^&*)</li>
            </ul>
        </div>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <label>
                New Password
                <input type="password" name="new_password" id="new_password" required autocomplete="new-password" />
            </label>
            
            <label>
                Confirm Password
                <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password" />
            </label>
            
            <button type="submit" class="btn">Reset Password</button>
        </form>
        
        <p style="margin-top: 1rem; text-align: center;">
            <a href="../logout.php">Cancel and Logout</a>
        </p>
    </div>
    
    <script>
        // Auto-logout after 5 minutes
        let timeRemaining = 300; // 5 minutes in seconds
        const warningTime = 60; // Show warning at 1 minute
        
        const countdownInterval = setInterval(function() {
            timeRemaining--;
            
            if (timeRemaining === warningTime) {
                // Show warning at 1 minute remaining
                const warning = document.createElement('div');
                warning.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #ffc107; color: #000; padding: 15px; border-radius: 8px; z-index: 10000;';
                warning.textContent = 'Session will expire in 1 minute. Please complete password reset.';
                document.body.appendChild(warning);
            }
            
            if (timeRemaining <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '../logout.php?reason=session_expired';
            }
        }, 1000);
        
        // Show real-time password validation
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };
            
            // You could add visual feedback here
        });
        
        // Prevent form submission if passwords don't match
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
