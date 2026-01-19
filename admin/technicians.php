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

requireRole('admin');

$db = Database::getInstance()->getConnection();

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reassign') {
        $ticket_id = (int)$_POST['ticket_id'];
        $new_assigned_to = $_POST['new_assigned_to'] ? (int)$_POST['new_assigned_to'] : null;
        
        try {
            $stmt = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$new_assigned_to, $ticket_id]);
            
            // Log history
            $stmt = $db->prepare("
                INSERT INTO ticket_history (ticket_id, user_id, action, new_value)
                VALUES (?, ?, 'reassigned', ?)
            ");
            $stmt->execute([$ticket_id, getCurrentUserId(), $new_assigned_to]);
            
            redirect('technicians.php', 'Ticket reassigned successfully');
        } catch (PDOException $e) {
            $error = 'Failed to reassign ticket: ' . $e->getMessage();
        }
    } elseif ($action === 'unassign') {
        $ticket_id = (int)$_POST['ticket_id'];
        
        try {
            $stmt = $db->prepare("UPDATE tickets SET assigned_to = NULL WHERE id = ?");
            $stmt->execute([$ticket_id]);
            
            // Log history
            $stmt = $db->prepare("
                INSERT INTO ticket_history (ticket_id, user_id, action)
                VALUES (?, ?, 'unassigned')
            ");
            $stmt->execute([$ticket_id, getCurrentUserId()]);
            
            redirect('technicians.php', 'Ticket unassigned successfully');
        } catch (PDOException $e) {
            $error = 'Failed to unassign ticket: ' . $e->getMessage();
        }
    }
}

// Get filters
$technician_filter = $_GET['technician'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get technicians list
$technicians = $db->query("
    SELECT 
        u.id,
        u.username,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name,
        COUNT(t.id) as ticket_count
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_to
    WHERE u.role IN ('technician', 'admin') AND u.is_active = 1
    GROUP BY u.id
    ORDER BY u.username
")->fetchAll();

// Build query for assigned tickets
$query = "
    SELECT 
        t.id,
        t.subject,
        t.created_at,
        t.assigned_to,
        u.username as creator,
        a.username as assigned_username,
        CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) as assigned_name,
        p.name as priority,
        s.name as status,
        c.name as category
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN statuses s ON t.status_id = s.id
    JOIN categories c ON t.category_id = c.id
    WHERE t.assigned_to IS NOT NULL
";

$params = [];

if ($technician_filter) {
    $query .= " AND t.assigned_to = ?";
    $params[] = $technician_filter;
}

if ($status_filter) {
    $query .= " AND t.status_id = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$assigned_tickets = $stmt->fetchAll();

// Get statuses for filter
$statuses = $db->query("SELECT id, name FROM statuses ORDER BY name")->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Tickets - <?php echo APP_NAME; ?></title>
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
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <h1 style="margin-bottom: 2rem;">Assigned Tickets by Technician</h1>
            
            <!-- Technician Stats -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <?php foreach ($technicians as $tech): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $tech['ticket_count']; ?></div>
                        <div class="stat-label">
                            <?php echo htmlspecialchars($tech['username']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Filters -->
            <div class="card mb-2">
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <select name="technician" class="form-control" style="width: 200px;">
                            <option value="">All Technicians</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo $technician_filter == $tech['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="form-control" style="width: 150px;">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo $status_filter == $status['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="technicians.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <!-- Assigned Tickets Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Assigned Tickets (<?php echo count($assigned_tickets); ?>)</h2>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Creator</th>
                            <th>Assigned To</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assigned_tickets)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No assigned tickets found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assigned_tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo $ticket['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['creator']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['assigned_username']); ?></td>
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
                                    <td><?php echo timeAgo($ticket['created_at']); ?></td>
                                    <td>
                                        <button 
                                            class="btn btn-secondary btn-sm" 
                                            onclick='openReassignModal(<?php echo $ticket["id"]; ?>, <?php echo $ticket["assigned_to"]; ?>)'
                                        >
                                            Reassign
                                        </button>
                                        <button 
                                            class="btn btn-danger btn-sm" 
                                            onclick="unassignTicket(<?php echo $ticket['id']; ?>)"
                                        >
                                            Unassign
                                        </button>
                                        <a href="ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">
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
    
    <!-- Reassign Modal -->
    <div id="reassignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reassign Ticket</h2>
                <button class="modal-close" onclick="closeModal('reassignModal')">&times;</button>
            </div>
            <form method="POST" id="reassignForm">
                <input type="hidden" name="action" value="reassign">
                <input type="hidden" name="ticket_id" id="reassign_ticket_id">
                
                <div class="form-group">
                    <label class="form-label">Reassign To</label>
                    <select name="new_assigned_to" id="reassign_to" class="form-control" required>
                        <option value="">Select Technician</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>">
                                <?php echo htmlspecialchars($tech['username']); ?>
                                <?php if ($tech['full_name']): ?>
                                    (<?php echo htmlspecialchars(trim($tech['full_name'])); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Reassign</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reassignModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Unassign Form -->
    <form method="POST" id="unassignForm" style="display: none;">
        <input type="hidden" name="action" value="unassign">
        <input type="hidden" name="ticket_id" id="unassign_ticket_id">
    </form>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function openReassignModal(ticketId, currentAssignedTo) {
            document.getElementById('reassign_ticket_id').value = ticketId;
            document.getElementById('reassign_to').value = '';
            // Remove current assignee from selection
            const select = document.getElementById('reassign_to');
            for (let option of select.options) {
                if (option.value == currentAssignedTo) {
                    option.disabled = true;
                    option.text += ' (Current)';
                } else {
                    option.disabled = false;
                }
            }
            openModal('reassignModal');
        }
        
        function unassignTicket(ticketId) {
            if (confirm('Are you sure you want to unassign this ticket?')) {
                document.getElementById('unassign_ticket_id').value = ticketId;
                document.getElementById('unassignForm').submit();
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
