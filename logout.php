<?php
require_once 'includes/config.php';

// Destroy the session
session_destroy();

// Redirect to login page with a success message
header('Location: login.php?logged_out=1');
exit();
?> 