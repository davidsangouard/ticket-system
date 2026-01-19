<?php
/*
    ═════════════════════════════════════════════════════════
    
         ██████╗ ███████╗
         ██╔══██╗██╔════╝
         ██║  ██║███████╗    David Sangouard
         ██║  ██║╚════██║    
         ██████╔╝███████║    github.com/davidsangouard
         ╚═════╝ ╚══════╝    
    
         » Architect of digital solutions
         » Building the web, one pixel at a time
    
    ═════════════════════════════════════════════════════════
*/

/**
 * Security headers configuration
 * Include this file at the top of every page
 */

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection
header('X-XSS-Protection: 1; mode=block');

// Referrer policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (CSP)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';");

// Permissions policy
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Strict Transport Security (uncomment when using HTTPS)
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Prevent caching of sensitive pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Session security settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 when using HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_trans_sid', 0);
    
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}
