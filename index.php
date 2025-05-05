<?php
// Remove duplicate session management and use centralized auth
require_once 'includes/auth.php';
?>
<html data-theme="light">
    <head>
        <title>Home</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <?php if ($auth) : ?>
        <body>
<div class="container" style="margin-top: 6%">
<header>
<section>
<h1>You are logged in: <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
<a href="logout.php">Log Out</a>
</section>
</header>
<hr />
<main>
<section>
<div class="grid"> 
<?php if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) : ?>
<div role="button" tabindex="0" class="secondary" onclick="location.href='apps.php';">Container Manager</div>
<div role="button" tabindex="0" class="secondary" onclick="location.href='users.php';">Users</div>
<div role="button" tabindex="0" class="secondary" onclick="location.href='settings.php';">Settings</div>
<?php else: ?>
<div role="button" tabindex="0" class="secondary" onclick="location.href='apps.php';">My Containers</div>
<?php endif; ?>
</div>
</section> 
</main>
<footer>

</footer>
</div>
</body>
    <?php endif; ?>
</html>
