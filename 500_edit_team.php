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

    header("Location: 500_edit_team.php");
    exit;
}

// Get team list
$teams = $pdo->query("SELECT * FROM review_team ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Edit Teams</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>Edit Teams</h2>

    <?php if ($editTeam): ?>
        <form method="post" enctype="multipart/form-data" class="mb-4">
            <input type="hidden" name="id" value="<?= $editTeam['id'] ?>">
            <input type="hidden" name="shortname" value="<?= htmlspecialchars($editTeam['shortname']) ?>">

            <div class="form-group">
                <label>Name:</label>
                <strong><?= htmlspecialchars($editTeam['name']) ?></strong>
            </div>
            <div class="form-group">
                <label>Current logo:</label><br>
                <?php if ($editTeam['logo']): ?>
                    <img src="logo/<?= $editTeam['logo'] ?>" height="60">
                <?php else: ?>
                    <span class="text-muted">No logo uploaded</span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Upload New Logo</label>
                <input type="file" name="logo" class="form-control-file">
            </div>
            <div class="form-group">
                <label>Color 1</label>
                <input type="color" name="color1" value="<?= htmlspecialchars($editTeam['color1']) ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Color 2</label>
                <input type="color" name="color2" value="<?= htmlspecialchars($editTeam['color2']) ?>" class="form-control">
            </div>
            <button type="submit" class="btn btn-success">Save</button>
            <a href="500_edit_team.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th>Logo</th>
                <th>Name</th>
                <th>City</th>
                <th>Short</th>
                <th>Color1</th>
                <th>Color2</th>
                <th>Edit</th>
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
                <td><a href="?edit=<?= $team['id'] ?>" class="btn btn-sm btn-primary">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
