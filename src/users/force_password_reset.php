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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>

    <style>
        .password-requirements {
            background-color: var(--pico-card-background-color);
            border: 1px solid var(--pico-muted-border-color);
            border-radius: var(--pico-border-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .password-requirements h4 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
            color: var(--pico-heading-color);
        }
        
        .password-requirements ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        
        .password-requirements li {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--pico-muted-color);
            transition: color 0.3s ease;
        }
        
        .password-requirements li::before {
            content: '\f00d'; /* Times */
            font-family: 'FontAwesome';
            color: #d93526;
            width: 1rem;
            text-align: center;
        }
        
        .password-requirements li.valid {
            color: var(--pico-color);
        }
        
        .password-requirements li.valid::before {
            content: '\f00c'; /* Check */
            color: #388e3c;
        }

        .session-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 2rem;
        }

        .error-banner {
            background-color: #f8d7da;
            color: #842029;
            padding: 1rem;
            border-radius: var(--pico-border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid #f5c2c7;
        }

        article {
            max-width: 600px;
            margin: 0 auto;
            box-shadow: var(--pico-card-sectioning-background-color);
        }

        .countdown-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ffc107;
            color: #000;
            padding: 15px 25px;
            border-radius: 8px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: bold;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <hgroup>
                    <h1>Password Reset</h1>
                </hgroup>
            </header>

            <div class="session-warning">
                <strong><i class="fa fa-exclamation-triangle"></i> Security Notice:</strong> 
                An administrator has required a password change. You have <strong><span id="timer-display">5:00</span></strong> to complete this action before the session expires.
            </div>
            
            <?php if (isset($error_message) && $error_message): ?>
                <div class="error-banner">
                    <i class="fa fa-times-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <section class="password-requirements">
                <h4>Required Strength:</h4>
                <ul>
                    <li id="req-length">8+ characters</li>
                    <li id="req-uppercase">Uppercase (A-Z)</li>
                    <li id="req-lowercase">Lowercase (a-z)</li>
                    <li id="req-number">Number (0-9)</li>
                    <li id="req-special">Special (!@#$%^&*)</li>
                </ul>
            </section>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <label for="new_password">
                    New Password
                    <input type="password" name="new_password" id="new_password" placeholder="New password" required autocomplete="new-password" />
                </label>
                
                <label for="confirm_password">
                    Confirm New Password
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat password" required autocomplete="new-password" />
                </label>
                
                <button type="submit" class="contrast">
                    <i class="fa fa-shield"></i> Update Password & Access System
                </button>
            </form>
            
            <footer>
                <div style="text-align: center;">
                    <a href="../logout.php" class="secondary">Cancel and Logout</a>
                </div>
            </footer>
        </article>
    </div>
    
    <script>
        // Auto-logout countdown logic
        let timeRemaining = 300; 
        const warningTime = 60;
        const timerDisplay = document.getElementById('timer-display');
        
        const countdownInterval = setInterval(function() {
            timeRemaining--;
            
            // Update inline timer
            const mins = Math.floor(timeRemaining / 60);
            const secs = timeRemaining % 60;
            timerDisplay.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
            
            if (timeRemaining === warningTime) {
                const warning = document.createElement('div');
                warning.className = 'countdown-toast';
                warning.innerHTML = '<i class="fa fa-clock-o"></i> 1 minute remaining! Save your changes.';
                document.body.appendChild(warning);
            }
            
            if (timeRemaining <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '../logout.php?reason=session_expired';
            }
        }, 1000);
        
        // Real-time validation
        const passInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('confirm_password');
        
        passInput.addEventListener('input', function(e) {
            const val = e.target.value;
            const reqs = {
                'req-length': val.length >= 8,
                'req-uppercase': /[A-Z]/.test(val),
                'req-lowercase': /[a-z]/.test(val),
                'req-number': /[0-9]/.test(val),
                'req-special': /[^A-Za-z0-9]/.test(val)
            };
            
            for (const [id, isValid] of Object.entries(reqs)) {
                document.getElementById(id).classList.toggle('valid', isValid);
            }
        });
        
        // Form Validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (passInput.value !== confirmInput.value) {
                e.preventDefault();
                confirmInput.setAttribute('aria-invalid', 'true');
                // Use custom message instead of browser alert
                const errorBox = document.createElement('div');
                errorBox.className = 'error-banner';
                errorBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> Passwords do not match.';
                this.prepend(errorBox);
                setTimeout(() => errorBox.remove(), 3000);
            }
        });
    </script>
</body>
</html>