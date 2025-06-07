<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Check if message ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: contact_messages.php');
    exit;
}

$message_id = intval($_GET['id']);

try {
    $db = getDB();
    
    // Get message details
    $stmt = $db->prepare("SELECT * FROM contact_messages WHERE message_id = :id");
    $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
    $stmt->execute();
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        // Message not found
        header('Location: contact_messages.php?error=message_not_found');
        exit;
    }
    
    // Mark message as read if it's unread
    if (!$message['is_read']) {
        $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE message_id = :id");
        $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
        $stmt->execute();
        $message['is_read'] = 1;
    }
} catch (PDOException $e) {
    error_log("Error getting message details: " . $e->getMessage());
    header('Location: contact_messages.php?error=database_error');
    exit;
}

// Include admin header
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="contact_messages.php">Contact Messages</a></li>
            <li class="breadcrumb-item active">View Message</li>
        </ol>
    </nav>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Message Details</h5>
            <div>
                <a href="contact_messages.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Messages
                </a>
                <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo htmlspecialchars($message['subject']); ?>" class="btn btn-sm btn-primary ms-2">
                    <i class="fas fa-reply me-1"></i> Reply
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <!-- Message Info -->
                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($message['subject']); ?></h4>
                        <div class="text-muted mb-3">
                            <?php 
                            $date = new DateTime($message['created_at']);
                            echo $date->format('F j, Y g:i A'); 
                            ?>
                            <span class="ms-2 badge bg-<?php echo $message['is_read'] ? 'success' : 'danger'; ?>">
                                <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Message Content -->
                    <div class="message-content p-4 bg-light rounded mb-4">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Sender Info -->
                    <div class="card border-0 bg-light">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Sender Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="text-muted d-block">Name:</label>
                                <div class="fw-semibold"><?php echo htmlspecialchars($message['name']); ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="text-muted d-block">Email:</label>
                                <div class="fw-semibold">
                                    <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>">
                                        <?php echo htmlspecialchars($message['email']); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-muted d-block">Message Received:</label>
                                <div class="fw-semibold"><?php echo $date->format('F j, Y g:i A'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-4">
                        <div class="d-grid gap-2">
                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo htmlspecialchars($message['subject']); ?>" class="btn btn-primary">
                                <i class="fas fa-reply me-2"></i> Reply to Message
                            </a>
                            <a href="contact_messages.php?action=delete&id=<?php echo $message['message_id']; ?>" class="btn btn-outline-danger" data-confirm="Are you sure you want to delete this message?">
                                <i class="fas fa-trash me-2"></i> Delete Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.message-content {
    min-height: 200px;
    white-space: pre-line;
    border-left: 4px solid var(--bs-primary);
}
</style>

<?php include 'includes/footer.php'; ?> 