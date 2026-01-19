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

if (!isTechnician()) {
    header('Location: ../index.php');
    exit;
}

requireLogin();

$db = Database::getInstance()->getConnection();
$ticket_id = (int)($_GET['id'] ?? 0);

if (!$ticket_id) {
    redirect('tickets.php', 'Invalid ticket ID', 'error');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $status_id = (int)$_POST['status_id'];
        
        $stmt = $db->prepare("UPDATE tickets SET status_id = ? WHERE id = ?");
        $stmt->execute([$status_id, $ticket_id]);
        
        // Log history
        $stmt = $db->prepare("
            INSERT INTO ticket_history (ticket_id, user_id, action, field_changed, new_value)
            VALUES (?, ?, 'updated', 'status', ?)
        ");
        $stmt->execute([$ticket_id, getCurrentUserId(), $status_id]);
        
        redirect("ticket-detail.php?id=$ticket_id", 'Status updated successfully');
    } elseif ($action === 'update_priority') {
        $priority_id = (int)$_POST['priority_id'];
        
        $stmt = $db->prepare("UPDATE tickets SET priority_id = ? WHERE id = ?");
        $stmt->execute([$priority_id, $ticket_id]);
        
        // Log history
        $stmt = $db->prepare("
            INSERT INTO ticket_history (ticket_id, user_id, action, field_changed, new_value)
            VALUES (?, ?, 'updated', 'priority', ?)
        ");
        $stmt->execute([$ticket_id, getCurrentUserId(), $priority_id]);
        
        redirect("ticket-detail.php?id=$ticket_id", 'Priority updated successfully');
    } elseif ($action === 'assign') {
        $assigned_to = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;
        
        $stmt = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$assigned_to, $ticket_id]);
        
        // Log history
        $stmt = $db->prepare("
            INSERT INTO ticket_history (ticket_id, user_id, action, new_value)
            VALUES (?, ?, 'assigned', ?)
        ");
        $stmt->execute([$ticket_id, getCurrentUserId(), $assigned_to]);
        
        redirect("ticket-detail.php?id=$ticket_id", 'Assignment updated successfully');
    } elseif ($action === 'add_comment') {
        $content = sanitize($_POST['content']);
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        
        $stmt = $db->prepare("
            INSERT INTO comments (ticket_id, user_id, content, is_internal)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, getCurrentUserId(), $content, $is_internal]);
        
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
    WHERE t.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('tickets.php', 'Ticket not found', 'error');
}

// Get comments
$stmt = $db->prepare("
    SELECT 
        c.*,
        u.username,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.ticket_id = ?
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

// Get options
$priorities = $db->query("SELECT id, name FROM priorities ORDER BY level")->fetchAll();
$statuses = $db->query("SELECT id, name FROM statuses ORDER BY name")->fetchAll();
$technicians = $db->query("SELECT id, username, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name FROM users WHERE role IN ('technician', 'admin') AND is_active = 1 ORDER BY username")->fetchAll();

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
                <a href="tickets.php" class="btn btn-secondary btn-sm">← Back to Tickets</a>
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
                
                <!-- Actions -->
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-secondary btn-sm" onclick="openModal('statusModal')">
                        Change Status
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="openModal('priorityModal')">
                        Change Priority
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="openModal('assignModal')">
                        <?php echo $ticket['assigned_to'] ? 'Reassign' : 'Assign'; ?>
                    </button>
                </div>
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
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                                        <input type="checkbox" name="is_internal">
                                        <span>Internal comment</span>
                                    </label>
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
    
    <!-- Change Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change Status</h2>
                <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select name="status_id" class="form-control" required>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>" <?php echo $status['id'] == $ticket['status_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Priority Modal -->
    <div id="priorityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change Priority</h2>
                <button class="modal-close" onclick="closeModal('priorityModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_priority">
                <div class="form-group">
                    <label class="form-label">New Priority</label>
                    <select name="priority_id" class="form-control" required>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?php echo $priority['id']; ?>" <?php echo $priority['id'] == $ticket['priority_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($priority['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('priorityModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Assign Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Ticket</h2>
                <button class="modal-close" onclick="closeModal('assignModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="form-group">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">Unassign</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>" <?php echo $tech['id'] == $ticket['assigned_to'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['username']); ?>
                                <?php if ($tech['full_name']): ?>
                                    (<?php echo htmlspecialchars(trim($tech['full_name'])); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
