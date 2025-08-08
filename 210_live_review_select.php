<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Simulerad inloggning (byt till sessionshantering senare)
$currentUserId = 1; // <-- byt till session senare
$userStmt = $pdo->prepare("SELECT * FROM review_user WHERE id = ?");
$userStmt->execute([$currentUserId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// Kontroll: endast utvärderare
if (!$currentUser || $currentUser['is_evaluator'] != 1) {
    die("Du har inte behörighet att göra live review.");
}

// Skapa ny review direkt om game_id skickats
if (isset($_GET['game_id']) && is_numeric($_GET['game_id'])) {
    $game_id = intval($_GET['game_id']);

    // Kolla om redan finns en utvärdering
    $stmt = $pdo->prepare("SELECT id FROM review_evaluation WHERE game_id = ? AND user_id = ?");
    $stmt->execute([$game_id, $currentUserId]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        header("Location: 201_add_observation.php?game_id=$game_id&evaluation_id=$existing");
        exit;
    }

    // Skapa ny utvärdering
    $stmt = $pdo->prepare("
        INSERT INTO review_evaluation (game_id, user_id, is_head_evaluator, evaluation_type, create_date, update_date)
        VALUES (?, ?, 0, 'official', NOW(), NOW())
    ");
    $stmt->execute([$game_id, $currentUserId]);
    $evaluation_id = $pdo->lastInsertId();

    header("Location: 201_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id");
    exit;
}

// Hämta matcher som startar inom 1 timme och ej avslutade
$stmt = $pdo->query("
    SELECT g.id, g.date, g.start_time, g.place, g.field, 
           t1.name AS home_team, t2.name AS away_team
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE g.is_finished = 0 
      AND TIMESTAMP(g.date, g.start_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
    ORDER BY g.date, g.start_time
");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Live Review – Välj Match</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>Live Review – Välj Match</h2>

    <?php if (count($matches) === 0): ?>
        <div class="alert alert-info">Inga matcher tillgängliga för live review just nu.</div>
    <?php else: ?>
        <table class="table table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th>Datum</th>
                    <th>Tid</th>
                    <th>Match</th>
                    <th>Plats</th>
                    <th>Plan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $match): ?>
                <tr>
                    <td><?= htmlspecialchars($match['date']) ?></td>
                    <td><?= substr($match['start_time'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($match['home_team']) ?> vs <?= htmlspecialchars($match['away_team']) ?></td>
                    <td><?= htmlspecialchars($match['place']) ?></td>
                    <td><?= htmlspecialchars($match['field']) ?></td>
                    <td>
                        <a href="?game_id=<?= $match['id'] ?>" class="btn btn-sm btn-success">Starta Review</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
