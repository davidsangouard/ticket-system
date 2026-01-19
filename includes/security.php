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
 * Enhanced security functions for XSS, CSRF, SQL injection prevention
 */

/**
 * Sanitize input data with strict filtering
 */
function sanitize($data, $type = 'string') {
    if (is_array($data)) {
        return array_map(function($item) use ($type) {
            return sanitize($item, $type);
        }, $data);
    }
    
    // Remove null bytes
    $data = str_replace(chr(0), '', $data);
    
    // Trim whitespace
    $data = trim($data);
    
    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT) !== false ? (int)$data : 0;
            
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) !== false ? (float)$data : 0.0;
            
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
            
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
            
        case 'html':
            // Allow limited HTML tags
            return strip_tags($data, '<p><br><strong><em><ul><ol><li>');
            
        case 'string':
        default:
            // Remove all HTML tags and encode special chars
            return htmlspecialchars(strip_tags($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Validate integer ID
 */
function validateId($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : 0;
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username (alphanumeric, underscore, hyphen only)
 */
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username);
}

/**
 * Generate secure CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token with timing attack protection
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Check token age (1 hour max)
    if (isset($_SESSION['csrf_token_time']) && 
        (time() - $_SESSION['csrf_token_time']) > 3600) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF token field for forms
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate and sanitize file upload
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload'];
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File too large'];
        default:
            return ['success' => false, 'error' => 'Upload error'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File exceeds maximum size'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    return ['success' => true, 'mime_type' => $mimeType];
}

/**
 * Secure redirect with header injection prevention
 */
function redirect($url, $message = null, $type = 'success') {
    // Prevent header injection
    $url = str_replace(["\r", "\n"], '', $url);
    
    // Validate URL format
    if (!preg_match('/^[a-zA-Z0-9\/_.-]+(\?[a-zA-Z0-9=&_-]+)?$/', $url)) {
        $url = 'index.php';
    }
    
    if ($message) {
        $_SESSION['flash_message'] = sanitize($message);
        $_SESSION['flash_type'] = in_array($type, ['success', 'error', 'warning']) ? $type : 'success';
    }
    
    header("Location: $url", true, 302);
    exit;
}

/**
 * Format date for display (safe from XSS)
 */
function formatDate($date) {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Invalid date';
    return htmlspecialchars(date('M d, Y H:i', $timestamp), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date relative to now (safe from XSS)
 */
function timeAgo($date) {
    if (empty($date)) return 'N/A';
    
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Invalid date';
    
    $diff = time() - $timestamp;
    
    if ($diff < 0) return 'In the future';
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return htmlspecialchars(date('M d, Y', $timestamp), ENT_QUOTES, 'UTF-8');
}

/**
 * Get priority badge class (validated input)
 */
function getPriorityClass($priority) {
    $priority = strtolower(sanitize($priority));
    
    $classes = [
        'low' => 'badge-low',
        'medium' => 'badge-medium',
        'high' => 'badge-high',
        'critical' => 'badge-critical'
    ];
    
    return isset($classes[$priority]) ? $classes[$priority] : 'badge-low';
}

/**
 * Get status badge class (validated input)
 */
function getStatusClass($status) {
    $status = strtolower(sanitize($status));
    
    if (strpos($status, 'closed') !== false || strpos($status, 'resolved') !== false) {
        return 'badge-closed';
    }
    if (strpos($status, 'progress') !== false || strpos($status, 'pending') !== false) {
        return 'badge-progress';
    }
    return 'badge-open';
}

/**
 * Generate avatar initials (safe from XSS)
 */
function getInitials($name) {
    $name = sanitize($name);
    $words = explode(' ', trim($name));
    
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

/**
 * Display flash message (XSS safe)
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = sanitize($_SESSION['flash_message']);
        $type = isset($_SESSION['flash_type']) ? sanitize($_SESSION['flash_type']) : 'success';
        
        // Validate type
        if (!in_array($type, ['success', 'error', 'warning'])) {
            $type = 'success';
        }
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Pagination helper with validated inputs
 */
function paginate($total, $perPage, $currentPage) {
    $total = max(0, (int)$total);
    $perPage = max(1, min(100, (int)$perPage)); // Limit to 100 per page
    $currentPage = max(1, (int)$currentPage);
    
    $totalPages = ceil($total / $perPage);
    $currentPage = min($currentPage, max(1, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Rate limiting helper
 */
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Reset if time window expired
    if (time() - $data['start'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    // Check if limit exceeded
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Log security event
 */
function logSecurityEvent($event, $details = []) {
    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'event' => $event,
        'details' => $details
    ];
    
    @file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
