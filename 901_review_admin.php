<?php
// 901_review_admin.php
// Admin page to view and toggle review status (open/closed) using is_finished

require_once 'php/session.php';
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Admin page for managing review status of all games');

$pdo = getDatabaseConnection();

// Access control
if ($_SESSION['user_id'] != 1) {
    die("Access denied");
}

// Toggle review status if triggered
if (isset($_GET['toggle']) && isset($_GET['game_id'])) {
    $game_id = (int) $_GET['game_id'];
    $stmt = $pdo->prepare("UPDATE review_game SET is_finished = 1 - is_finished WHERE id = ?");
    $stmt->execute([$game_id]);
    header("Location: 901_review_admin.php?updated=1");
    exit;
}

// Fetch all games
$stmt = $pdo->query("
    SELECT 
        g.id AS game_id,
        g.date, g.league, g.field,
        g.is_finished,
        t1.name AS home_name, t1.logo AS home_logo,
        t2.name AS away_name, t2.logo AS away_logo
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    ORDER BY g.date DESC
");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Admin: Review Status</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo { height: 30px; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h3>⚙️ Admin – Manage Review Status</h3>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">✅ Review status updated.</div>
    <?php endif; ?>

    <table class="table table-sm table-bordered text-center">
        <thead class="thead-light">
            <tr>
                <th>Logo</th>
                <th>Home</th>
                <th>Away</th>
                <th>Date</th>
                <th>League</th>
                <th>Status</th>
                <th>Toggle</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($games as $g): ?>
                <tr>
                    <td>
                        <?php if ($g['home_logo']): ?>
                            <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo">
                        <?php endif; ?>
                        <?php if ($g['away_logo']): ?>
                            <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo ml-1">
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= htmlspecialchars($g['home_name']) ?></td>
                    <td class="text-left"><?= htmlspecialchars($g['away_name']) ?></td>
                    <td><?= htmlspecialchars($g['date']) ?></td>
                    <td><?= htmlspecialchars($g['league']) ?></td>
                    <td>
                        <?= $g['is_finished'] ? '🔒 Closed' : '🔓 Open' ?>
                    </td>
                    <td>
                        <a href="901_review_admin.php?toggle=1&game_id=<?= $g['game_id'] ?>" 
                           class="btn btn-sm <?= $g['is_finished'] ? 'btn-success' : 'btn-danger' ?>">
                            <?= $g['is_finished'] ? 'Open' : 'Close' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
