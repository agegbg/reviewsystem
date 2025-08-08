<?php
// Start the session
session_start();

// ==============================================
// Configuration: Set to true to require login
// ==============================================
$requireLoginGlobally = true;

// ==============================================
// Redirect to login page if login is required
// and user is not logged in
// ==============================================
if ($requireLoginGlobally && !isset($_SESSION['user_id'])) {
    // Change this path if your login file is located elsewhere
    header("Location: ./00_login.php");
    exit;
}

// ==============================================
// Optional: You can access session variables here
// e.g. $_SESSION['user_id'], $_SESSION['user_email']
// ==============================================

?>
