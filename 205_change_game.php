<?php
// 205_change_game.php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$users = $pdo->query("SELECT id, name FROM review_user ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);

$search = $_GET['search'] ?? '';
$game_id = $_GET['game_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['game_id'])) {
    $stmt = $pdo->prepare("UPDATE review_game SET link = ?, place = ?, weather = ?, start_time = ?, end_time = ?, sport = ? WHERE id = ?");
    $stmt->execute([
        $_POST['link'], $_POST['place'], $_POST['weather'], $_POST['start_time'], $_POST['end_time'], $_POST['sport'], $_POST['game_id']
    ]);

    $stmt = $pdo->prepare("UPDATE review_evaluation SET user_id = ? WHERE game_id = ? AND is_head_evaluator = 1");
    $stmt->execute([
        $_POST['reviewer_id'], $_POST['game_id']
    ]);
    echo "<div class='alert alert-success'>Game updated successfully!</div>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Game Info</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Edit Game Info</h2>

  <form method="get" class="mb-3">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search team name...">
  </form>

  <?php
  if (!$game_id && $search) {
      $stmt = $pdo->prepare("SELECT g.id, g.date, t1.name AS home_name, t2.name AS away_name FROM review_game g LEFT JOIN review_team t1 ON g.home_team_id = t1.id LEFT JOIN review_team t2 ON g.away_team_id = t2.id WHERE t1.name LIKE ? OR t2.name LIKE ? ORDER BY g.date DESC");
      $stmt->execute(["%$search%", "%$search%"]);
      $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<ul class='list-group'>";
      foreach ($games as $g) {
          echo "<li class='list-group-item'><a href='205_change_game.php?game_id={$g['id']}'>{$g['home_name']} vs {$g['away_name']} ({$g['date']})</a></li>";
      }
      echo "</ul>";
  }

  if ($game_id) {
      $stmt = $pdo->prepare("SELECT * FROM review_game WHERE id = ?");
      $stmt->execute([$game_id]);
      $game = $stmt->fetch(PDO::FETCH_ASSOC);

      $stmt = $pdo->prepare("SELECT * FROM review_evaluation WHERE game_id = ? AND is_head_evaluator = 1");
      $stmt->execute([$game_id]);
      $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
  ?>
  <form method="post" class="mt-4">
    <input type="hidden" name="game_id" value="<?= $game_id ?>">
    <div class="form-group">
      <label>Link to Stream</label>
      <input type="text" name="link" class="form-control" value="<?= htmlspecialchars($game['link'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Place</label>
      <input type="text" name="place" class="form-control" value="<?= htmlspecialchars($game['place'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Weather</label>
      <input type="text" name="weather" class="form-control" value="<?= htmlspecialchars($game['weather'] ?? '') ?>">
    </div>
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Start Time</label>
        <input type="time" name="start_time" class="form-control" value="<?= $game['start_time'] ?>">
      </div>
      <div class="form-group col-md-6">
        <label>End Time</label>
        <input type="time" name="end_time" class="form-control" value="<?= $game['end_time'] ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Sport</label>
      <input type="text" name="sport" class="form-control" value="<?= htmlspecialchars($game['sport'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Evaluator</label>
      <select name="reviewer_id" class="form-control">
        <option value="">-- Select Evaluator --</option>
        <?php foreach ($users as $id => $name): ?>
          <option value="<?= $id ?>" <?= ($evaluation['user_id'] ?? null) == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
  <?php } ?>
</div>
</body>
</html>
