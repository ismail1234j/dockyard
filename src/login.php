<?php
session_start();

include_once 'includes/db.php';
$db = get_db();

    if (!isset($_SESSION['username']) && !isset($_SESSION['authenticated'])){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username = $_POST["username"];
            $password = $_POST["password"];
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Check for rate limiting - max 5 failed attempts in 15 minutes
            try {
                $stmt = $db->prepare('SELECT COUNT(*) FROM failed_login_attempts WHERE Username = :username AND Success = 0 AND datetime(AttemptTime) > datetime("now", "-15 minutes")');
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->execute();
                $failedAttempts = $stmt->fetchColumn();
                
                if ($failedAttempts >= 5) {
                    $error_message = "Too many failed login attempts. Please try again in 15 minutes.";
                    // Log this attempt
                    $stmt = $db->prepare('INSERT INTO failed_login_attempts (Username, IPAddress, Success) VALUES (:username, :ip, 0)');
                    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
                    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt->execute();
                    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $row['password'])) {
                    // Regenerate session ID to prevent session fixation attacks
                    session_regenerate_id(true);
                    
                    // Check if force password reset is required
                    $forceReset = isset($row['force_password_reset']) && $row['force_password_reset'] == 1;
                    
                    if ($forceReset) {
                        // Create a temporary restricted session
                        $_SESSION['temp_user_id'] = $row['ID'];
                        $_SESSION['temp_username'] = $username;
                        $_SESSION['force_reset_session'] = true;
                        $_SESSION['force_reset_time'] = time();
                        
                        // Generate and store CSRF token
                        if (!isset($_SESSION['csrf_token'])) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        }
                        
                        header('Location: users/force_password_reset.php');
                        exit;
                    }
                    
                    $_SESSION['username'] = $username;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['isAdmin'] = (bool)$row['IsAdmin'];
                    $_SESSION['user_id'] = $row['ID'];
                    $_SESSION['LAST_ACTIVITY'] = time();
                    
                    // Store session in database for session management
                    $sessionId = session_id();
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    try {
                        $stmt = $db->prepare('INSERT INTO user_sessions (UserID, SessionID, IPAddress, UserAgent) VALUES (:user_id, :session_id, :ip, :user_agent)');
                        $stmt->bindParam(':user_id', $row['ID'], PDO::PARAM_INT);
                        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
                        $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
                        $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
                        $stmt->execute();
                    } catch (PDOException $e) {
                        // Log error but don't block login
                        error_log("Error storing session: " . $e->getMessage());
                    }
                    
                    // Log successful login
                    try {
                        $stmt = $db->prepare('INSERT INTO failed_login_attempts (Username, IPAddress, Success) VALUES (:username, :ip, 1)');
                        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                        $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
                        $stmt->execute();
                    } catch (PDOException $e) {
                        error_log("Error logging successful login: " . $e->getMessage());
                    }
                    
                    // Generate and store CSRF token
                    if (!isset($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    
                    header('Location: index.php');
                    exit; // Add exit after header redirect
                } else {
                    // Log failed login attempt
                    try {
                        $stmt = $db->prepare('INSERT INTO failed_login_attempts (Username, IPAddress, Success) VALUES (:username, :ip, 0)');
                        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                        $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
                        $stmt->execute();
                    } catch (PDOException $e) {
                        error_log("Error logging failed login: " . $e->getMessage());
                    }
                    $error_message = "Invalid username or password."; // Store error message
                }
            } else {
                // Log failed login attempt for non-existent user
                try {
                    $stmt = $db->prepare('INSERT INTO failed_login_attempts (Username, IPAddress, Success) VALUES (:username, :ip, 0)');
                    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
                    $stmt->execute();
                } catch (PDOException $e) {
                    error_log("Error logging failed login: " . $e->getMessage());
                }
                $error_message = "Invalid username or password."; // Store error message
            }
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error_message = "An error occurred. Please try again.";
            }
        }
    } elseif (isset($_SESSION['username']) && isset($_SESSION['authenticated'])) {
        // User is already logged in, redirect to index
        header('Location: index.php');
        exit;
    }
?>
<!DOCTYPE html>
<html data-theme="light">
    <head>
        <title>Login</title>
      <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"
      />
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <?php if (isset($db_error)): ?>
            <dialog open id='errorDialog'>
                <article>
                    <header>
                    <button aria-label='Close' rel='prev' id='closeErrorDialog'></button>
                    <p>
                        <strong>❌ Error</strong>
                    </p>
                    </header>
                    <p>
                    <strong>Database connection failed.</strong>
                </article>
            </dialog>
            <script>
              const errorDialog = document.getElementById('errorDialog');
              const closeButton = document.getElementById('closeErrorDialog');
              
              if (closeButton && errorDialog) {
                closeButton.addEventListener('click', () => {
                  errorDialog.close();
                });
              }
            </script>
        <?php endif; ?>

        <div class="container" style="margin-top: 8%;">
          <h1>Login</h1>
          <hr />
          <?php if (isset($error_message)): ?>
              <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
          <?php endif; ?>
          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
            <label>Username<input type="text" id="username" name="username" required /></label>
            <label>Password<input type="password" id="password" name="password" required /></label>
            <button type="submit" class="btn">Login</button>
          </form>
        </div>
    </body>
</html>