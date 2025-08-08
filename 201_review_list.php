<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Add file info to database for menu/system tracking
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Lists all review games for logged-in reviewer with logos and review counts.');

$pdo = getDatabaseConnection();

// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: 00_login.php");
    exit;
}

// Fetch games assigned to this reviewer, including logos and review counts
$stmt = $pdo->prepare("
    SELECT 
        g.id AS game_id,
        g.date, g.league,
        t1.name AS home_name, t1.logo AS home_logo,
        t2.name AS away_name, t2.logo AS away_logo,
        -- Number of observations written by this user
        (SELECT COUNT(*) FROM review_evaluation e1 
            JOIN review_observation o1 ON e1.id = o1.evaluation_id 
            WHERE e1.user_id = :user_id AND e1.game_id = g.id
        ) AS my_reviews,
        -- Total number of observations for the game
        (SELECT COUNT(*) FROM review_evaluation e2 
            JOIN review_observation o2 ON e2.id = o2.evaluation_id 
            WHERE e2.game_id = g.id
        ) AS total_reviews
    FROM review_game g
    JOIN review_evaluation e ON e.game_id = g.id
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE e.user_id = :user_id
    GROUP BY g.id
    ORDER BY g.date DESC
");
$stmt->execute(['user_id' => $user_id]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Review Games</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo {
            height: 30px;
            vertical-align: middle;
        }
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .text-center {
            text-align: center;
        }
        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h3>🎯 My Review Games</h3>

    <?php if (empty($games)): ?>
        <div class="alert alert-info">You have no assigned games to review yet.</div>
    <?php else: ?>
        <!-- Table showing all assigned games with logos and review stats -->
        <table class="table table-bordered table-sm">
            <thead class="thead-light text-center">
                <tr>
                    <th></th>
                    <th>Home</th>
                    <th>Away</th>
                    <th></th>
                    <th>Date</th>
                    <th>Event</th>
                    <th>Reviews</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($games as $g): ?>
                <tr>
                    <!-- Logos column (home) -->
                    <td class="text-center">
                        <?php if ($g['home_logo']): ?>
                            <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo mr-1">
                        <?php endif; ?>
                        
                    </td>

                    <!-- Home team name (right-aligned) -->
                    <td class="text-right"><?= htmlspecialchars($g['home_name']) ?></td>

                    <!-- Away team name (left-aligned) -->
                    <td class="text-left"><?= htmlspecialchars($g['away_name']) ?></td>

                        <!-- Logos column (away) -->
                    <td class="text-center">
                    <?php if ($g['away_logo']): ?>
                            <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo ml-1">
                        <?php endif; ?>
                        </td>

                    <!-- Game date -->
                    <td class="text-center"><?= htmlspecialchars($g['date']) ?></td>

                    <!-- League or event -->
                    <td class="text-center"><?= htmlspecialchars($g['league']) ?></td>

                    <!-- Review count: My reviews / Total reviews -->
                    <td class="text-center">
                        <a href="502_show_reviews.php?game_id=<?= $g['game_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <?= $g['my_reviews'] ?> / <?= $g['total_reviews'] ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
