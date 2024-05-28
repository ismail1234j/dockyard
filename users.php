<?php
// Check auth
$db = new PDO('sqlite:db.sqlite');
session_start();
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
    <title>Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"/>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
    />
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
        />
</head>
<?php if ($auth): ?>
    <body>
        <div class="container" style="margin-top: 6%">
            <header>
                <section>
                    <h1>User</h1>
                        <button class="secondary" onclick="location.href='index.php';">Back</button>
                        <button class="pico-background-jade-150" onclick="location.href='users/new.php';">Create</button>
                        <button class="pico-background-amber-300" onclick="location.href='users/change_password.php';">Change Password</button>
                </section>
            </header>
            <hr />
            <main class="">
                <section>
                    <div class="overflow-auto">
                        <table>
                            <thead>
                            <tr>
                                <th scope="col">Username</th>
                                <th scope="col">Delete</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $stmt = $db->prepare('SELECT * FROM users');
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>';
                                echo '<td>' . $row['username'] . '</td>';
                                // Only display the delete icon if the username is not the current user
                                if ($row['username'] !== $username) {
                                    echo '<td><i class="fa fa-trash" aria-hidden="true" onclick="deleteUser(\'' . $row['username'] . '\')"></i></td>'; // New table data cell for delete icon
                                } else {
                                    echo '<td></td>'; // Empty cell for current user
                                }
                                echo '</tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                        <script>
                          function deleteUser(username) {
                            // Send a POST request to delete_user.php with the username
                            fetch('delete_user.php', {
                              method: 'POST',
                              headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: 'username=' + encodeURIComponent(username),
                            })
                              .then(response => response.text())
                              .then(data => {
                                // Reload the page after the user is deleted
                                location.reload();
                              });
                          }
                        </script>
                </section>
            </main>
    </body>
<?php endif; ?>