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
$user_id = getCurrentUserId();

// Get statistics
$stats = [];

// Total tickets
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total'] = $stmt->fetch()['count'];

// Open tickets
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets t
    JOIN statuses s ON t.status_id = s.id
    WHERE t.user_id = ? AND s.type = 'open'
");
$stmt->execute([$user_id]);
$stats['open'] = $stmt->fetch()['count'];

// In progress tickets
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets t
    JOIN statuses s ON t.status_id = s.id
    WHERE t.user_id = ? AND s.type = 'in_progress'
");
$stmt->execute([$user_id]);
$stats['in_progress'] = $stmt->fetch()['count'];

// Resolved tickets
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets t
    JOIN statuses s ON t.status_id = s.id
    WHERE t.user_id = ? AND s.type = 'closed'
");
$stmt->execute([$user_id]);
$stats['resolved'] = $stmt->fetch()['count'];

// My tickets
$stmt = $db->prepare("
    SELECT 
        t.id,
        t.subject,
        t.created_at,
        t.updated_at,
        CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) as assigned_name,
        p.name as priority,
        s.name as status,
        c.name as category
    FROM tickets t
    LEFT JOIN users a ON t.assigned_to = a.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN statuses s ON t.status_id = s.id
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$my_tickets = $stmt->fetchAll();

// Get categories and priorities for create form
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$priorities = $db->query("SELECT id, name FROM priorities ORDER BY level")->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - <?php echo APP_NAME; ?></title>
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
            
            <div class="d-flex justify-between align-center mb-3">
                <h1>My Tickets</h1>
                <button class="btn btn-primary" onclick="openModal('createModal')">
                    Create Ticket
                </button>
            </div>
            
            <!-- My Tickets -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Tickets</h2>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_tickets)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No tickets found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($my_tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo $ticket['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['category']); ?></td>
                                    <td>
                                        <span class="badge <?php echo getPriorityClass($ticket['priority']); ?>">
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusClass($ticket['status']); ?>">
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['assigned_name']) ?: 'Unassigned'; ?></td>
                                    <td><?php echo timeAgo($ticket['created_at']); ?></td>
                                    <td><?php echo timeAgo($ticket['updated_at']); ?></td>
                                    <td>
                                        <a href="ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-secondary btn-sm">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Create Ticket Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Ticket</h2>
                <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" action="ticket-create.php">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" required rows="6"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority_id" class="form-control" required>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?php echo $priority['id']; ?>" <?php echo $priority['name'] === 'Medium' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($priority['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
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
