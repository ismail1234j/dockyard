<?php
session_start();
$db = new PDO('sqlite:db.sqlite');
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
if ($auth) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['username'])) {
            $usernameToDelete = $_POST['username'];
            $stmt = $db->prepare('DELETE FROM users WHERE username = :username');
            $stmt->bindParam(':username', $usernameToDelete, PDO::PARAM_STR);
            $stmt->execute();
            echo 'User deleted successfully';
        } else {
            echo 'No username provided';
        }
    } else {
        echo 'Invalid request method';
    }
}
?>
