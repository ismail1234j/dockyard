<?php
/**
 * Email Sender Utility
 * 
 * This file provides email sending functionality for the Dockyard application.
 * Configure your SMTP settings in the configuration section below.
 * 
 * Usage Example:
 * require_once 'includes/email.php';
 * $result = send_email('user@example.com', 'Subject', 'Message body');
 */

// Email Configuration
// TODO: Move these to a configuration file or environment variables
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@dockyard.local');
define('SMTP_FROM_NAME', 'Dockyard Container Manager');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl' or '' for none

/**
 * Send an email using PHP's mail() function
 * For production, consider using PHPMailer or similar library for SMTP support
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (can be HTML)
 * @param bool $isHTML Whether the message is HTML (default: false)
 * @return bool True on success, false on failure
 */
function send_email($to, $subject, $message, $isHTML = false) {
    try {
        // Validate email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email address: $to");
            return false;
        }
        
        // Prepare headers
        $headers = [];
        $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . SMTP_FROM_EMAIL;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        if ($isHTML) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        // Send email using PHP's mail() function
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email sent successfully to: $to");
            return true;
        } else {
            error_log("Failed to send email to: $to");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a password reset notification email
 * 
 * @param string $to User's email address
 * @param string $username User's username
 * @return bool Success status
 */
function send_password_reset_email($to, $username) {
    $subject = 'Password Reset Required';
    $message = "Hello $username,\n\n";
    $message .= "An administrator has requested that you reset your password.\n";
    $message .= "You will be prompted to change your password on your next login.\n\n";
    $message .= "If you have any questions, please contact your administrator.\n\n";
    $message .= "Best regards,\n";
    $message .= "Dockyard Container Manager";
    
    return send_email($to, $subject, $message);
}

/**
 * Send a container status notification email
 * 
 * @param string $to User's email address
 * @param string $containerName Container name
 * @param string $status New status
 * @return bool Success status
 */
function send_container_notification($to, $containerName, $status) {
    $subject = "Container Status Update: $containerName";
    $message = "Container '$containerName' status has changed to: $status\n\n";
    $message .= "Please check the Dockyard dashboard for more details.\n\n";
    $message .= "Best regards,\n";
    $message .= "Dockyard Container Manager";
    
    return send_email($to, $subject, $message);
}

/**
 * Send a test email to verify configuration
 * 
 * @param string $to Test recipient email
 * @return bool Success status
 */
function send_test_email($to) {
    $subject = 'Dockyard Email Test';
    $message = "This is a test email from Dockyard Container Manager.\n\n";
    $message .= "If you received this email, your email configuration is working correctly.\n\n";
    $message .= "Timestamp: " . date('Y-m-d H:i:s');
    
    return send_email($to, $subject, $message);
}

?>
