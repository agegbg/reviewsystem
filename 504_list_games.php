<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Hämta valt datum från GET, annars visa allt
$selected_date = $_GET['date'] ?? '';

// Förbered query
$sql = "
    SELECT g.*, 
           t1.name AS home_name, t1.logo AS home_logo,
           t2.name AS away_name, t2.logo AS away_logo
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
";

$params = [];
if ($selected_date) {
    $sql .= " WHERE g.date = ?";
    $params[] = $selected_date;
}

$sql .= " ORDER BY g.date DESC, g.time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Matcher</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo { height: 40px; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Alla Matcher</h2>

    <form class="form-inline mb-3" method="get">
        <label class="mr-2">Filtrera på datum:</label>
        <input type="date" name="date" class="form-control mr-2" value="<?= htmlspecialchars($selected_date) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Filtrera</button>
        <a href="505_list_games.php" class="btn btn-secondary btn-sm ml-2">Rensa</a>
    </form>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
        <tr>
            <th>Hemma</th>
            <th>Borta</th>
            <th>Datum</th>
            <th>Tid</th>
            <th>Field</th>
            <th>Level</th>
            <th>Crew</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($games as $g): ?>
            <tr>
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
                <td><?= htmlspecialchars($g['date']) ?></td>
                <td><?= htmlspecialchars($g['time']) ?></td>
                <td><?= htmlspecialchars($g['field']) ?></td>
                <td><?= htmlspecialchars($g['league']) ?></td>
                <td>
    <a href="505_add_crew.php?game_id=<?= $g['id'] ?>" class="btn btn-sm btn-primary">
        Add/Edit Crew
    </a>
</td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($games)): ?>
            <tr><td colspan="6" class="text-center text-muted">Inga matcher funna</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
