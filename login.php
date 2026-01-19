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
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getUserRole();
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'technician') {
        header('Location: technician/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting
    if (!checkRateLimit('login', 5, 300)) {
        $error = 'Too many login attempts. Please try again in 5 minutes.';
        logSecurityEvent('login_rate_limit_exceeded');
    } else {
        // CSRF protection
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid security token. Please try again.';
            logSecurityEvent('csrf_token_invalid', ['action' => 'login']);
        } else {
            $email = sanitize($_POST['email'] ?? '', 'email');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error = 'Please enter both email and password';
            } elseif (!isValidEmail($email)) {
                $error = 'Invalid email format';
            } else {
                $auth = new Auth();
                if ($auth->login($email, $password)) {
                    // Clear rate limit on successful login
                    unset($_SESSION['rate_limit_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]);
                    
                    $role = $_SESSION['role'];
                    if ($role === 'admin') {
                        header('Location: admin/dashboard.php');
                    } elseif ($role === 'technician') {
                        header('Location: technician/dashboard.php');
                    } else {
                        header('Location: user/dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Invalid email or password';
                    logSecurityEvent('login_failed', ['email' => $email]);
                }
            }
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-card {
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-light);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo"><?php echo APP_NAME; ?></div>
                <div class="login-subtitle">Sign in to your account</div>
            </div>
            
            <div class="card">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> mb-2">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error mb-2">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            required
                            autocomplete="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            required
                            autocomplete="current-password"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
