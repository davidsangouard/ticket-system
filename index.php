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

require_once 'includes/auth.php';

// Redirect to appropriate dashboard based on role
if (isLoggedIn()) {
    $role = getUserRole();
    
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'technician') {
        header('Location: technician/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
} else {
    header('Location: login.php');
}

exit;
