<?php
// 301_mygames.php – Shows all games where user is in referee crew and allows review

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

// Fetch games where user is assigned as referee (any role in review_crew)
$stmt = $pdo->prepare("
    SELECT 
        g.id AS game_id,
        g.date, g.league,
        t1.name AS home_name, t1.logo AS home_logo,
        t2.name AS away_name, t2.logo AS away_logo,
        -- Count user's observations
        (SELECT COUNT(*) FROM review_evaluation e1 
         JOIN review_observation o1 ON e1.id = o1.evaluation_id 
         WHERE e1.user_id = :user_id AND e1.game_id = g.id) AS my_reviews,
        -- Count all observations for the game
        (SELECT COUNT(*) FROM review_evaluation e2 
         JOIN review_observation o2 ON e2.id = o2.evaluation_id 
         WHERE e2.game_id = g.id) AS total_reviews
    FROM review_game g
    JOIN review_crew c ON c.game_id = g.id
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE :user_id IN (
        c.referee_id, c.umpire_id, c.hl_id, c.lj_id, c.fj_id,
        c.sj_id, c.bj_id, c.cj_id, c.observer_id, c.evaluator_id
    )
    GROUP BY g.id
    ORDER BY g.date DESC
");
$stmt->execute(['user_id' => $user_id]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare evaluation mapping
$evaluation_map = [];
if ($games) {
    $game_ids = array_column($games, 'game_id');
    $in_clause = implode(',', array_fill(0, count($game_ids), '?'));
    $eval_stmt = $pdo->prepare("
        SELECT id, game_id FROM review_evaluation
        WHERE user_id = ? AND game_id IN ($in_clause)
    ");
    $eval_stmt->execute(array_merge([$user_id], $game_ids));
    foreach ($eval_stmt->fetchAll(PDO::FETCH_ASSOC) as $eval) {
        $evaluation_map[$eval['game_id']] = $eval['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
    <!-- Filename: 301_mygames.php -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo {
            max-height: 30px;
        }
        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h3>My Games as Referee</h3>

    <?php if (empty($games)): ?>
        <p>No games assigned to you.</p>
    <?php else: ?>
        <table class="table table-bordered table-sm mt-4">
            <thead class="thead-light text-center">
                <tr>
                    <th>Logo</th>
                    <th class="text-right">Home</th>
                    <th class="text-left">Away</th>
                    <th>Logo</th>
                    <th>Date</th>
                    <th>League</th>
                    <th>My Obs</th>
                    <th>Total</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($games as $g): ?>
                    <?php
                        $game_id = $g['game_id'];
                        // Create evaluation if missing
                        if (!isset($evaluation_map[$game_id])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO review_evaluation (game_id, user_id, is_head_evaluator, evaluation_type, create_date, update_date)
                                VALUES (?, ?, 0, 'official', NOW(), NOW())
                            ");
                            $stmt->execute([$game_id, $user_id]);
                            $evaluation_id = $pdo->lastInsertId();
                            $evaluation_map[$game_id] = $evaluation_id;
                        } else {
                            $evaluation_id = $evaluation_map[$game_id];
                        }

                        $link = "202_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id";
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php if ($g['home_logo']): ?>
                                <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo mr-1">
                            <?php endif; ?>
                        </td>
                        <td class="text-right nowrap"><?= htmlspecialchars($g['home_name']) ?></td>
                        <td class="text-left nowrap"><?= htmlspecialchars($g['away_name']) ?></td>
                        <td class="text-center">
                            <?php if ($g['away_logo']): ?>
                                <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo ml-1">
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($g['date']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($g['league']) ?></td>
                        <td class="text-center"><?= (int)$g['my_reviews'] ?></td>
                        <td class="text-center"><?= (int)$g['total_reviews'] ?></td>
                        <td class="text-center">
                            <a href="<?= $link ?>" class="btn btn-sm btn-primary">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
