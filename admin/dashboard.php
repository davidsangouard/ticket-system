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

// Get statistics
$stats = [];

// Total tickets
$stmt = $db->query("SELECT COUNT(*) as count FROM tickets");
$stats['total_tickets'] = $stmt->fetch()['count'];

// Open tickets
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM tickets t 
    JOIN statuses s ON t.status_id = s.id 
    WHERE s.type = 'open'
");
$stats['open_tickets'] = $stmt->fetch()['count'];

// Total users
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch()['count'];

// Total technicians
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role IN ('technician', 'admin')");
$stats['total_technicians'] = $stmt->fetch()['count'];

// Recent tickets
$stmt = $db->query("
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
    ORDER BY t.created_at DESC
    LIMIT 10
");
$recent_tickets = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
            
            <h1 style="margin-bottom: 2rem;">Dashboard</h1>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['open_tickets']; ?></div>
                    <div class="stat-label">Open Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_technicians']; ?></div>
                    <div class="stat-label">Technicians</div>
                </div>
            </div>
            
            <!-- Recent Tickets -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Tickets</h2>
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
                        <?php if (empty($recent_tickets)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No tickets found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_tickets as $ticket): ?>
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
