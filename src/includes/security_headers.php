<?php
/**
 * Security Headers
 * 
 * This file sets security headers for all pages to protect against common attacks.
 * Include this file at the top of every page after session_start().
 */

// Prevent clickjacking attacks
header("X-Frame-Options: DENY");

// Prevent MIME-sniffing attacks
header("X-Content-Type-Options: nosniff");

// Enable XSS protection in older browsers
header("X-XSS-Protection: 1; mode=block");

// Referrer policy - only send referrer to same origin
header("Referrer-Policy: same-origin");

// Content Security Policy - Restrictive policy for enhanced security
// Note: This is a basic policy. Adjust based on your specific needs.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'");

// Permissions Policy - Disable features not needed
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Strict Transport Security - Force HTTPS (uncomment when using HTTPS)
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

?>
