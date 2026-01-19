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

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $icon = sanitize($_POST['icon']);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO categories (name, description, icon) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $icon]);
            redirect('categories.php', 'Category created successfully');
        } catch (PDOException $e) {
            $error = 'Failed to create category: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $category_id = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $icon = sanitize($_POST['icon']);
        
        try {
            $stmt = $db->prepare("
                UPDATE categories 
                SET name = ?, description = ?, icon = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $icon, $category_id]);
            redirect('categories.php', 'Category updated successfully');
        } catch (PDOException $e) {
            $error = 'Failed to update category: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $category_id = (int)$_POST['category_id'];
        
        // Check if category is used
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $error = 'Cannot delete category: it is used by ' . $count . ' ticket(s)';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                redirect('categories.php', 'Category deleted successfully');
            } catch (PDOException $e) {
                $error = 'Failed to delete category: ' . $e->getMessage();
            }
        }
    }
}

// Get categories
$stmt = $db->query("
    SELECT 
        c.id,
        c.name,
        c.description,
        c.icon,
        c.created_at,
        COUNT(t.id) as ticket_count
    FROM categories c
    LEFT JOIN tickets t ON c.id = t.category_id
    GROUP BY c.id
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .icon-item {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--surface-light);
            border: 2px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .icon-item:hover {
            background: var(--border);
            border-color: var(--primary);
        }
        
        .icon-item.active {
            background: var(--primary);
            border-color: var(--primary-light);
        }
    </style>
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
            
            <div class="d-flex justify-between align-center mb-3">
                <h1>Categories Management</h1>
                <button class="btn btn-primary" onclick="openModal('createModal')">
                    Create Category
                </button>
            </div>
            
            <!-- Categories Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Icon</th>
                            <th>Tickets</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No categories found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>#<?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td><?php echo htmlspecialchars($category['icon']); ?></td>
                                    <td><?php echo $category['ticket_count']; ?></td>
                                    <td><?php echo formatDate($category['created_at']); ?></td>
                                    <td>
                                        <button 
                                            class="btn btn-secondary btn-sm" 
                                            onclick='editCategory(<?php echo json_encode($category); ?>)'
                                        >
                                            Edit
                                        </button>
                                        <button 
                                            class="btn btn-danger btn-sm" 
                                            onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['ticket_count']; ?>)"
                                            <?php echo $category['ticket_count'] > 0 ? 'disabled' : ''; ?>
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Create Category Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Category</h2>
                <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <input type="hidden" name="icon" id="create_icon" value="help-circle">
                    <div class="icon-grid">
                        <div class="icon-item active" data-icon="help-circle" title="Help">❓</div>
                        <div class="icon-item" data-icon="tool" title="Tool">🔧</div>
                        <div class="icon-item" data-icon="star" title="Star">⭐</div>
                        <div class="icon-item" data-icon="alert-circle" title="Alert">⚠️</div>
                        <div class="icon-item" data-icon="book" title="Book">📚</div>
                        <div class="icon-item" data-icon="code" title="Code">💻</div>
                        <div class="icon-item" data-icon="settings" title="Settings">⚙️</div>
                        <div class="icon-item" data-icon="bug" title="Bug">🐛</div>
                        <div class="icon-item" data-icon="zap" title="Feature">⚡</div>
                        <div class="icon-item" data-icon="shield" title="Security">🛡️</div>
                        <div class="icon-item" data-icon="database" title="Database">💾</div>
                        <div class="icon-item" data-icon="mail" title="Mail">📧</div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <input type="hidden" name="icon" id="edit_icon_value">
                    <div class="icon-grid" id="edit_icon_grid">
                        <div class="icon-item" data-icon="help-circle" title="Help">❓</div>
                        <div class="icon-item" data-icon="tool" title="Tool">🔧</div>
                        <div class="icon-item" data-icon="star" title="Star">⭐</div>
                        <div class="icon-item" data-icon="alert-circle" title="Alert">⚠️</div>
                        <div class="icon-item" data-icon="book" title="Book">📚</div>
                        <div class="icon-item" data-icon="code" title="Code">💻</div>
                        <div class="icon-item" data-icon="settings" title="Settings">⚙️</div>
                        <div class="icon-item" data-icon="bug" title="Bug">🐛</div>
                        <div class="icon-item" data-icon="zap" title="Feature">⚡</div>
                        <div class="icon-item" data-icon="shield" title="Security">🛡️</div>
                        <div class="icon-item" data-icon="database" title="Database">💾</div>
                        <div class="icon-item" data-icon="mail" title="Mail">📧</div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="category_id" id="delete_category_id">
    </form>
    
    <script>
        // Icon selection handling
        document.querySelectorAll('.icon-item').forEach(item => {
            item.addEventListener('click', function() {
                const parent = this.parentElement;
                parent.querySelectorAll('.icon-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Update hidden input
                const icon = this.dataset.icon;
                if (parent.id === 'edit_icon_grid') {
                    document.getElementById('edit_icon_value').value = icon;
                } else {
                    document.getElementById('create_icon').value = icon;
                }
            });
        });
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_icon_value').value = category.icon || '';
            
            // Set active icon
            const grid = document.getElementById('edit_icon_grid');
            grid.querySelectorAll('.icon-item').forEach(item => {
                if (item.dataset.icon === category.icon) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
            
            openModal('editModal');
        }
        
        function deleteCategory(categoryId, categoryName, ticketCount) {
            if (ticketCount > 0) {
                alert('Cannot delete category "' + categoryName + '": it is used by ' + ticketCount + ' ticket(s)');
                return;
            }
            
            if (confirm('Are you sure you want to delete category "' + categoryName + '"?')) {
                document.getElementById('delete_category_id').value = categoryId;
                document.getElementById('deleteForm').submit();
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
