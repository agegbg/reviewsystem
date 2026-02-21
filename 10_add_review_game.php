<?php
// 10_add_simple_game.php
// Minimal page to add a review_game using review_team (home, away, date, time, sport)

require_once __DIR__ . '/php/db.php';
$pdo = getDatabaseConnection();

$msg = '';

// Load existing teams from review_team for selects/datalist
$teams = $pdo->query("SELECT id, name FROM review_team ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

/** Return team id from review_team by name (case-insensitive), or null */
function find_team_id(PDO $pdo, string $name): ?int {
    $st = $pdo->prepare("SELECT id FROM review_team WHERE LOWER(name)=LOWER(?) LIMIT 1");
    $st->execute([trim($name)]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

/** Get-or-create team in review_team, return id */
function get_or_create_team(PDO $pdo, string $name): int {
    $name = trim($name);
    if ($name === '') throw new RuntimeException("Team name is empty.");
    if ($id = find_team_id($pdo, $name)) return $id;

    $ins = $pdo->prepare("INSERT INTO review_team (name, create_date, update_date) VALUES (?, NOW(), NOW())");
    $ins->execute([$name]);
    return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = $_POST['date'] ?? '';
    $time  = $_POST['time'] ?? '';
    $sport = $_POST['sport'] ?? 'American Football';

    // Prefer selected IDs; if empty, use typed name (and create if needed)
    $home_id = ($_POST['home_id'] !== '') ? (int)$_POST['home_id'] : null;
    $away_id = ($_POST['away_id'] !== '') ? (int)$_POST['away_id'] : null;
    $home_name = trim($_POST['home_name'] ?? '');
    $away_name = trim($_POST['away_name'] ?? '');

    if (!$home_id && $home_name !== '') $home_id = get_or_create_team($pdo, $home_name);
    if (!$away_id && $away_name !== '') $away_id = get_or_create_team($pdo, $away_name);

    if ($home_id && $away_id && $date && $time && $sport) {
        $stmt = $pdo->prepare("
            INSERT INTO review_game
                (date, time, home_team_id, away_team_id, sport, create_date, update_date)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$date, $time, $home_id, $away_id, $sport]);
        $msg = "✅ Game added (ID " . $pdo->lastInsertId() . ").";
    } else {
        $msg = "⚠️ Please select/type both teams and fill date, time, sport.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Game (review_team)</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
</head>
<body class="p-4">
<div class="container" style="max-width:720px">
  <h3 class="mb-3">Add Game</h3>

  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <!-- Teams -->
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Home team</label>
        <select name="home_id" class="form-control">
          <option value="">— Select existing —</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">or type a new/existing name:</small>
        <input list="team_names" name="home_name" class="form-control mt-1" placeholder="Home team name">
      </div>
      <div class="form-group col-md-6">
        <label>Away team</label>
        <select name="away_id" class="form-control">
          <option value="">— Select existing —</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">or type a new/existing name:</small>
        <input list="team_names" name="away_name" class="form-control mt-1" placeholder="Away team name">
      </div>
    </div>
    <datalist id="team_names">
      <?php foreach ($teams as $t): ?>
        <option value="<?= htmlspecialchars($t['name']) ?>"></option>
      <?php endforeach; ?>
    </datalist>

    <!-- Date / Time -->
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Date</label>
        <input type="date" name="date" class="form-control" required>
      </div>
      <div class="form-group col-md-6">
        <label>Time</label>
        <input type="time" name="time" class="form-control" required>
      </div>
    </div>

    <!-- Sport -->
    <div class="form-group">
      <label>Sport</label>
      <input type="text" name="sport" class="form-control" value="American Football" required>
    </div>

    <button class="btn btn-primary">Save</button>
  </form>
</div>
</body>
</html>
