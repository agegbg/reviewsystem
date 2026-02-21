<?php
// 200_my_reviews.php – Shows only your own review sessions

// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Add file info to database for menu/system tracking
require_once 'php/file_register.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: 00_login.php");
    exit;
}

// Fetch your review sessions
$stmt = $pdo->prepare("
    SELECT rs.id, g.date, g.home_team, g.away_team, rs.created_by, rs.create_date
    FROM review_session rs
    JOIN game g ON rs.game_id = g.id
    WHERE rs.created_by = ?
    ORDER BY g.date DESC
");
$stmt->execute([$user_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reviews</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        table td, table th { vertical-align: middle; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h3>📝 My Review Games</h3>

    <?php if (count($sessions) === 0): ?>
        <div class="alert alert-info">You haven’t created any review games yet.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered mt-3">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Game</th>
                    <th>Created</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['home_team']) ?> vs <?= htmlspecialchars($row['away_team']) ?></td>
                        <td><?= htmlspecialchars($row['create_date']) ?></td>
                        <td>
                            <a href="202_review_observations.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
