<?php
session_start();

// Clear all session variables
session_unset();

// Destroy the session entirely
session_destroy();

// Redirect to homepage (you can change this if needed)
header('Location: /index.php');
exit;
