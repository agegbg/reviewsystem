<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $color1 = $_POST['color1'] ?? null;
    $color2 = $_POST['color2'] ?? null;

    // Handle logo upload
    $logoName = null;
    if (!empty($_FILES['logo']['name'])) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logoName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $_POST['shortname'])) . '.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], "logo/$logoName");
    }

    $sql = "UPDATE review_team SET color1 = ?, color2 = ?" . ($logoName ? ", logo = ?" : "") . " WHERE id = ?";
    $params = [$color1, $color2];
    if ($logoName) $params[] = $logoName;
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: 501_edit_incomplete_team.php");
    exit;
}

// ✅ Get only teams with incomplete info
$teams = $pdo->query("
    SELECT * FROM review_team 
    WHERE logo IS NULL OR logo = ''
       OR color1 IS NULL OR color1 = ''
       OR color2 IS NULL OR color2 = ''
       OR shortname IS NULL OR shortname = ''
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Get team if editing
$editTeam = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM review_team WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editTeam = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Redigera ofullständiga lag</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>Redigera ofullständiga lag</h2>
    <p><a href="500_edit_team.php" class="btn btn-outline-primary btn-sm mb-3">Visa alla lag</a></p>

    <?php if ($editTeam): ?>
        <form method="post" enctype="multipart/form-data" class="mb-4">
            <input type="hidden" name="id" value="<?= $editTeam['id'] ?>">
            <input type="hidden" name="shortname" value="<?= htmlspecialchars($editTeam['shortname']) ?>">

            <div class="form-group">
                <label>Namn:</label>
                <strong><?= htmlspecialchars($editTeam['name']) ?></strong>
            </div>
            <div class="form-group">
                <label>Nuvarande logotyp:</label><br>
                <?php if ($editTeam['logo']): ?>
                    <img src="logo/<?= $editTeam['logo'] ?>" height="60">
                <?php else: ?>
                    <span class="text-muted">Ingen logotyp uppladdad</span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Ladda upp ny logotyp</label>
                <input type="file" name="logo" class="form-control-file">
            </div>
            <div class="form-group">
                <label>Färg 1</label>
                <input type="color" name="color1" value="<?= htmlspecialchars($editTeam['color1']) ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Färg 2</label>
                <input type="color" name="color2" value="<?= htmlspecialchars($editTeam['color2']) ?>" class="form-control">
            </div>
            <button type="submit" class="btn btn-success">Spara</button>
            <a href="501_edit_incomplete_team.php" class="btn btn-secondary">Avbryt</a>
        </form>
    <?php endif; ?>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
        <tr>
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
                <td>
                    <?php if ($team['logo']): ?>
                        <img src="logo/<?= $team['logo'] ?>" height="40">
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($team['name']) ?></td>
                <td><?= htmlspecialchars($team['city']) ?></td>
                <td><?= htmlspecialchars($team['shortname']) ?></td>
                <td style="background: <?= $team['color1'] ?>"><?= $team['color1'] ?></td>
                <td style="background: <?= $team['color2'] ?>"><?= $team['color2'] ?></td>
                <td><a href="?edit=<?= $team['id'] ?>" class="btn btn-sm btn-primary">Redigera</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
