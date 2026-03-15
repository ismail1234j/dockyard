<?php
/* NOTES:
   - All client facing pages should use this
   - Login.php doesn't use this (for obvious reasons)
   - All admin facing pages must call require_admin()
*/
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'db.php';
$db = get_db();

use Jumbojett\OpenIDConnectClient;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: /login.php'); 
    exit;
}

/**
 * Upsert??? local user from OIDC identity
 * Admin is always taken from OIDC group membership
 */
function sync_oidc_user(PDO $db, string $username, ?string $email, bool $oidcIsAdmin): array {
    $stmt = $db->prepare('SELECT ID, username, email, IsAdmin FROM users WHERE username = :username LIMIT 1');
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $isAdminInt = $oidcIsAdmin ? 1 : 0;

    if ($existing) {
        // OIDC is the only source of truth for admin role
        $upd = $db->prepare('UPDATE users SET email = :email, IsAdmin = :is_admin WHERE ID = :id');
        $upd->bindValue(':email', $email, PDO::PARAM_STR);
        $upd->bindValue(':is_admin', $isAdminInt, PDO::PARAM_INT);
        $upd->bindValue(':id', (int)$existing['ID'], PDO::PARAM_INT);
        $upd->execute();

        $existing['email'] = $email;
        $existing['IsAdmin'] = $isAdminInt;
        return $existing;
    }

    // New OIDC users are normal unless in admin group
    // Basically how it was before
    // We use a random password, users will never log in with it but it satisfies the DB schema
    $placeholderPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $ins = $db->prepare('
        INSERT INTO users (username, password, email, IsAdmin, force_password_reset)
        VALUES (:username, :password, :email, :is_admin, 0)
    ');
    $ins->bindValue(':username', $username, PDO::PARAM_STR);
    $ins->bindValue(':password', $placeholderPassword, PDO::PARAM_STR);
    $ins->bindValue(':email', $email, PDO::PARAM_STR);
    $ins->bindValue(':is_admin', $isAdminInt, PDO::PARAM_INT);
    $ins->execute();

    return [
        'ID' => (int)$db->lastInsertId(),
        'username' => $username,
        'email' => $email,
        'IsAdmin' => $isAdminInt,
    ];
}

// OIDC flow
if (!isset($_SESSION['authenticated'])) {
    try {
        $oidc = new OpenIDConnectClient(
            "https://auth.ismailj.eu.org/", 
            "54b7bad7-ed95-4361-8cb5-cd15512dc15b",
            "vHuGEg838ZQqma709nInQA5DruD3DYvH"
        );

        $oidc->addScope(['openid', 'profile', 'email', 'groups']);
        
        // Dev only
        // Todo: Make hostname into a config option
        $oidc->setRedirectURL('http://localhost:8001/index.php');

        // Jumbojett's lib does the rest here, no need for a callback page or anything
        $oidc->authenticate();

        $userInfo = $oidc->requestUserInfo();

        // Sync OIDC to Session
        $oidcUsername = $userInfo->preferred_username ?? ($userInfo->name ?? 'User');
        $oidcEmail = $userInfo->email ?? null;
        $oidcIsAdmin = isset($userInfo->groups) && in_array('dy_admin', (array)$userInfo->groups, true);

        $localUser = sync_oidc_user($db, $oidcUsername, $oidcEmail, $oidcIsAdmin);

        $_SESSION['authenticated'] = true;
        $_SESSION['oidc_sub'] = (string)($userInfo->sub ?? '');
        $_SESSION['user_id'] = (int)$localUser['ID'];   // keep local DB ID
        $_SESSION['username'] = $localUser['username'];
        $_SESSION['email'] = $localUser['email'];
        $_SESSION['isAdmin'] = ((int)$localUser['IsAdmin'] === 1);
        
        // Admin check
        // Todo: make this user definable
        $_SESSION['isAdmin'] = isset($userInfo->groups) && in_array('dy_admin', (array)$userInfo->groups);

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    } catch (Exception $e) {
        error_log("OIDC Auth Error: " . $e->getMessage());
        die("Authentication failed. Please try again later.");
    }
}

$_SESSION['LAST_ACTIVITY'] = time();
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

function require_admin(): void {
    if (empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}

// CSRF token check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['csrf_token'])) {
    // Skip CSRF check for the login form itself
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        // Log potential CSRF attempt
        error_log("CSRF token missing in POST request to " . $_SERVER['PHP_SELF']);
        http_response_code(403);
        die("Security error: form submission failed validation");
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'], $_SESSION['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Log CSRF token mismatch
        error_log("CSRF token mismatch in POST request to " . $_SERVER['PHP_SELF']);
        http_response_code(403);
        die("Security error: invalid form submission");
    }
}

