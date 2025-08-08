<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$evaluation_id = $_GET['evaluation_id'] ?? 0;
if (!$evaluation_id) die("Missing evaluation_id");

// Hämta utvärdering och match
$stmt = $pdo->prepare("
    SELECT g.id AS game_id, g.date, g.start_time,
           t1.name AS home_name, t1.logo AS home_logo,
           t2.name AS away_name, t2.logo AS away_logo,
           u.name AS reviewer_name
    FROM review_evaluation e
    JOIN review_game g ON e.game_id = g.id
    JOIN review_user u ON e.user_id = u.id
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE e.id = ?
");
$stmt->execute([$evaluation_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die("Invalid evaluation ID");

// Hämta observationer
$stmt = $pdo->prepare("SELECT * FROM review_observation WHERE evaluation_id = ? ORDER BY time ASC");
$stmt->execute([$evaluation_id]);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Observationer</title>
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo { height: 40px; }
        td, th { vertical-align: middle !important; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h3>Observationer</h3>
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3">
            <?php if ($data['home_logo']): ?>
                <img src="logo/<?= $data['home_logo'] ?>" class="logo">
            <?php endif; ?>
            <?= htmlspecialchars($data['home_name']) ?>
        </div>
        <strong class="mx-2">vs</strong>
        <div class="mr-3">
            <?php if ($data['away_logo']): ?>
                <img src="logo/<?= $data['away_logo'] ?>" class="logo">
            <?php endif; ?>
            <?= htmlspecialchars($data['away_name']) ?>
        </div>
    </div>
    <p><strong>Datum:</strong> <?= $data['date'] ?> <?= $data['start_time'] ?></p>
    <p><strong>Utvärderare:</strong> <?= htmlspecialchars($data['reviewer_name']) ?></p>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
        <tr>
            <th>Tid</th>
            <th>Position</th>
            <th>Typ</th>
            <th>Foul / Regel</th>
            <th>Gradering</th>
            <th>Kommentar</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($observations as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['time']) ?></td>
                <td><?= htmlspecialchars($o['position']) ?></td>
                <td><?= htmlspecialchars($o['play_type']) ?></td>
                <td><?= htmlspecialchars($o['foul']) ?></td>
                <td><?= htmlspecialchars($o['grading']) ?></td>
                <td><?= htmlspecialchars($o['comment']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($observations)): ?>
            <tr><td colspan="6" class="text-center text-muted">Inga observationer ännu.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <a href="502_review_sessions.php" class="btn btn-secondary">Tillbaka</a>
</div>
</body>
</html>
