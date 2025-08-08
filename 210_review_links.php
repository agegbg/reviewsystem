<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Hämta alla med delad länk
$stmt = $pdo->query("
    SELECT e.id, e.shared_link_code, e.is_head_evaluator, u.name AS reviewer,
           g.id AS game_id, t1.name AS home, t2.name AS away
    FROM review_evaluation e
    JOIN review_user u ON e.user_id = u.id
    JOIN review_game g ON e.game_id = g.id
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE e.shared_link_code IS NOT NULL
    ORDER BY g.date DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Review Links</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h2>Active Shared Review Links</h2>
        <table class="table table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th>Match</th>
                    <th>Reviewer</th>
                    <th>Head?</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['home']) ?> vs <?= htmlspecialchars($r['away']) ?></td>
                        <td><?= htmlspecialchars($r['reviewer']) ?></td>
                        <td><?= $r['is_head_evaluator'] ? 'Yes' : 'No' ?></td>
                        <td>
                            <a href="203_start_from_link.php?code=<?= $r['shared_link_code'] ?>" target="_blank">
                                203_start_from_link.php?code=<?= $r['shared_link_code'] ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

