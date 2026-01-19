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
$user_id = getCurrentUserId();

// Handle claim action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'claim') {
        $ticket_id = (int)$_POST['ticket_id'];
        
        try {
            // Check if ticket is unassigned
            $stmt = $db->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch();
            
            if ($ticket && $ticket['assigned_to'] === null) {
                $stmt = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
                $stmt->execute([$user_id, $ticket_id]);
                
                // Log history
                $stmt = $db->prepare("
                    INSERT INTO ticket_history (ticket_id, user_id, action, new_value)
                    VALUES (?, ?, 'assigned', ?)
                ");
                $stmt->execute([$ticket_id, $user_id, $user_id]);
                
                redirect('tickets.php', 'Ticket claimed successfully');
            } else {
                redirect('tickets.php', 'Ticket is already assigned', 'error');
            }
        } catch (PDOException $e) {
            redirect('tickets.php', 'Failed to claim ticket', 'error');
        }
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$category_filter = $_GET['category'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build query
$query = "
    SELECT 
        t.id,
        t.subject,
        t.created_at,
        t.updated_at,
        t.assigned_to,
        u.username as creator,
        CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) as assigned_name,
        a.username as assigned_username,
        p.name as priority,
        s.name as status,
        c.name as category
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN statuses s ON t.status_id = s.id
    JOIN categories c ON t.category_id = c.id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (t.subject LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $query .= " AND t.status_id = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $query .= " AND t.priority_id = ?";
    $params[] = $priority_filter;
}

if ($category_filter) {
    $query .= " AND t.category_id = ?";
    $params[] = $category_filter;
}

if ($assigned_filter === 'unassigned') {
    $query .= " AND t.assigned_to IS NULL";
} elseif ($assigned_filter === 'assigned') {
    $query .= " AND t.assigned_to IS NOT NULL";
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM ($query) as subquery";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];

// Pagination
$pagination = paginate($total, ITEMS_PER_PAGE, $page);

// Get tickets
$query .= " ORDER BY t.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Get filter options
$priorities = $db->query("SELECT id, name FROM priorities ORDER BY level")->fetchAll();
$statuses = $db->query("SELECT id, name FROM statuses ORDER BY name")->fetchAll();
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Tickets - <?php echo APP_NAME; ?></title>
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
            
            <h1 style="margin-bottom: 2rem;">All Tickets</h1>
            
            <!-- Filters -->
            <div class="card mb-2">
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control" 
                            placeholder="Search tickets..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            style="width: 200px;"
                        >
                    </div>
                    
                    <div class="filter-group">
                        <select name="assigned" class="form-control" style="width: 150px;">
                            <option value="">All Tickets</option>
                            <option value="unassigned" <?php echo $assigned_filter === 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                            <option value="assigned" <?php echo $assigned_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="form-control" style="width: 140px;">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo $status_filter == $status['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="priority" class="form-control" style="width: 140px;">
                            <option value="">All Priorities</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?php echo $priority['id']; ?>" <?php echo $priority_filter == $priority['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($priority['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="category" class="form-control" style="width: 140px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="tickets.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <!-- Tickets Table -->
            <div class="card">
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
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No tickets found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo $ticket['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['creator']); ?></td>
                                    <td>
                                        <?php if ($ticket['assigned_to']): ?>
                                            <?php echo htmlspecialchars($ticket['assigned_username']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
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
                                        <?php if ($ticket['assigned_to'] === null): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="claim">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Claim
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-secondary btn-sm">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination">
                        <button 
                            class="btn" 
                            <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>
                            onclick="location.href='?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&category=<?php echo $category_filter; ?>&assigned=<?php echo $assigned_filter; ?>'"
                        >
                            Previous
                        </button>
                        
                        <span class="text-muted">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                        </span>
                        
                        <button 
                            class="btn" 
                            <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>
                            onclick="location.href='?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&category=<?php echo $category_filter; ?>&assigned=<?php echo $assigned_filter; ?>'"
                        >
                            Next
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
