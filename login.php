<?php
session_start();

// Database connection and authentication logic
try {
    $db = new PDO('sqlite:data/db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Ensure PDO throws exceptions

    if (!isset($_SESSION['username']) && !isset($_SESSION['authenticated'])){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username = $_POST["username"];
            $password = $_POST["password"];
            $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $row['password'])) {
                    $_SESSION['username'] = $username;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['isAdmin'] = (bool)$row['IsAdmin'];
                    $_SESSION['user_id'] = $row['ID'];
                    $_SESSION['LAST_ACTIVITY'] = time();
                    
                    // Generate and store CSRF token
                    if (!isset($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    
                    header('Location: index.php');
                    exit; // Add exit after header redirect
                } else {
                    $error_message = "Invalid username or password."; // Store error message
                }
            } else {
                $error_message = "Invalid username or password."; // Store error message
            }
        }
    } elseif (isset($_SESSION['username']) && isset($_SESSION['authenticated'])) {
        // User is already logged in, redirect to index
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $db_error = true; // Set flag for database error
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