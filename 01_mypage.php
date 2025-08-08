<?php
// 01_mypage.php – Main user dashboard

require_once 'php/session.php';
require_once 'php/db.php';
require_once 'php/roles.php';

$pdo = getDatabaseConnection();

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: 00_login.php");
    exit;
}

// Get user name
$stmt = $pdo->prepare("SELECT name FROM review_user WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$name = $user['name'] ?? '';

// Get extended info
$stmt = $pdo->prepare("SELECT * FROM review_user_info WHERE user_id = ?");
$stmt->execute([$user_id]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Page</title>
    <!-- Filename: 01_mypage.php -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .btn-role {
    min-width: 160px;
    margin: 5px;
}

        .info-box {
            margin: 40px auto;
            max-width: 600px;
        }
        .photo {
            max-width: 120px;
            max-height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        .name-header {
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Greeting -->
    <h3 class="name-header">👋 Welcome <?= htmlspecialchars($name) ?>!</h3>

    <!-- Info Card -->
    <div class="card info-box">
        <div class="card-header">Your Info</div>
        <div class="card-body row">
            <div class="col-sm-8">
                <?php if ($info): ?>
                    <table class="table table-sm">
                        <tr><th scope="row">City</th><td><?= htmlspecialchars($info['city']) ?></td></tr>
                        <tr><th scope="row">License</th><td><?= htmlspecialchars($info['license_number']) ?></td></tr>
                    </table>
                <?php else: ?>
                    <p>No extra information found yet.</p>
                <?php endif; ?>
               <!-- Removed Edit button – already in menu -->
            </div>
            <div class="col-sm-4 text-center">
                <?php if (!empty($info['photo'])): ?>
                    <img src="photo/<?= htmlspecialchars($info['photo']) ?>" alt="Photo" class="photo mt-2">
                <?php endif; ?>
            </div>
        </div>
    </div>

  <!-- Role Buttons in responsive row -->
<div class="d-flex flex-wrap justify-content-center mt-5 gap-2">
    <?php if (hasRole('referee')): ?>
        <a href="301_mygames.php" class="btn btn-success btn-role">My Games</a>
    <?php endif; ?>

    <?php if (hasRole('reviewer')): ?>
        <a href="201_review_list.php" class="btn btn-info btn-role">Review Games</a>
    <?php endif; ?>

    <a href="01_editmypage.php" class="btn btn-secondary btn-role">My Info</a>

    <?php if (hasRole('admin')): ?>
        <a href="999_admin_menu.php" class="btn btn-danger btn-role">Admin Panel</a>
    <?php endif; ?>

    <a href="00_logout.php" class="btn btn-outline-dark btn-role">Log Out</a>
</div>


</div>
</body>
</html>
