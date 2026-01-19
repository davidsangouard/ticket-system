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
                    All Tickets
                </a>
                <a href="my-tickets.php" class="<?php echo $current_page === 'my-tickets.php' ? 'active' : ''; ?>">
                    My Tickets
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
