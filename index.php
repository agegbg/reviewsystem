<?php
// index.php – Entry point for the Zebraz Review System
// Redirects logged-in users to their personal page

session_start();

// If user is already logged in, redirect to personal dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: 01_mypage.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Zebraz Review System – Start</title>
    <!-- Load Bootstrap 4.3.1 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            text-align: center;
            padding-top: 60px;
        }
        .container {
            max-width: 600px;
        }
        .btn-lg {
            margin-top: 20px;
            width: 100%;
        }
        .logo {
            width: 140px;
            margin-bottom: 30px;
        }
        footer {
            margin-top: 60px;
            font-size: 0.9em;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- System logo -->
        <img src="image/zebraz.jpg" alt="Zebraz Logo" class="logo">

        <!-- Page title and intro -->
        <h1 class="mb-4">Welcome to the Zebraz Review System</h1>
        <p class="lead">Log in to review games, manage evaluations, or view your referee assignments.</p>

        <!-- Button to initiate login process -->
        <a href="01_select_user.php" class="btn btn-primary btn-lg">
            🔐 Start login process
        </a>

        <!-- Footer -->
        <footer>
            <p class="mt-5">© 2025 Zebraz | Review System v1.0</p>
        </footer>
    </div>
</body>
</html>
