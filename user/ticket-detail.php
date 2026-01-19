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
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$db = Database::getInstance()->getConnection();
$ticket_id = (int)($_GET['id'] ?? 0);
$current_user_id = getCurrentUserId();

if (!$ticket_id) {
    redirect('dashboard.php', 'Invalid ticket ID', 'error');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_comment') {
        $content = sanitize($_POST['content']);
        
        $stmt = $db->prepare("
            INSERT INTO comments (ticket_id, user_id, content, is_internal)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$ticket_id, $current_user_id, $content]);
        
        redirect("ticket-detail.php?id=$ticket_id", 'Comment added successfully');
    }
}

// Get ticket details
$stmt = $db->prepare("
    SELECT 
        t.*,
        u.username as creator_username,
        u.email as creator_email,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as creator_name,
        a.username as assigned_username,
        CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) as assigned_name,
        p.name as priority,
        p.color as priority_color,
        s.name as status,
        s.color as status_color,
        c.name as category
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN statuses s ON t.status_id = s.id
    JOIN categories c ON t.category_id = c.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$ticket_id, $current_user_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('dashboard.php', 'Ticket not found or access denied', 'error');
}

// Get comments (non-internal only for users)
$stmt = $db->prepare("
    SELECT 
        c.*,
        u.username,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.ticket_id = ? AND c.is_internal = 0
    ORDER BY c.created_at ASC
");
$stmt->execute([$ticket_id]);
$comments = $stmt->fetchAll();

// Get history
$stmt = $db->prepare("
    SELECT 
        h.*,
        u.username,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
    FROM ticket_history h
    JOIN users u ON h.user_id = u.id
    WHERE h.ticket_id = ?
    ORDER BY h.created_at DESC
    LIMIT 20
");
$stmt->execute([$ticket_id]);
$history = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket['id']; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-2">
                <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back to My Tickets</a>
            </div>
            
            <!-- Ticket Header -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
                    <div>
                        <h1 style="margin-bottom: 0.5rem;">
                            #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?>
                        </h1>
                        <div class="text-muted" style="font-size: 0.875rem;">
                            Created by <?php echo htmlspecialchars($ticket['creator_username']); ?> 
                            on <?php echo formatDate($ticket['created_at']); ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end;">
                        <span class="badge <?php echo getPriorityClass($ticket['priority']); ?>">
                            <?php echo $ticket['priority']; ?>
                        </span>
                        <span class="badge <?php echo getStatusClass($ticket['status']); ?>">
                            <?php echo $ticket['status']; ?>
                        </span>
                        <span class="badge badge-low">
                            <?php echo $ticket['category']; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Ticket Info -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; padding: 1rem; background: var(--background); border-radius: 6px;">
                    <div>
                        <div class="text-muted" style="font-size: 0.8125rem; margin-bottom: 0.25rem;">Assigned To</div>
                        <div><?php echo $ticket['assigned_to'] ? htmlspecialchars($ticket['assigned_username']) : 'Unassigned'; ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.8125rem; margin-bottom: 0.25rem;">Last Updated</div>
                        <div><?php echo timeAgo($ticket['updated_at']); ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.8125rem; margin-bottom: 0.25rem;">Creator Email</div>
                        <div><?php echo htmlspecialchars($ticket['creator_email']); ?></div>
                    </div>
                </div>
                
                <!-- Description -->
                <div style="padding: 1rem; background: var(--background); border-radius: 6px; margin-bottom: 1.5rem;">
                    <div class="text-muted" style="font-size: 0.8125rem; margin-bottom: 0.5rem;">Description</div>
                    <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($ticket['description']); ?></div>
                </div>
                
                <!-- No actions for users - they can only view and comment -->
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; margin-top: 1.5rem;">
                <!-- Comments -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Comments (<?php echo count($comments); ?>)</h2>
                        </div>
                        
                        <?php if (empty($comments)): ?>
                            <div class="text-center text-muted" style="padding: 2rem;">
                                No comments yet
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($comment['username']); ?>
                                            <?php if ($comment['is_internal']): ?>
                                                <span class="badge badge-high" style="margin-left: 0.5rem;">Internal</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.8125rem;">
                                            <?php echo timeAgo($comment['created_at']); ?>
                                        </div>
                                    </div>
                                    <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($comment['content']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Add Comment Form -->
                        <div style="padding: 1rem; background: var(--surface-light);">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_comment">
                                <div class="form-group">
                                    <textarea 
                                        name="content" 
                                        class="form-control" 
                                        rows="4" 
                                        placeholder="Add a comment..." 
                                        required
                                    ></textarea>
                                </div>
                                <div style="display: flex; justify-content: flex-end;">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Add Comment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- History Sidebar -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Activity History</h2>
                        </div>
                        
                        <?php if (empty($history)): ?>
                            <div class="text-center text-muted" style="padding: 1rem;">
                                No activity yet
                            </div>
                        <?php else: ?>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($history as $item): ?>
                                    <div style="padding: 0.75rem; border-bottom: 1px solid var(--border); font-size: 0.875rem;">
                                        <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                            <?php echo htmlspecialchars($item['username']); ?>
                                        </div>
                                        <div class="text-muted" style="margin-bottom: 0.25rem;">
                                            <?php echo ucfirst($item['action']); ?>
                                            <?php if ($item['field_changed']): ?>
                                                <?php echo htmlspecialchars($item['field_changed']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?php echo timeAgo($item['created_at']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // No modals needed for users
    </script>
</body>
</html>
