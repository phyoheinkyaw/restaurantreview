<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Review Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container-fluid py-4">
        <!-- Search Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <form class="d-flex">
                    <input class="form-control me-2" type="search" placeholder="Search restaurants...">
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>
            </div>
        </div>

        <!-- Map Container -->
        <div id="map" style="height: 80vh; width: 95%; margin: 0 auto; display: block;"></div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/map.js"></script>
</body>
</html>