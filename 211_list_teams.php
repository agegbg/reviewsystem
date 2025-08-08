<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$search = $_GET['search'] ?? '';
$showIncomplete = isset($_GET['incomplete']) && $_GET['incomplete'] == '1';

$sql = "SELECT * FROM dt_team WHERE 1";
$params = [];

if ($showIncomplete) {
    $sql .= " AND (logo IS NULL OR logo = '' OR color1 IS NULL OR color1 = '' OR color2 IS NULL OR color2 = '' OR shortname IS NULL OR shortname = '')";
}

if (!empty($search)) {
    $sql .= " AND (name LIKE :search OR city LIKE :search OR shortname LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Lista lag</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo-img { height: 40px; }
        .color-box { display: inline-block; width: 20px; height: 20px; border: 1px solid #000; margin-right: 4px; }
    </style>
</head>
<body class="p-4">

<h2 class="mb-4">Lista över lag</h2>

<form class="form-inline mb-4" method="get">
    <input type="text" name="search" class="form-control mr-2" placeholder="Sök lag..." value="<?= htmlspecialchars($search) ?>">
    <label class="mr-2"><input type="checkbox" name="incomplete" value="1" <?= $showIncomplete ? 'checked' : '' ?>> Visa endast ofullständiga</label>
    <button type="submit" class="btn btn-primary">Sök</button>
    <a href="211_list_teams.php" class="btn btn-secondary ml-2">Återställ</a>
</form>

<table class="table table-bordered table-sm">
    <thead class="thead-light">
        <tr>
            <th>ID</th>
            <th>Logo</th>
            <th>Namn</th>
            <th>Stad</th>
            <th>Kortnamn</th>
            <th>Färg 1</th>
            <th>Färg 2</th>
            <th>Redigera</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($teams as $team): ?>
        <tr>
            <td><?= $team['id'] ?></td>
            <td>
                <?php if (!empty($team['logo'])): ?>
                    <img src="logo/<?= htmlspecialchars($team['logo']) ?>" class="logo-img">
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($team['name']) ?></td>
            <td><?= htmlspecialchars($team['city']) ?></td>
            <td><?= htmlspecialchars($team['shortname']) ?></td>
            <td>
                <?php if (!empty($team['color1'])): ?>
                    <span class="color-box" style="background: <?= $team['color1'] ?>;"></span> <?= $team['color1'] ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($team['color2'])): ?>
                    <span class="color-box" style="background: <?= $team['color2'] ?>;"></span> <?= $team['color2'] ?>
                <?php endif; ?>
            </td>
            <td><a href="212_edit_team.php?id=<?= $team['id'] ?>" class="btn btn-sm btn-info">Redigera</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
