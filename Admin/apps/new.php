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
        $name = $_POST['name'];
        $image = $_POST['image'];
        $version = $_POST['version'];
        $status = 'not done';
        $link = $_POST['link'];
        $comments = $_POST['comments'] . " ";
        $stmt = $db->prepare('INSERT INTO apps (Name, Image, Version, Status, Comment, Url) VALUES (:Name, :Image, :Version, :Status, :Comment, :Url)');
        $stmt->bindParam(':Name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':Image', $image, PDO::PARAM_STR);
        $stmt->bindParam(':Version', $version, PDO::PARAM_STR);
        $stmt->bindParam(':Status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':Comment', $comments, PDO::PARAM_STR);
        $stmt->bindParam(':Url', $link, PDO::PARAM_STR);
        $stmt->execute();
        header('Location: ../apps.php');
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
                <h1>Apps</h1>

              <button class="secondary" onclick="location.href='../apps.php';">Back</button>

            </section>
        </header>
        <hr />
        <main>
            <section>
                <div class="overflow-auto">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required>
                        <label for="image">Image</label>
                        <input type="text" id="image" name="image" required>
                        <label for="version">Version</label>
                        <input type="text" id="version" name="version" required>
                        <label for="link">link</label>
                        <input type="text" id="link" name="link" required>
                        <label for="comments">Comments</label>
                        <input type="text" id="comments" name="comments" >
                        <button type="submit">Create</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
    </body>
<?php endif; ?>
</html>
