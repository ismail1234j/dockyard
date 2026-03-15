<?php
session_start();

// If the user is already authenticated, send them to the index
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: index.php');
    exit;
}

/**
 * Handle the button click. 
 * We redirect to a script (or the same page) that includes your OIDC auth logic.
 */
if (isset($_POST['login_oidc'])) {
    // This file contains the $oidc->authenticate() logic we wrote earlier
    require_once 'includes/auth.php'; 
    exit;
}
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .login-container {
            max-width: 400px;
            margin: 10% auto;
            text-align: center;
        }
        .oidc-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <main class="container">
        <article class="login-container">
            <header>
                <h2>Dockyard</h2>
            </header>
            
            <form method="post">
                <button type="submit" name="login_oidc" class="oidc-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Login with OIDC
                </button>
            </form>
            
            <footer>
            </footer>
        </article>
    </main>
</body>
</html>