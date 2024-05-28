<?php
session_start();
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
$db = new PDO('sqlite:db.sqlite');
if (!isset($_SESSION['username']) or !isset($_SESSION['password'])) {
  header('Location: login.php');
}

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
?>
<html>
    <head>
        <title>Home</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"/>
    </head>
    <?php if ($auth) : ?>
        <body>
<div class="container" style="margin-top: 6%">
<header>
<section>
<h1>You are logged in: <?php echo $username; ?></h1>
<a href="logout.php">Log Out</a>
</section>
</header>
<hr />
<main>
<section>
<div class="grid"> 
<div role="button" tabindex="0" class="secondary" onclick="location.href='apps.php';">Apps</div>
<div role="button" tabindex="0" class="secondary" onclick="location.href='users.php';">Users</div>
<div role="button" tabindex="0" class="secondary" onclick="location.href='status.php';">Status</div>
<div role="button" tabindex="0" class="secondary" onclick="location.href='settings.php';">Settings</div>
</div>
</section> 
</main>
<footer>

</footer>
</div>
</body>
    <?php endif; ?>
</html>
