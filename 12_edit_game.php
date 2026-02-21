<?php
// 12_edit_game.php
// Edit a single game (basic fields). Switch teams via review_team.

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Edit game (review_game)', 'review', 0, 12);

$pdo = getDatabaseConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: 11_manage_games_teams.php'); exit; }

$msg = '';
$teams = $pdo->query("SELECT id, name FROM review_team ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Load game
$st = $pdo->prepare("
    SELECT id, date, time, sport, home_team_id, away_team_id
    FROM review_game
    WHERE id = ?
    LIMIT 1
");
$st->execute([$id]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game) { header('Location: 11_manage_games_teams.php'); exit; }

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = $_POST['date'] ?? '';
    $time  = $_POST['time'] ?? '';
    $sport = $_POST['sport'] ?? '';
    $home  = (int)($_POST['home_team_id'] ?? 0);
    $away  = (int)($_POST['away_team_id'] ?? 0);

    if ($date && $time && $sport && $home > 0 && $away > 0) {
        $upd = $pdo->prepare("
            UPDATE review_game
            SET date = ?, time = ?, sport = ?, home_team_id = ?, away_team_id = ?, update_date = NOW()
            WHERE id = ?
        ");
        $upd->execute([$date, $time, $sport, $home, $away, $id]);
        $msg = '✅ Saved.';
        // Refresh game data
        $st->execute([$id]);
        $game = $st->fetch(PDO::FETCH_ASSOC);
    } else {
        $msg = '⚠️ Fill all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Game #<?= (int)$id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
      crossorigin="anonymous">
</head>
<body class="p-4">
<div class="container" style="max-width:700px">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Edit Game #<?= (int)$id ?></h4>
    <a href="11_manage_games_teams.php" class="btn btn-link ml-2">Back</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="post">
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Date</label>
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($game['date']) ?>" required>
      </div>
      <div class="form-group col-md-6">
        <label>Time</label>
        <input type="time" name="time" class="form-control" value="<?= htmlspecialchars($game['time']) ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Home team</label>
        <select name="home_team_id" class="form-control" required>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $t['id']==$game['home_team_id']?'selected':'' ?>>
              <?= htmlspecialchars($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-6">
        <label>Away team</label>
        <select name="away_team_id" class="form-control" required>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $t['id']==$game['away_team_id']?'selected':'' ?>>
              <?= htmlspecialchars($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Sport</label>
      <input type="text" name="sport" class="form-control" value="<?= htmlspecialchars($game['sport'] ?? 'American Football') ?>" required>
    </div>

    <button class="btn btn-primary">Save</button>
  </form>
</div>
</body>
</html>
