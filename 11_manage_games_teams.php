<?php
// 11_manage_games_teams.php
// Lists games (review_game) and teams (review_team) with Edit links.

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'List games and teams with edit links', 'review', 1, 11);

$pdo = getDatabaseConnection();

// Fetch games (basic columns)
$games = $pdo->query("
    SELECT g.id, g.date, g.time, g.sport,
           ht.name AS home_name, at.name AS away_name
    FROM review_game g
    LEFT JOIN review_team ht ON g.home_team_id = ht.id
    LEFT JOIN review_team at ON g.away_team_id = at.id
    ORDER BY g.date DESC, g.time DESC, g.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch teams
$teams = $pdo->query("
    SELECT id, name
    FROM review_team
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Games & Teams</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
      crossorigin="anonymous">
</head>
<body class="p-4">
<div class="container">
  <div class="d-flex align-items-center mb-3">
    <h3 class="mb-0">Manage Games & Teams</h3>
    <a class="btn btn-link ml-3" href="10_add_simple_game.php">+ Add Game</a>
  </div>

  <!-- Games -->
  <h5 class="mt-3">Games</h5>
  <div class="table-responsive">
    <table class="table table-sm table-bordered">
      <thead class="thead-light">
        <tr>
          <th class="text-right">ID</th>
          <th>Date</th>
          <th>Time</th>
          <th>Home</th>
          <th>Away</th>
          <th>Sport</th>
          <th class="text-center">Edit</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$games): ?>
        <tr><td colspan="7" class="text-muted">No games yet.</td></tr>
      <?php else: ?>
        <?php foreach ($games as $g): ?>
          <tr>
            <td class="text-right"><?= (int)$g['id'] ?></td>
            <td><?= htmlspecialchars($g['date']) ?></td>
            <td><?= htmlspecialchars($g['time']) ?></td>
            <td><?= htmlspecialchars($g['home_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['away_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['sport'] ?? '') ?></td>
            <td class="text-center">
              <a class="btn btn-sm btn-outline-primary" href="12_edit_game.php?id=<?= (int)$g['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Teams -->
  <div class="d-flex align-items-center mt-4">
    <h5 class="mb-0">Teams</h5>
    <a class="btn btn-link ml-2" href="13_edit_team.php">+ Add Team</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-bordered">
      <thead class="thead-light">
        <tr>
          <th class="text-right">ID</th>
          <th>Name</th>
          <th class="text-center">Edit</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$teams): ?>
        <tr><td colspan="3" class="text-muted">No teams yet.</td></tr>
      <?php else: ?>
        <?php foreach ($teams as $t): ?>
          <tr>
            <td class="text-right"><?= (int)$t['id'] ?></td>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td class="text-center">
              <a class="btn btn-sm btn-outline-secondary" href="13_edit_team.php?id=<?= (int)$t['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
