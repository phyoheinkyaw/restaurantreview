<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);

// Handle mark as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE message_id = :id");
        $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header('Location: contact_messages.php?success=1');
        exit;
    } catch (PDOException $e) {
        error_log("Error marking message as read: " . $e->getMessage());
        $error = "Failed to update the message.";
    }
}

// Handle delete message
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM contact_messages WHERE message_id = :id");
        $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header('Location: contact_messages.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        error_log("Error deleting message: " . $e->getMessage());
        $error = "Failed to delete the message.";
    }
}

// Handle mark all as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE is_read = 0");
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header('Location: contact_messages.php?all_read=1');
        exit;
    } catch (PDOException $e) {
        error_log("Error marking all messages as read: " . $e->getMessage());
        $error = "Failed to update messages.";
    }
}

// Get contact messages with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Filter options
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "";

if ($filter === 'unread') {
    $where_clause = "WHERE is_read = 0";
} else if ($filter === 'read') {
    $where_clause = "WHERE is_read = 1";
}

try {
    $db = getDB();
    
    // Count total messages
    $count_query = "SELECT COUNT(*) FROM contact_messages $where_clause";
    $stmt = $db->query($count_query);
    $total = $stmt->fetchColumn();
    
    // Get messages for current page
    $query = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log("Error fetching contact messages: " . $e->getMessage());
    $error = "Failed to retrieve messages.";
    $messages = [];
    $total_pages = 0;
}

// Include admin header which has proper layout and session management
include 'includes/header.php';

// Calculate stats
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages");
    $total_messages = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
    $unread_messages = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 1");
    $read_messages = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error calculating message stats: " . $e->getMessage());
    $total_messages = 0;
    $unread_messages = 0;
    $read_messages = 0;
}
?>

<!-- Main content - No need for a full HTML structure as it's provided by the header -->
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h1 class="h3 mb-0">Contact Messages</h1>
                <div>
                    <?php if ($unread_messages > 0): ?>
                    <a href="contact_messages.php?action=mark_all_read" class="btn btn-success">
                        <i class="fas fa-check-double me-1"></i> Mark All as Read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">Total Messages</h6>
                            <h2 class="mt-2 mb-0"><?php echo $total_messages; ?></h2>
                        </div>
                        <div class="icon-box bg-primary-light rounded">
                            <i class="fas fa-envelope text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">Unread Messages</h6>
                            <h2 class="mt-2 mb-0"><?php echo $unread_messages; ?></h2>
                        </div>
                        <div class="icon-box bg-danger-light rounded">
                            <i class="fas fa-envelope-open text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">Read Messages</h6>
                            <h2 class="mt-2 mb-0"><?php echo $read_messages; ?></h2>
                        </div>
                        <div class="icon-box bg-success-light rounded">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Message has been marked as read.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-trash me-2"></i> Message has been deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['all_read'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> All messages have been marked as read.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> 
            <?php 
            switch($_GET['error']) {
                case 'message_not_found':
                    echo 'Message not found.';
                    break;
                case 'database_error':
                    echo 'A database error occurred. Please try again.';
                    break;
                default:
                    echo 'An error occurred.';
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filter options -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <h5 class="mb-0">Filter Messages</h5>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100">
                        <a href="contact_messages.php" class="btn btn-outline-primary <?php echo ($filter === 'all') ? 'active' : ''; ?>">
                            All Messages
                        </a>
                        <a href="contact_messages.php?filter=unread" class="btn btn-outline-primary <?php echo ($filter === 'unread') ? 'active' : ''; ?>">
                            Unread
                        </a>
                        <a href="contact_messages.php?filter=read" class="btn btn-outline-primary <?php echo ($filter === 'read') ? 'active' : ''; ?>">
                            Read
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="card mb-4">
        <div class="card-body">
            <?php if (count($messages) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr class="<?php echo $message['is_read'] ? '' : 'fw-bold'; ?>">
                                    <td><?php echo htmlspecialchars($message['name']); ?></td>
                                    <td><?php echo htmlspecialchars($message['email']); ?></td>
                                    <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                    <td>
                                        <?php 
                                        $date = new DateTime($message['created_at']);
                                        echo $date->format('M d, Y g:i A');
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($message['is_read']): ?>
                                            <span class="badge bg-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_message.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!$message['is_read']): ?>
                                            <a href="contact_messages.php?action=mark_read&id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="contact_messages.php?action=delete&id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete this message?">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="contact_messages.php?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" tabindex="-1">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="contact_messages.php?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="contact_messages.php?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-envelope-open fa-4x text-muted"></i>
                    </div>
                    <h5>No Messages Found</h5>
                    <p class="text-muted">There are no <?php echo ($filter !== 'all') ? $filter . ' ' : ''; ?>messages at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.icon-box {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1);
}
.bg-success-light {
    background-color: rgba(25, 135, 84, 0.1);
}
.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}
</style>

<!-- Add JavaScript for AJAX mark as read functionality -->
<script>
$(document).ready(function() {
    // AJAX for mark as read functionality
    $('.mark-read-btn').on('click', function(e) {
        e.preventDefault();
        var messageId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: 'mark_message_read.php',
            type: 'POST',
            data: { message_id: messageId },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    row.removeClass('fw-bold');
                    row.find('.status-badge').removeClass('bg-danger').addClass('bg-success').text('Read');
                    row.find('.mark-read-btn').hide();
                    
                    // Update counters
                    var unreadCount = $('.unread-count');
                    var currentCount = parseInt(unreadCount.text());
                    if (currentCount > 1) {
                        unreadCount.text(currentCount - 1);
                    } else {
                        unreadCount.hide();
                    }
                }
            }
        });
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 