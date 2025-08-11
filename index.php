<?php
/**
 * index.php
 * Entry point for the Zebraz Review System.
 * - If a user is logged in, redirect to personal page (01_mypage.php)
 * - Otherwise, render a simple start page using shared header/footer
 *
 * Calls:       php/header.php, php/footer.php
 * Called from: -
 */

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';

// Redirect logged-in users to their personal dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: 01_mypage.php');
    exit();
}

// ---- Page variables consumed by header/footer ----
$PAGE_TITLE       = 'Zebraz Review System – Start';
$APP_NAME         = 'Zebraz Review System';
$APP_VERSION      = 'v1.0';
$COPYRIGHT_HOLDER = 'Zebraz';

// Optional: extra CSS for this page (kept minimal)
$EXTRA_HEAD = <<<HTML
<style>
  body { background-color:#f8f9fa; }
  .landing-wrap { max-width:640px; margin:0 auto; padding-top:64px; text-align:center; }
  .landing-logo { width:140px; margin-bottom:28px; }
  .btn-lg { margin-top:20px; width:100%; }
</style>
HTML;

// Optional: page-specific JS
$EXTRA_FOOT = '<script>console.log("Index loaded");</script>';

// Open PDO connection (available if you need it later on this page)
$pdo = getDatabaseConnection();

// Include shared header (contains <head>, navbar, opening <body>)
require_once __DIR__ . '/php/header.php';
?>

<div class="container landing-wrap">
    <!-- System logo -->
    <img src="image/zebraz.jpg" alt="Zebraz Logo" class="landing-logo">

    <!-- Title & intro -->
    <h1 class="h3 mb-3">Welcome to the Zebraz Review System</h1>
    <p class="lead text-muted">
        Log in to review games, manage evaluations, or view your referee assignments.
    </p>

    <!-- Start login -->
    <a href="01_select_user.php" class="btn btn-primary btn-lg">
        🔐 Start login process
    </a>

    <!-- Small, consistent footer area lives in php/footer.php -->
</div>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
