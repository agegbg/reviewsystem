<?php
// 505_list_reviewed_games.php – Visar alla matcher som har minst ett review-inlägg

require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Hämta matcher med minst ett review-inlägg
$stmt = $pdo->query("
    SELECT 
        g.id AS game_id,
        g.date, g.time,
        t1.name AS home_name,
        t1.logo AS home_logo,
        t2.name AS away_name,
        t2.logo AS away_logo,
        COUNT(o.id) AS total_reviews
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    JOIN review_evaluation e ON g.id = e.game_id
    JOIN review_observation o ON o.evaluation_id = e.id
    GROUP BY g.id
    HAVING total_reviews > 0
    ORDER BY g.date DESC, g.time DESC
");

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Matcher med inlägg</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { padding: 1rem; }
        .logo-img { height: 30px; margin-right: 5px; }
        .game-table { max-width: 1000px; margin: auto; }
        td, th { vertical-align: middle !important; }
    </style>
</head>
<body>
<div class="container">
    <h3 class="mb-4">Matcher med inlägg</h3>

    <table class="table table-bordered table-hover game-table">
        <thead class="thead-light">
            <tr>
                <th>Datum</th>
                <th>Hemmalag</th>
                <th></th>
                <th>Bortalag</th>
                <th>Inlägg</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($games as $g): ?>
                <tr>
                    <td><?= htmlspecialchars($g['date']) ?> <?= htmlspecialchars($g['time']) ?></td>
                    <td>
                        <?php if ($g['home_logo']): ?>
                            <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo-img">
                        <?php endif; ?>
                        <?= htmlspecialchars($g['home_name']) ?>
                    </td>
                    <td class="text-center font-weight-bold">vs</td>
                    <td>
                        <?php if ($g['away_logo']): ?>
                            <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo-img">
                        <?php endif; ?>
                        <?= htmlspecialchars($g['away_name']) ?>
                    </td>
                    <td>
                        <a href="502_show_reviews.php?game_id=<?= $g['game_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <?= $g['total_reviews'] ?> st
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($games)): ?>
                <tr><td colspan="5" class="text-center text-muted">Inga matcher har några review-inlägg ännu.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
