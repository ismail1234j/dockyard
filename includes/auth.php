<?php
session_start();

/*
if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') == false) {
    // Running under OpenLiteSpeed or LiteSpeed
    echo "<script>alert('This app is better worked with OpenLiteSpeed or Litespeed Web Server.');</script>";
}
*/

// Determine the correct relative path to the database file
$root_dir = dirname(__DIR__); // Get parent directory of includes folder
$db_path = $root_dir . '/data/db.sqlite'; // Default full path for database

try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Ensure PDO throws exceptions
} catch (PDOException $e) {
    // Log error: error_log("Database Connection Error: " . $e->getMessage());
    // Display a generic error message and stop execution
    // You might want a more user-friendly error page in a real application
    die("Database connection failed. Please try again later or contact support.");
}

// Session Timeout Check
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
    // Redirect to login page after timeout
    $login_path = (strpos($_SERVER['PHP_SELF'], '/apps/') !== false || strpos($_SERVER['PHP_SELF'], '/users/') !== false) ? '../login.php' : 'login.php';
    header('Location: ' . $login_path);
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

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

// Authentication Check
$auth = false;
if (isset($_SESSION['username']) && isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // User is authenticated via session
    $auth = true;
} else {
    $auth = false;
}

// If not authenticated, redirect to login (except for login.php itself)
if (!$auth && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    $login_path = (strpos($_SERVER['PHP_SELF'], '/apps/') !== false || strpos($_SERVER['PHP_SELF'], '/users/') !== false) ? '../login.php' : 'login.php';
    header('Location: ' . $login_path);
    exit;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token']) && $auth) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to check for unauthorized error and add modal HTML and JavaScript
function checkForUnauthorizedError() {
    if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
        echo <<<HTML
        <!-- Modal HTML structure -->
        <div id="unauthorized-modal" class="modal" style="display:block; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
            <div style="background-color:#13171f; margin:15% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3 style="margin:0;">Access Denied</h3>
                    <span id="close-modal" style="cursor:pointer; font-size:24px; font-weight:bold;">&times;</span>
                </div>
                <hr style="margin:0 0 15px 0; border:0; border-top:1px solid #eee;">
                <p>You don't have permission to access this resource.</p>
                <div style="text-align:right; margin-top:20px;">
                    <button id="dismiss-modal" class="secondary">Dismiss</button>
                </div>
            </div>
        </div>

        <!-- Modal JavaScript -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Get the modal
                var modal = document.getElementById('unauthorized-modal');
                
                // Get the close button element
                var closeBtn = document.getElementById('close-modal');
                
                // Get the dismiss button
                var dismissBtn = document.getElementById('dismiss-modal');
                
                // Function to close the modal
                function closeModal() {
                    modal.style.display = "none";
                    // Remove the error parameter from the URL without refreshing the page
                    const url = new URL(window.location);
                    url.searchParams.delete('error');
                    window.history.replaceState({}, document.title, url);
                }
                
                // When the user clicks on the close button, close the modal
                closeBtn.onclick = closeModal;
                
                // When the user clicks on the dismiss button, close the modal
                dismissBtn.onclick = closeModal;
                
                // When the user clicks anywhere outside of the modal, close it
                window.onclick = function(event) {
                    if (event.target == modal) {
                        closeModal();
                    }
                }
            });
        </script>
HTML;
    }
}

// Function to check admin privileges
function require_admin() {
    if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
        // Redirect with unauthorized error parameter
        $path = (strpos($_SERVER['PHP_SELF'], '/apps/') !== false || strpos($_SERVER['PHP_SELF'], '/users/') !== false) ? '../index.php' : 'index.php';
        header('Location: ' . $path . '?error=unauthorized');
        exit;
    }
}

// Call the function immediately after defining it
checkForUnauthorizedError();
?>