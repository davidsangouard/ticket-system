<!--
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
-->
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header>
    <div class="container">
        <div class="header-content">
            <div class="logo"><?php echo APP_NAME; ?></div>
            
            <nav class="nav">
                <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    Dashboard
                </a>
                <a href="tickets.php" class="<?php echo $current_page === 'tickets.php' ? 'active' : ''; ?>">
                    Tickets
                </a>
                <a href="users.php" class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                    Users
                </a>
                <a href="technicians.php" class="<?php echo $current_page === 'technicians.php' ? 'active' : ''; ?>">
                    Technicians
                </a>
                <a href="categories.php" class="<?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                    Settings
                </a>
            </nav>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo getInitials($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </div>
    </div>
</header>
