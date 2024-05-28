<?php
session_start();
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
$db = new PDO('sqlite:../db.sqlite');
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
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        $stmt = $db->prepare('DELETE FROM apps WHERE ID = :ID');
        $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
        $stmt->execute();
        // redirect back to apps.php
        header('Location: ../apps.php');
    }
}
?>
<html>
<head>
    <title>Delete</title>
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
                <h1>Apps</h1>
              <button class="secondary" onclick="location.href='../apps.php';">Back</button>
            </section>
        </header>
        <hr />
        <main>
            <section>
                <div class="overflow-auto">
                    <!-- drop down menu -->
                    <form method="post">
                        <label for="id">Select an app:</label>
                        <select id="id" name="id" required>
                            <?php
                            $stmt = $db->prepare('SELECT * FROM apps');
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . $row['ID'] . "'>" . $row['Name'] . "</option>";
                            }
                            ?>
                        </select>
                        <input class="pico-background-red-500" type="submit" value="Delete">
                    </form>
                </div>
            </section>
        </main>
        <footer>
            <hr />
            <section>
                <p>&copy; 2021 Apps</p>
            </section>
        </footer>
    </div>
    </body>
<?php endif; ?>
</html>
