<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_admin(); // This function ensures only admins can access this page
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<?php if ($auth): ?>
    <body>
        <div class="container" style="margin-top: 6%">
            <header>
                <section>
                    <h1>Settings</h1>
                    <button class="secondary" onclick="location.href='index.php';">Back</button>
                </section>
            </header>
            <hr />
            <main>
                <section>
                    <h2>System Settings</h2>
                    <form action="update_settings.php" method="post">
                        <!-- Add CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <!-- Add your settings form fields here -->
                        <div class="grid">
                            <label for="setting1">
                                Setting 1
                                <input type="text" id="setting1" name="setting1" value="">
                            </label>
                            
                            <label for="setting2">
                                Setting 2
                                <input type="text" id="setting2" name="setting2" value="">
                            </label>
                        </div>
                        
                        <div class="grid">
                            <label for="setting3">
                                Setting 3
                                <select id="setting3" name="setting3">
                                    <option value="option1">Option 1</option>
                                    <option value="option2">Option 2</option>
                                    <option value="option3">Option 3</option>
                                </select>
                            </label>
                            
                            <label for="setting4">
                                Setting 4
                                <input type="checkbox" id="setting4" name="setting4" role="switch">
                            </label>
                        </div>
                        
                        <button type="submit" class="contrast">Save Settings</button>
                    </form>
                </section>
            </main>
            <footer>
                <p>Admin-only settings page</p>
            </footer>
        </div>
    </body>
<?php endif; ?>
</html>