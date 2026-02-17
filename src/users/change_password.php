<?php
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <style>
        article {
            max-width: 600px;
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

        /* Dynamic Requirements Styling */
        .password-requirements-box {
            background-color: var(--pico-card-background-color);
            border: 1px solid var(--pico-muted-border-color);
            border-radius: var(--pico-border-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .password-requirements-box h4 {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            color: var(--pico-heading-color);
        }
        
        .password-requirements-box ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.5rem;
        }
        
        .password-requirements-box li {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--pico-muted-color);
            transition: all 0.2s ease;
        }
        
        .password-requirements-box li::before {
            content: '\f00d'; /* Times */
            font-family: 'FontAwesome';
            color: #d93526;
            width: 1rem;
            text-align: center;
        }
        
        .password-requirements-box li.valid {
            color: var(--pico-color);
        }
        
        .password-requirements-box li.valid::before {
            content: '\f00c'; /* Check */
            color: #388e3c;
        }

        .form-footer {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <div class="header-nav">
                    <hgroup style="margin: 0;">
                        <h1>Change password</h1>
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

                <div class="password-requirements-box">
                    <h4>Required Strength:</h4>
                    <ul>
                        <li id="req-length">8+ characters</li>
                        <li id="req-uppercase">Uppercase (A-Z)</li>
                        <li id="req-lowercase">Lowercase (a-z)</li>
                        <li id="req-number">Number (0-9)</li>
                        <li id="req-special">Special (!@#$%^&*)</li>
                    </ul>
                </div>

                <form method="post" id="passwordForm">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <label for="new_password">
                        New Password
                        <input type="password" id="new_password" name="new_password" placeholder="••••••••" required autocomplete="new-password">
                    </label>

                    <label for="confirm_password">
                        Confirm New Password
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required autocomplete="new-password">
                    </label>

                    <div class="form-footer">
                        <button type="submit" class="contrast" id="submitBtn">
                            <i class="fa fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </main>
        </article>
    </div>

    <script>
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const form = document.getElementById('passwordForm');

        const requirements = {
            length: (val) => val.length >= 8,
            uppercase: (val) => /[A-Z]/.test(val),
            lowercase: (val) => /[a-z]/.test(val),
            number: (val) => /[0-9]/.test(val),
            special: (val) => /[!@#$%^&*(),.?":{}|<>]/.test(val)
        };

        const updateRequirements = () => {
            const val = newPassword.value;
            
            document.getElementById('req-length').classList.toggle('valid', requirements.length(val));
            document.getElementById('req-uppercase').classList.toggle('valid', requirements.uppercase(val));
            document.getElementById('req-lowercase').classList.toggle('valid', requirements.lowercase(val));
            document.getElementById('req-number').classList.toggle('valid', requirements.number(val));
            document.getElementById('req-special').classList.toggle('valid', requirements.special(val));
        };

        newPassword.addEventListener('input', updateRequirements);

        form.addEventListener('submit', function(e) {
            const val = newPassword.value;
            const isMatch = val === confirmPassword.value;
            const allValid = Object.values(requirements).every(fn => fn(val));

            if (!allValid) {
                e.preventDefault();
                alert('Please ensure all password requirements are met.');
            } else if (!isMatch) {
                e.preventDefault();
                confirmPassword.setAttribute('aria-invalid', 'true');
                alert('Passwords do not match.');
            }
        });
    </script>
</body>
</html>