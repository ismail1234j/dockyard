<?php
require_once '../includes/auth.php'; // Use centralized auth

// Add new user
if ($auth) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $password = $_POST['password']; // Get password from form
        $email = $_POST['email']; // Get email from form
        $isAdmin = isset($_POST['isAdmin']) ? 1 : 0; // Check if isAdmin checkbox is checked

        // Validate inputs (basic example)
        if (empty($username) || empty($password)) {
            $error_message = "Username and password are required.";
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
?>
<html data-theme="light">
<head>
    <title>New User</title>
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
                <h1>New User</h1>
                <button class="secondary" onclick="location.href='../users.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <?php if (!empty($error_message)): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>
                <div class="overflow-auto">
                    <form method="post">
                        <!-- Add CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                        <label for="password">Password</label> 
                        <input type="password" id="password" name="password" required> <!-- Added password field -->
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"> <!-- Added email field -->
                        <label for="isAdmin" style="padding-bottom: 10px;">
                            <input type="checkbox" id="isAdmin" name="isAdmin" value="1">
                            Is Admin?
                        </label> <!-- Added IsAdmin checkbox -->
                        <button type="submit">Create</button>
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
