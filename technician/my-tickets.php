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

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$category_filter = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build query
$query = "
    SELECT 
        t.id,
        t.subject,
        t.created_at,
        t.updated_at,
        u.username as creator,
        p.name as priority,
        s.name as status,
        c.name as category
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN statuses s ON t.status_id = s.id
    JOIN categories c ON t.category_id = c.id
    WHERE t.assigned_to = ?
";

$params = [$user_id];

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
            
            <h1 style="margin-bottom: 2rem;">My Assigned Tickets</h1>
            
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
                            style="width: 250px;"
                        >
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
                    
                    <div class="filter-group">
                        <select name="priority" class="form-control" style="width: 150px;">
                            <option value="">All Priorities</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?php echo $priority['id']; ?>" <?php echo $priority_filter == $priority['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($priority['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="category" class="form-control" style="width: 150px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="my-tickets.php" class="btn btn-secondary">Reset</a>
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
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No tickets assigned to you</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo $ticket['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['creator']); ?></td>
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
                                    <td><?php echo timeAgo($ticket['updated_at']); ?></td>
                                    <td>
                                        <a href="ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">
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
                            onclick="location.href='?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&category=<?php echo $category_filter; ?>'"
                        >
                            Previous
                        </button>
                        
                        <span class="text-muted">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                        </span>
                        
                        <button 
                            class="btn" 
                            <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>
                            onclick="location.href='?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&category=<?php echo $category_filter; ?>'"
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
