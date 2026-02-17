<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
$db = get_db();
require_admin();
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Users</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
    <style>
        :root {
            --transition-speed: 0.3s;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 22px;
            vertical-align: middle;
            margin: 0;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: var(--pico-muted-border-color);
            transition: var(--transition-speed);
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition-speed);
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input:checked + .slider {
            background-color: var(--pico-primary-background);
        }
        input:checked + .slider:before {
            transform: translateX(22px);
        }
        table td {
            vertical-align: middle;
        }
        .action-links a, .action-links i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            transition: background 0.2s;
            text-decoration: none;
        }
        .action-links a:hover {
            background: var(--pico-secondary-hover-background);
        }
        .text-danger { color: #d93526 !important; }
        .text-success { color: #388e3c !important; }
        header hgroup {
            margin-bottom: var(--pico-spacing);
        }
        .header-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }
    </style>
</head>
<?php if ($auth): ?>
<body>
    <div class="container" style="padding-top: 2rem;">
        <header>
            <hgroup>
                <h1>User Management</h1>
            </hgroup>
            <nav class="header-actions">
                <button class="secondary outline" onclick="location.href='index.php';">
                    <i class="fa fa-arrow-left"></i> Back
                </button>
                <button class="contrast" onclick="location.href='users/new.php';">
                    <i class="fa fa-plus"></i> Create User
                </button>
                <button class="outline" onclick="location.href='users/change_password.php';">
                    <i class="fa fa-key"></i> My Password
                </button>
            </nav>
        </header>
        <hr />
        <main>
            <figure>
                <table role="grid">
                    <thead>
                        <tr>
                            <th scope="col">Username</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Force Reset</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmt = $db->prepare('SELECT * FROM users ORDER BY username');
                    $stmt->execute();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<tr>';
                        // Username
                        echo '<td>';
                        if ($row['username'] === $username) {
                            echo '<strong>' . htmlspecialchars($row['username']) . '</strong>';
                        } else {
                            echo htmlspecialchars($row['username']);
                        }
                        echo '</td>';
                        // Email
                        echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                        // Role
                        echo '<td>';
                        if ($row['IsAdmin']) {
                            echo '<mark>Admin</mark>';
                        } else {
                            echo 'User';
                        }
                        echo '</td>';
                        // Force Reset
                        $forceReset = isset($row['force_password_reset']) && $row['force_password_reset'] ? true : false;
                        echo '<td>';
                        if ($row['username'] !== $username) {
                            echo '<label class="switch" title="Toggle force password reset">';
                            echo '<input type="checkbox" ' . ($forceReset ? 'checked' : '') . ' onchange="toggleForceReset(' . $row['ID'] . ', this.checked)">';
                            echo '<span class="slider"></span>';
                            echo '</label>';
                        } else {
                            echo '-';
                        }
                        echo '</td>';
                        // Actions
                        echo '<td class="action-links">';
                        if ($row['username'] !== $username) {
                            echo '<a href="users/edit.php?id=' . htmlspecialchars($row['ID']) . '" title="Edit"><i class="fa fa-edit"></i></a>';
                            echo '<a href="users/manage_permissions.php?id=' . htmlspecialchars($row['ID']) . '" title="Docker Permissions"><i class="fa fa-plug"></i></a>';
                            echo '<i class="fa fa-trash text-danger" style="cursor: pointer;" onclick="deleteUser(\'' . htmlspecialchars($row['username'], ENT_QUOTES) . '\')" title="Delete User"></i>';
                        } else {
                            echo '<a href="users/change_password.php" title="Change Password"><i class="fa fa-key"></i></a>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </figure>
        </main>
        <!-- Hidden CSRF -->
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    </div>
    <script>
        function deleteUser(username) {
            if (!confirm(`Permanently delete user "${username}"?`)) return;
            const csrfToken = document.getElementById('csrf_token').value;
            fetch('delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                alert('User deleted successfully');
                location.reload();
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        function toggleForceReset(userId, enabled) {
            const csrfToken = document.getElementById('csrf_token').value;
            fetch('users/toggle_force_reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'user_id=' + encodeURIComponent(userId) + 
                      '&enabled=' + encodeURIComponent(enabled ? '1' : '0') +
                      '&csrf_token=' + encodeURIComponent(csrfToken),
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => { throw new Error(text || 'Error toggling force reset') });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                location.reload();
            });
        }
    </script>
</body>
<?php endif; ?>
</html>