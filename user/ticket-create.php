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

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

// CSRF protection
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    logSecurityEvent('csrf_token_invalid', ['action' => 'ticket_create', 'user_id' => getCurrentUserId()]);
    redirect('dashboard.php', 'Invalid security token', 'error');
}

$db = Database::getInstance()->getConnection();
$user_id = getCurrentUserId();

$subject = sanitize($_POST['subject'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$category_id = validateId($_POST['category_id'] ?? 0);
$priority_id = validateId($_POST['priority_id'] ?? 0);

// Validate inputs
if (empty($subject) || strlen($subject) < 5 || strlen($subject) > 200) {
    redirect('dashboard.php', 'Subject must be between 5 and 200 characters', 'error');
}

if (empty($description) || strlen($description) < 10) {
    redirect('dashboard.php', 'Description must be at least 10 characters', 'error');
}

if ($category_id === 0 || $priority_id === 0) {
    redirect('dashboard.php', 'Invalid category or priority', 'error');
}

// Verify category and priority exist
$stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
if ($stmt->fetchColumn() == 0) {
    redirect('dashboard.php', 'Invalid category', 'error');
}

$stmt = $db->prepare("SELECT COUNT(*) FROM priorities WHERE id = ?");
$stmt->execute([$priority_id]);
if ($stmt->fetchColumn() == 0) {
    redirect('dashboard.php', 'Invalid priority', 'error');
}

// Get default open status
$stmt = $db->query("SELECT id FROM statuses WHERE type = 'open' LIMIT 1");
$status_id = $stmt->fetch()['id'];

try {
    $stmt = $db->prepare("
        INSERT INTO tickets (subject, description, user_id, category_id, priority_id, status_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$subject, $description, $user_id, $category_id, $priority_id, $status_id]);
    
    $ticket_id = $db->lastInsertId();
    
    // Log history
    $stmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action)
        VALUES (?, ?, 'created')
    ");
    $stmt->execute([$ticket_id, $user_id]);
    
    redirect('dashboard.php', 'Ticket created successfully');
} catch (PDOException $e) {
    redirect('dashboard.php', 'Failed to create ticket', 'error');
}
