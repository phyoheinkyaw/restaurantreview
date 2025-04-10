<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Database - Restaurant Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container py-5">
        <h1 class="mb-4">Database Creation Status</h1>
        
        <?php
        // Database connection settings
        $host = 'localhost';
        $username = 'root';
        $password = 'root'; 
        $port = '3308';

        try {
            // Create connection without selecting a database
            $pdo = new PDO("mysql:host=$host:$port", $username, $password);
            
            // Set PDO error mode to exception
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo '<div class="alert alert-success">Connected successfully</div>';

            // Read the SQL file
            $sql = file_get_contents('database.sql');

            // Add DROP DATABASE statement at the beginning
            $sql = "DROP DATABASE IF EXISTS restaurant_review;\n" . $sql;

            // Split SQL by semicolon to execute multiple queries
            $queries = array_filter(array_map('trim', explode(';', $sql)));

            // Execute each query
            foreach ($queries as $query) {
                if (!empty($query)) {
                    try {
                        $pdo->exec($query);
                        echo '<div class="alert alert-success">Executed: ' . substr($query, 0, 50) . '...</div>';
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">';
                        echo 'Error executing query: ' . $e->getMessage() . '<br>';
                        echo 'Query: ' . substr($query, 0, 100) . '...<br>';
                        echo '</div>';
                    }
                }
            }
            
            echo '<div class="alert alert-success">Database created successfully with all tables and sample data!</div>';

        } catch(PDOException $e) {
            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }

        // Close connection
        $pdo = null;
        ?>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">Go to Homepage</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 