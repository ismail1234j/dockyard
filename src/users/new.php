<?php
require_once '../includes/auth.php'; // Use centralized auth
require_once '../includes/functions.php';
require_admin(); // Ensure only admins can create users

// Add new user
if ($auth) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {        
        // Validate CSRF token
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $error_message = "Security validation failed. Please try again.";
            } else {
            $username = $_POST['username'];
            $password = $_POST['password']; // Get password from form
            $email = $_POST['email']; // Get email from form
            $isAdmin = isset($_POST['isAdmin']) ? 1 : 0; // Check if isAdmin checkbox is checked

            // Validate inputs (basic example)
            if (empty($username) || empty($password)) {
                $error_message = "Username and password are required.";
            } elseif (strlen($password) < 8) {
                $error_message = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $error_message = "Password must contain at least one uppercase letter.";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $error_message = "Password must contain at least one lowercase letter.";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $error_message = "Password must contain at least one number.";
            } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $error_message = "Password must contain at least one special character.";
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Invalid email address format.";
            } else {
                try {
                    // Check if username already exists
                    $stmtCheck = $db->prepare('SELECT ID FROM users WHERE username = :username');
                    $stmtCheck->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmtCheck->execute();
                    if ($stmtCheck->fetch()) {
                        $error_message = "Username already exists.";
                    } else {
                        // Hash the password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);

                        // Prepare the insert statement with all columns
                        $stmt = $db->prepare('INSERT INTO users (username, password, email, IsAdmin) VALUES (:username, :password, :email, :isAdmin)');
                        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                        $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
                        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                        $stmt->bindParam(':isAdmin', $isAdmin, PDO::PARAM_INT);
                        $stmt->execute();

                        // Redirect back to users.php on success
                        header('Location: ../users.php');
                        exit; // Stop script execution after redirect
                    }
                } catch (PDOException $e) {
                    // Log error: error_log("Database Error: " . $e->getMessage());
                    $error_message = "Database error during user creation. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>New User</title>
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

        .admin-toggle {
            background: var(--pico-card-sectioning-background-color);
            padding: 1rem;
            border-radius: var(--pico-border-radius);
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
            <article>
                <header>
                    <div class="header-nav">
                        <hgroup style="margin: 0;">
                            <h1>New User</h1>
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

                    <form method="post" id="userForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <label for="username">
                            Username
                            <input type="text" id="username" name="username" placeholder="jdoe" required>
                        </label>

                        <label for="password">
                            Initial Password
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </label>

                        <label for="email">
                            Email Address
                            <input type="email" id="email" name="email" placeholder="user@example.com">
                        </label>

                        <div class="admin-toggle">
                            <label for="isAdmin" style="margin: 0;">
                                <input type="checkbox" id="isAdmin" name="isAdmin" value="1">
                                Assign Administrator Privileges
                            </label>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="contrast">
                                <i class="fa fa-user-plus"></i> Create User
                            </button>
                        </div>
                    </form>
                </main>
            </article>

    <script>
        const passwordInput = document.getElementById('password');
        const form = document.getElementById('userForm');

        const requirements = {
            length: (val) => val.length >= 8,
            uppercase: (val) => /[A-Z]/.test(val),
            lowercase: (val) => /[a-z]/.test(val),
            number: (val) => /[0-9]/.test(val),
            special: (val) => /[!@#$%^&*(),.?":{}|<>]/.test(val)
        };

        const updateRequirements = () => {
            const val = passwordInput.value;
            document.getElementById('req-length').classList.toggle('valid', requirements.length(val));
            document.getElementById('req-uppercase').classList.toggle('valid', requirements.uppercase(val));
            document.getElementById('req-lowercase').classList.toggle('valid', requirements.lowercase(val));
            document.getElementById('req-number').classList.toggle('valid', requirements.number(val));
            document.getElementById('req-special').classList.toggle('valid', requirements.special(val));
        };

        passwordInput.addEventListener('input', updateRequirements);

        form.addEventListener('submit', function(e) {
            const val = passwordInput.value;
            const allValid = Object.values(requirements).every(fn => fn(val));

            if (!allValid) {
                e.preventDefault();
                alert('Please ensure the password meets all required security criteria.');
            }
        });
    </script>
</body>
</html>