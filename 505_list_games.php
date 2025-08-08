<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Hämta alla matcher med laginfo
$stmt = $pdo->query("
    SELECT g.id, g.date, g.start_time, g.field, g.league,
           t1.name AS home_name, t1.logo AS home_logo,
           t2.name AS away_name, t2.logo AS away_logo
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    ORDER BY g.date DESC, g.start_time DESC
");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Lista Matcher</title>
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo { height: 40px; margin-right: 5px; }
        td, th { vertical-align: middle !important; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Matcher</h2>
    <table class="table table-bordered table-sm">
        <thead class="thead-light">
        <tr>
            <th>Datum</th>
            <th>Starttid</th>
            <th>Hemma</th>
            <th>Borta</th>
            <th>Level</th>
            <th>Field</th>
            <th>Info</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($games as $g): ?>
            <tr>
                <td><?= htmlspecialchars($g['date']) ?></td>
                <td><?= htmlspecialchars($g['start_time']) ?></td>
                <td class="nowrap">
                    <?php if ($g['home_logo']): ?>
                        <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo">
                    <?php endif; ?>
                    <?= htmlspecialchars($g['home_name']) ?>
                </td>
                <td class="nowrap">
                    <?php if ($g['away_logo']): ?>
                        <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo">
                    <?php endif; ?>
                    <?= htmlspecialchars($g['away_name']) ?>
                </td>
                <td><?= htmlspecialchars($g['league']) ?></td>
                <td><?= htmlspecialchars($g['field']) ?></td>
                <td>
                    <a href="504_add_info.php?game_id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary">Add Info</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($games)): ?>
            <tr><td colspan="7" class="text-center text-muted">Inga matcher hittades</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
