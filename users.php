<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_admin(); // This function ensures only admins can access this page
// The auth.php include already handles authentication and provides $db connection
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
    />
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
        />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                                <th scope="col">Email</th>
                                <th scope="col">Admin</th>
                                <th scope="col">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $stmt = $db->prepare('SELECT * FROM users ORDER BY username');
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                                echo '<td>' . ($row['IsAdmin'] ? 'Yes' : 'No') . '</td>';
                                // Only display the delete icon if the username is not the current user
                                if ($row['username'] !== $username) {
                                    echo '<td>';
                                    echo '<a href="users/edit.php?id=' . htmlspecialchars($row['ID']) . '" style="margin-right: 10px;" title="Edit user"><i class="fa fa-edit" aria-hidden="true"></i></a>';
                                    echo '<i class="fa fa-trash" aria-hidden="true" style="cursor: pointer;" onclick="deleteUser(\'' . htmlspecialchars($row['username'], ENT_QUOTES) . '\')" title="Delete user"></i>';
                                    echo '</td>';
                                } else {
                                    echo '<td><a href="users/change_password.php" title="Change password"><i class="fa fa-key" aria-hidden="true"></i></a></td>';
                                }
                                echo '</tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                        
                        <!-- Add a hidden CSRF token field -->
                        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <script>
                          function deleteUser(username) {
                            // Confirmation dialog
                            if (!confirm("Are you sure you want to delete user: " + username + "?")) {
                                return; // Stop if user cancels
                            }
                            
                            // Get the CSRF token
                            const csrfToken = document.getElementById('csrf_token').value;
                            
                            // Send a POST request to delete_user.php with the username and CSRF token
                            fetch('delete_user.php', {
                              method: 'POST',
                              headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: 'username=' + encodeURIComponent(username) + 
                                    '&csrf_token=' + encodeURIComponent(csrfToken),
                            })
                            .then(response => {
                              if (!response.ok) {
                                return response.text().then(text => { throw new Error(text || 'Error deleting user') });
                              }
                              return response.text();
                            })
                            .then(data => {
                              // Display success message and reload the page
                              alert('User deleted successfully');
                              location.reload();
                            })
                            .catch(error => {
                              // Display error message
                              alert('Error: ' + error.message);
                            });
                          }
                        </script>
                    </div>
                </section>
            </main>
        </div>
    </body>
<?php endif; ?>
</html>