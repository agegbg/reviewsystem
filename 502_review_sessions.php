<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

$pdo = getDatabaseConnection();

// Skicka mejl om formulär skickas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $evaluationId = intval($_POST['evaluation_id']);
    $gameId = intval($_POST['game_id']);
    $reviewerName = $_POST['reviewer_name'];
    $email = $_POST['email'];

    $subject = "Dukes Review: Observation Links";
    $message = "Hej $reviewerName,\n\nHär är dina länkar för att utvärdera matchen:\n\n"
             . "📱 Mobil: https://www.zebras.se/review/201_add_observation.php?game_id=$gameId&evaluation_id=$evaluationId\n"
             . "🖥️ Dator: https://www.zebras.se/review/202_add_observation.php?game_id=$gameId&evaluation_id=$evaluationId\n\n"
             . "Lycka till med utvärderingen!\nDukes Review System";

    $headers = "From: no-reply@zebras.se";

    if (mail($email, $subject, $message, $headers)) {
        echo "<div class='alert alert-success'>Mejl skickat till $reviewerName ($email)</div>";
    } else {
        echo "<div class='alert alert-danger'>Kunde inte skicka mejl till $reviewerName</div>";
    }
}

// Hämta alla utvärderingar med användarnamn, lag och antal inlägg
$stmt = $pdo->query("
    SELECT e.id AS evaluation_id, e.user_id, e.game_id,
           u.name AS reviewer_name, u.email,
           t1.name AS home_name, t2.name AS away_name,
           COUNT(o.id) AS num_observations
    FROM review_evaluation e
    JOIN review_user u ON e.user_id = u.id
    JOIN review_game g ON e.game_id = g.id
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    LEFT JOIN review_observation o ON e.id = o.evaluation_id
    GROUP BY e.id
    ORDER BY g.date DESC, g.start_time DESC
");
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Alla Review Sessions</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>Alla Review Sessions</h2>
    <table class="table table-bordered table-sm">
        <thead class="thead-light">
        <tr>
            <th>Utvärderare</th>
            <th>Hemma</th>
            <th>Borta</th>
            <th>Inlägg</th>
            <th>Länkar</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($evaluations as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['reviewer_name']) ?></td>
                <td><?= htmlspecialchars($e['home_name']) ?></td>
                <td><?= htmlspecialchars($e['away_name']) ?></td>
                <td><?= $e['num_observations'] ?></td>
                <td>
                    <a class="btn btn-sm btn-outline-primary mb-1"
                       href="201_add_observation.php?game_id=<?= $e['game_id'] ?>&evaluation_id=<?= $e['evaluation_id'] ?>">📱 Mobil</a>
                    <a class="btn btn-sm btn-outline-secondary mb-1"
                       href="202_add_observation.php?game_id=<?= $e['game_id'] ?>&evaluation_id=<?= $e['evaluation_id'] ?>">🖥️ Dator</a>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="send_email" value="1">
                        <input type="hidden" name="evaluation_id" value="<?= $e['evaluation_id'] ?>">
                        <input type="hidden" name="game_id" value="<?= $e['game_id'] ?>">
                        <input type="hidden" name="reviewer_name" value="<?= htmlspecialchars($e['reviewer_name']) ?>">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($e['email']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success">✉️ Email</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
