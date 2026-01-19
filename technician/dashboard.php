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

// Get statistics
$stats = [];

// Assigned tickets
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets 
    WHERE assigned_to = ?
");
$stmt->execute([$user_id]);
$stats['assigned'] = $stmt->fetch()['count'];

// Open assigned tickets
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets t
    JOIN statuses s ON t.status_id = s.id
    WHERE t.assigned_to = ? AND s.type = 'open'
");
$stmt->execute([$user_id]);
$stats['open'] = $stmt->fetch()['count'];

// In progress tickets
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets t
    JOIN statuses s ON t.status_id = s.id
    WHERE t.assigned_to = ? AND s.type = 'in_progress'
");
$stmt->execute([$user_id]);
$stats['in_progress'] = $stmt->fetch()['count'];

// Resolved this week
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM tickets t
    JOIN statuses s ON t.status_id = s.id
    WHERE t.assigned_to = ? AND s.type = 'closed' AND t.closed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$stats['resolved_week'] = $stmt->fetch()['count'];

// My tickets
$stmt = $db->prepare("
    SELECT 
        t.id,
        t.subject,
        t.created_at,
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
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$my_tickets = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
            
            <h1 style="margin-bottom: 2rem;">My Dashboard</h1>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['assigned']; ?></div>
                    <div class="stat-label">Assigned Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['open']; ?></div>
                    <div class="stat-label">Open</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['resolved_week']; ?></div>
                    <div class="stat-label">Resolved This Week</div>
                </div>
            </div>
            
            <!-- My Tickets -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Assigned Tickets</h2>
                    <a href="tickets.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_tickets)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No tickets assigned</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($my_tickets as $ticket): ?>
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
</body>
</html>
