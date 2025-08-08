<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Add file info to database for menu/system tracking
require_once 'php/file_register.php';

$pdo = getDatabaseConnection();

$game_id = $_GET['game_id'] ?? 0;
if (!$game_id) die("game_id saknas");

// Valda filter
$selected_reviewer = $_GET['reviewer_id'] ?? '';
$selected_position = $_GET['position'] ?? '';
$selected_grading = $_GET['grading'] ?? '';

// Hämta matchinfo
$stmt = $pdo->prepare("
    SELECT g.*, t1.name AS home_name, t2.name AS away_name,
           t1.logo AS home_logo, t2.logo AS away_logo
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE g.id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) die("Match ej hittad");

// Hämta alla reviewers för dropdown
$reviewers = $pdo->prepare("
    SELECT DISTINCT u.id, u.name
    FROM review_user u
    JOIN review_evaluation e ON u.id = e.user_id
    JOIN review_observation o ON o.evaluation_id = e.id
    WHERE e.game_id = ?
    ORDER BY u.name
");
$reviewers->execute([$game_id]);
$reviewer_list = $reviewers->fetchAll(PDO::FETCH_ASSOC);

// Hämta observationer (med filter)
$query = "
    SELECT o.*, u.name AS reviewer_name
    FROM review_observation o
    JOIN review_evaluation e ON o.evaluation_id = e.id
    JOIN review_user u ON e.user_id = u.id
    WHERE e.game_id = :game_id
";
$params = ['game_id' => $game_id];

if ($selected_reviewer) {
    $query .= " AND u.id = :reviewer_id";
    $params['reviewer_id'] = $selected_reviewer;
}
if ($selected_position) {
    $query .= " AND o.position = :position";
    $params['position'] = $selected_position;
}
if ($selected_grading) {
    $query .= " AND o.grading = :grading";
    $params['grading'] = $selected_grading;
}

$query .= " ORDER BY o.time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Review: <?= htmlspecialchars($game['home_name']) ?> vs <?= htmlspecialchars($game['away_name']) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { padding: 1rem; }
        .logo { height: 40px; }
        td, th { vertical-align: middle !important; }
        select.form-control-sm { max-width: 200px; display: inline-block; margin-right: 1rem; }
    </style>
</head>
<body>
<div class="container">

    <h3 class="mb-4">Review: <?= htmlspecialchars($game['home_name']) ?> vs <?= htmlspecialchars($game['away_name']) ?></h3>

    <div class="d-flex align-items-center mb-3">
        <?php if ($game['home_logo']): ?>
            <img src="logo/<?= htmlspecialchars($game['home_logo']) ?>" class="logo mr-2">
        <?php endif; ?>
        <strong class="mr-2"><?= htmlspecialchars($game['home_name']) ?></strong>
        <span class="mx-2">vs</span>
        <strong class="mr-2"><?= htmlspecialchars($game['away_name']) ?></strong>
        <?php if ($game['away_logo']): ?>
            <img src="logo/<?= htmlspecialchars($game['away_logo']) ?>" class="logo ml-2">
        <?php endif; ?>
    </div>

    <form method="get" class="form-inline mb-3">
        <input type="hidden" name="game_id" value="<?= $game_id ?>">
        <select name="reviewer_id" class="form-control form-control-sm">
            <option value="">Alla utvärderare</option>
            <?php foreach ($reviewer_list as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $r['id'] == $selected_reviewer ? 'selected' : '' ?>>
                    <?= explode(' ', $r['name'])[0] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="position" class="form-control form-control-sm">
            <option value="">Alla positioner</option>
            <?php foreach (['Referee','Ump','HL','LJ','BJ','FJ','SJ','C','Crew'] as $pos): ?>
                <option value="<?= $pos ?>" <?= $pos == $selected_position ? 'selected' : '' ?>><?= $pos ?></option>
            <?php endforeach; ?>
        </select>

        <select name="grading" class="form-control form-control-sm">
            <option value="">Alla gradering</option>
            <?php foreach (['CC','NC','CNC','CJ','MC','IJ','IC','IR','CM','EX','IM','Disc'] as $g): ?>
                <option value="<?= $g ?>" <?= $g == $selected_grading ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-sm btn-secondary">Filtrera</button>
    </form>

    <table class="table table-sm table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Tid</th>
                <th>Position</th>
                <th>Typ</th>
                <th>Foul</th>
                <th>Gradering</th>
                <th>Kommentar</th>
                <th>Utvärderare</th>
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
                    <td><?= htmlspecialchars(explode(' ', $o['reviewer_name'])[0]) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($observations)): ?>
                <tr><td colspan="7" class="text-center text-muted">Inga observationer matchar filtret</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>
