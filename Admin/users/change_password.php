<?php
session_start();
$db = new PDO('sqlite:../db.sqlite');
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
$username = $_SESSION['username'];
$password = $_SESSION['password'];
if (isset($_SESSION['username']) and isset($_SESSION['password'])) {
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (password_verify($password, $row['password'])) {
            $auth = true;
        } else {
            $auth = false;
        }
    } else {
        $auth = false;
    }
} else {
    $auth = false;
}
// Change the users password
if ($auth) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_password = $_POST['new_password'];
        $stmt = $db->prepare('UPDATE users SET password = :password WHERE username = :username');
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        // redirect back to users.php
        header('Location: ../logout.php');
    }
}
?>
<html>
<head>
    <title>Change Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"/>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
    />
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
                <form method="post">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
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
