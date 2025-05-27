<?php
// Include header
include 'includes/header.php';

// Process actions (delete, change role)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = (int)$_GET['id'];
    
    // Prevent actions on own account
    if ($user_id == $_SESSION['user_id']) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                You cannot perform this action on your own account.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    } else {
        if ($action === 'delete') {
            // Delete user
            $sql = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        User deleted successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            } else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Failed to delete user.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            }
            $stmt->close();
        } elseif ($action === 'change_role' && isset($_GET['role'])) {
            $role = $_GET['role'];
            
            // Validate role
            if (!in_array($role, ['user', 'owner', 'admin'])) {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Invalid role specified.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            } else {
                // Change user role
                $sql = "UPDATE users SET role = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $role, $user_id);
                
                if ($stmt->execute()) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            User role updated successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            Failed to update user role.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
                }
                $stmt->close();
            }
        }
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$role = isset($_GET['role']) ? $conn->real_escape_string($_GET['role']) : '';

// Build query with filters
$query = "SELECT * FROM users WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

if (!empty($role)) {
    $query .= " AND role = ?";
    $params[] = $role;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user statistics
$stats = [
    'total' => count($users),
    'admin' => 0,
    'owner' => 0,
    'user' => 0
];

foreach ($users as $user) {
    $stats[$user['role']]++;
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Manage Users</h1>
    <a href="add_user.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> Add New User
    </a>
</div>

<!-- User Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="dashboard-card">
            <div class="card-icon bg-primary-light text-primary mb-3">
                <i class="fas fa-users"></i>
            </div>
            <h6 class="card-title">Total Users</h6>
            <h2 class="card-value"><?php echo $stats['total']; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card">
            <div class="card-icon bg-danger-light text-danger mb-3">
                <i class="fas fa-user-shield"></i>
            </div>
            <h6 class="card-title">Admins</h6>
            <h2 class="card-value"><?php echo $stats['admin']; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card">
            <div class="card-icon bg-info-light text-info mb-3">
                <i class="fas fa-store"></i>
            </div>
            <h6 class="card-title">Restaurant Owners</h6>
            <h2 class="card-value"><?php echo $stats['owner']; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card">
            <div class="card-icon bg-secondary-light text-secondary mb-3">
                <i class="fas fa-user"></i>
            </div>
            <h6 class="card-title">Regular Users</h6>
            <h2 class="card-value"><?php echo $stats['user']; ?></h2>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search by username, email, name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="owner" <?php echo ($role === 'owner') ? 'selected' : ''; ?>>Restaurant Owner</option>
                    <option value="user" <?php echo ($role === 'user') ? 'selected' : ''; ?>>Regular User</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="data-table">
    <div class="data-table-header d-flex justify-content-between align-items-center">
        <h5 class="data-table-title">Users</h5>
        <span class="badge bg-primary"><?php echo count($users); ?> users</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Join Date</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="text-center">No users found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="../<?php echo $user['profile_image']; ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="rounded-circle me-2" width="40" height="40">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle me-2" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small class="text-muted">
                                    <?php 
                                    if (!empty($user['first_name']) || !empty($user['last_name'])) {
                                        echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']));
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo ($user['role'] == 'admin') ? 'danger' : (($user['role'] == 'owner') ? 'primary' : 'secondary'); ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="edit_user.php?id=<?php echo $user['user_id']; ?>">
                                    <i class="fas fa-edit me-2 text-primary"></i> Edit
                                </a></li>
                                <li><a class="dropdown-item" href="view_user.php?id=<?php echo $user['user_id']; ?>">
                                    <i class="fas fa-eye me-2 text-info"></i> View Profile
                                </a></li>
                                
                                <!-- Change Role Submenu -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-user-tag me-2 text-warning"></i> Change Role
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($user['role'] != 'admin'): ?>
                                        <li><a class="dropdown-item" href="?action=change_role&id=<?php echo $user['user_id']; ?>&role=admin">
                                            <i class="fas fa-user-shield me-2 text-danger"></i> Make Admin
                                        </a></li>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['role'] != 'owner'): ?>
                                        <li><a class="dropdown-item" href="?action=change_role&id=<?php echo $user['user_id']; ?>&role=owner">
                                            <i class="fas fa-store me-2 text-primary"></i> Make Owner
                                        </a></li>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['role'] != 'user'): ?>
                                        <li><a class="dropdown-item" href="?action=change_role&id=<?php echo $user['user_id']; ?>&role=user">
                                            <i class="fas fa-user me-2 text-secondary"></i> Make Regular User
                                        </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </li>
                                
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $user['user_id']; ?>" data-confirm="Are you sure you want to delete this user? This action cannot be undone.">
                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                </a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Dropdown submenu styles */
.dropdown-submenu {
    position: relative;
}

.dropdown-submenu .dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -1px;
}

@media (max-width: 768px) {
    .dropdown-submenu .dropdown-menu {
        left: 0;
        top: 100%;
    }
}
</style>

<script>
// The DataTable initialization is now handled centrally in admin.js
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 