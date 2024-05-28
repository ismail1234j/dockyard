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
// Add new user
if ($auth) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $password = " ";
        $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
        $stmt->execute();
        // redirect back to users.php
        header('Location: ../users.php');
    }
}
?>
<html>
<head>
    <title>New</title>
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
                <h1>New User</h1>
                <button class="secondary" onclick="location.href='../users.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <div class="overflow-auto">
                    <form method="post">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
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
