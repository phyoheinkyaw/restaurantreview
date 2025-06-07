<?php
// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters - using the same as the main application
require_once 'includes/config.php';

// Connect to the database
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all tables in the database
$tables = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

// Get the currently selected table
$current_table = isset($_GET['table']) && in_array($_GET['table'], $tables) ? $_GET['table'] : $tables[0] ?? null;

// Get table structure
$columns = [];
$primary_key = null;
$foreign_keys = [];

if ($current_table) {
    // Get column information
    $stmt = $db->query("DESCRIBE `$current_table`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find primary key
    foreach ($columns as $column) {
        if ($column['Key'] === 'PRI') {
            $primary_key = $column['Field'];
            break;
        }
    }
    
    // Try to find foreign keys (this is a simplified approach)
    $stmt = $db->query("SELECT 
        TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        REFERENCED_TABLE_SCHEMA = '" . DB_NAME . "' AND
        TABLE_NAME = '$current_table'");
    
    $foreign_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get current page for pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

// Get search term if any
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get column to sort by
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : $primary_key;
$sort_direction = isset($_GET['direction']) && $_GET['direction'] === 'desc' ? 'DESC' : 'ASC';

// Fetch data from the current table with pagination
$data = [];
$total_rows = 0;

if ($current_table) {
    // Count total rows (with search if applicable)
    $count_sql = "SELECT COUNT(*) FROM `$current_table`";
    $params = [];
    
    if (!empty($search)) {
        $search_conditions = [];
        foreach ($columns as $column) {
            $search_conditions[] = "`{$column['Field']}` LIKE ?";
            $params[] = "%$search%";
        }
        if (!empty($search_conditions)) {
            $count_sql .= " WHERE " . implode(" OR ", $search_conditions);
        }
    }
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_rows = $stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = ceil($total_rows / $per_page);
    $page = min($page, max(1, $total_pages));
    $offset = ($page - 1) * $per_page;
    
    // Fetch data
    $sql = "SELECT * FROM `$current_table`";
    
    // Add search condition if search term is provided
    if (!empty($search)) {
        $search_conditions = [];
        foreach ($columns as $column) {
            $search_conditions[] = "`{$column['Field']}` LIKE ?";
        }
        if (!empty($search_conditions)) {
            $sql .= " WHERE " . implode(" OR ", $search_conditions);
        }
    }
    
    // Add sorting
    if ($sort_column) {
        $sql .= " ORDER BY `$sort_column` $sort_direction";
    }
    
    // Add pagination
    $sql .= " LIMIT $per_page OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    
    // Bind search parameters if needed
    if (!empty($search)) {
        $params = [];
        foreach ($columns as $column) {
            $params[] = "%$search%";
        }
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to format cell value for display
function formatValue($value, $columns, $column_name) {
    if ($value === null) {
        return '<span class="text-muted">NULL</span>';
    }
    
    // Find column type
    $column_type = '';
    foreach ($columns as $col) {
        if ($col['Field'] === $column_name) {
            $column_type = $col['Type'];
            break;
        }
    }
    
    // Format based on type
    if (strpos($column_type, 'json') !== false) {
        // Format JSON data
        $json_data = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return '<pre class="json-data">' . htmlspecialchars(json_encode($json_data, JSON_PRETTY_PRINT)) . '</pre>';
        }
    } elseif (strpos($column_type, 'text') !== false || strlen($value) > 100) {
        // Truncate long text
        return '<span title="' . htmlspecialchars($value) . '">' . 
               htmlspecialchars(substr($value, 0, 100)) . 
               (strlen($value) > 100 ? '...' : '') . '</span>';
    } elseif (strpos($column_name, 'image') !== false || strpos($column_name, 'photo') !== false) {
        // Try to display images
        if (!empty($value)) {
            return '<span title="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</span>';
        }
    } elseif (strpos($column_type, 'datetime') !== false || strpos($column_type, 'timestamp') !== false) {
        // Format dates
        return date('Y-m-d H:i:s', strtotime($value));
    }
    
    return htmlspecialchars($value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Explorer - <?php echo DB_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            padding-top: 56px;
            font-size: 0.875rem;
        }
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            overflow-y: auto;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .table-container {
            overflow-x: auto;
        }
        .main-content {
            margin-left: 330px;
            padding: 20px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                top: 0;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
        .table th, .table td {
            white-space: nowrap;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .json-data {
            max-height: 150px;
            overflow: auto;
            white-space: pre-wrap;
            font-size: 0.75rem;
        }
        .pagination-container {
            overflow-x: auto;
        }
        .column-primary {
            background-color: rgba(0,123,255,0.1);
        }
        .column-foreign {
            background-color: rgba(255,193,7,0.1);
        }
        .table-info-panel {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="db_explorer.php">DB Explorer - <?php echo htmlspecialchars(DB_NAME); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <form class="d-flex ms-auto" method="GET" action="">
                    <input type="hidden" name="table" value="<?php echo htmlspecialchars($current_table); ?>">
                    <input class="form-control me-2" type="search" name="search" placeholder="Search table..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-light" type="submit">Search</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2 bg-light d-md-block">
        <div class="sidebar-sticky">
            <div class="p-3">
                <h6 class="d-flex justify-content-between align-items-center px-2">
                    <span>Tables</span>
                    <span class="badge bg-primary rounded-pill"><?php echo count($tables); ?></span>
                </h6>
                <div class="list-group">
                    <?php foreach ($tables as $table): ?>
                        <a href="?table=<?php echo urlencode($table); ?>" 
                           class="list-group-item list-group-item-action <?php echo $table === $current_table ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($table); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($current_table): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><?php echo htmlspecialchars($current_table); ?></h2>
                <div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Rows per page: <?php echo $per_page; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ([10, 20, 50, 100, 500] as $option): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $per_page === $option ? 'active' : ''; ?>" 
                                       href="?table=<?php echo urlencode($current_table); ?>&per_page=<?php echo $option; ?>&page=1&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_column); ?>&direction=<?php echo $sort_direction; ?>">
                                        <?php echo $option; ?> rows
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="table-info-panel">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Total Rows:</strong> <?php echo $total_rows; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Primary Key:</strong> <?php echo $primary_key ?? 'None'; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Columns:</strong> <?php echo count($columns); ?>
                    </div>
                </div>
                <?php if (!empty($foreign_keys)): ?>
                <div class="mt-2">
                    <strong>Foreign Keys:</strong>
                    <ul class="mb-0">
                        <?php foreach ($foreign_keys as $fk): ?>
                            <li><?php echo "{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}"; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($data)): ?>
                <div class="table-container">
                    <table class="table table-striped table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <th class="<?php echo $column['Field'] === $primary_key ? 'column-primary' : ''; ?> 
                                               <?php foreach ($foreign_keys as $fk) { if ($column['Field'] === $fk['COLUMN_NAME']) { echo 'column-foreign'; break; } } ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($column['Field']); ?></span>
                                            <div>
                                                <a href="?table=<?php echo urlencode($current_table); ?>&sort=<?php echo urlencode($column['Field']); ?>&direction=asc&page=<?php echo $page; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>" 
                                                   class="text-white <?php echo ($sort_column === $column['Field'] && $sort_direction === 'ASC') ? 'fw-bold' : 'text-white-50'; ?>">
                                                    <i class="fas fa-sort-up"></i>
                                                </a>
                                                <a href="?table=<?php echo urlencode($current_table); ?>&sort=<?php echo urlencode($column['Field']); ?>&direction=desc&page=<?php echo $page; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>" 
                                                   class="text-white <?php echo ($sort_column === $column['Field'] && $sort_direction === 'DESC') ? 'fw-bold' : 'text-white-50'; ?>">
                                                    <i class="fas fa-sort-down"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <small class="text-white-50"><?php echo $column['Type']; ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <td class="<?php echo $column['Field'] === $primary_key ? 'column-primary' : ''; ?> 
                                                   <?php foreach ($foreign_keys as $fk) { if ($column['Field'] === $fk['COLUMN_NAME']) { echo 'column-foreign'; break; } } ?>">
                                            <?php
                                            $value = $row[$column['Field']] ?? null;
                                            echo formatValue($value, $columns, $column['Field']);
                                            
                                            // Add link to foreign table if this is a foreign key
                                            foreach ($foreign_keys as $fk) {
                                                if ($column['Field'] === $fk['COLUMN_NAME'] && !empty($value)) {
                                                    echo ' <a href="?table=' . urlencode($fk['REFERENCED_TABLE_NAME']) . 
                                                         '&search=' . urlencode($value) . 
                                                         '&sort=' . urlencode($fk['REFERENCED_COLUMN_NAME']) . 
                                                         '" class="badge bg-info text-dark text-decoration-none" title="View in ' . 
                                                         htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . '">→</a>';
                                                    break;
                                                }
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container my-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?table=<?php echo urlencode($current_table); ?>&page=1&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_column); ?>&direction=<?php echo $sort_direction; ?>">First</a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?table=<?php echo urlencode($current_table); ?>&page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_column); ?>&direction=<?php echo $sort_direction; ?>">Previous</a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?table=<?php echo urlencode($current_table); ?>&page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_column); ?>&direction=<?php echo $sort_direction; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?table=<?php echo urlencode($current_table); ?>&page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_column); ?>&direction=<?php echo $sort_direction; ?>">Next</a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?table=<?php echo urlencode($current_table); ?>&page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_column); ?>&direction=<?php echo $sort_direction; ?>">Last</a>
                                </li>
                            </ul>
                        </nav>
                        <div class="text-muted">
                            Showing <?php echo min(($page - 1) * $per_page + 1, $total_rows); ?> to 
                            <?php echo min($page * $per_page, $total_rows); ?> of 
                            <?php echo $total_rows; ?> entries
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-info">No data found<?php echo !empty($search) ? ' for search term "' . htmlspecialchars($search) . '"' : ''; ?> in table <?php echo htmlspecialchars($current_table); ?>.</div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-warning">No tables found in the database.</div>
        <?php endif; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Code for showing table details
        $('.table-link').click(function(e) {
    </script>
</body>
</html> 