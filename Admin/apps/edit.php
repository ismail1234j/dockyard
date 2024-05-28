<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" and isset($_POST['name'])) {
    $db = new PDO('sqlite:../db.sqlite');
    $id = $_POST['id'];
    $name = $_POST['name'];
    $image = $_POST['image'];
    $version = $_POST['version'];
    $link = $_POST['link'];
    $comments = $_POST['comments'];
    $stmt = $db->prepare('UPDATE apps SET Name = :Name, Image = :Image, Version = :Version, Url = :Url, Comment = :Comment WHERE ID = :ID');
    $stmt->bindParam(':Name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':Image', $image, PDO::PARAM_STR);
    $stmt->bindParam(':Version', $version, PDO::PARAM_STR);
    $stmt->bindParam(':Url', $link, PDO::PARAM_STR);
    $stmt->bindParam(':Comment', $comments, PDO::PARAM_STR);
    $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: ../apps.php');
}
session_start();
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
?>
<html>
<head>
    <title>Edit</title>
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
                        <button type="submit" class="btn">Edit</button>
                    </form>
                    <?php
                    if ($_SERVER["REQUEST_METHOD"] == "POST") {
                        $id = $_POST['id'];
                        $stmt = $db->prepare('SELECT * FROM apps WHERE ID = :ID');
                        $stmt->bindParam(':ID', $id, PDO::PARAM_INT);
                        $stmt->execute();
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <form method="post">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo $row['Name']; ?>" required />
                            <label for="image">Image</label>
                            <input type="text" id="image" name="image" value="<?php echo $row['Image']; ?>" required />
                            <label for="version">Version</label>
                            <input type="text" id="version" name="version" value="<?php echo $row['Version']; ?>" required />
                            <label for="link">Link</label>
                            <input type="text" id="link" name="link" value="<?php echo $row['Url']; ?>" required />
                            <label for="comments">Comments</label>
                            <input type="text" id="comments" name="comments" value="<?php echo $row['Comment']; ?>" required />
                            <input type="hidden" name="id" value="<?php echo $row['ID']; ?>" />
                            <button type="submit" class="btn">Save</button>
                        </form>
                    <?php } ?>
                </div>
            </section>
            <footer>
                <div class="container">
                    <hr />
                    <p>&copy; 2024</p>
                </div>
            </footer>
        </main>
    </div>
    </body>
<?php else : ?>
    <body>
    <div class="container" style="margin-top: 8%;">
        <h1>Unauthorized</h1>
        <hr />
        <p>You are not authorized to view this page.</p>
    </div>
    <footer>
        <div class="container">
            <hr />
            <p>&copy; 2024</p>
        </div>
    </footer>
    </body>
<?php endif; ?>
</html>


