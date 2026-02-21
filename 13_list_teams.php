<?php
// 13_list_teams.php
// Description: List all teams from review_team with logo and color chips.
// Calls:      13_edit_team.php (opens editor with ?id=...)
// Called by:  -
// Notes:      Uses PDO getDatabaseConnection(); logos are loaded from /logo.

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'List teams (review_team) with logo & colors; link to 13_edit_team.php', 'review', 0, 13);

$pdo = getDatabaseConnection();

// Fetch teams
$stmt = $pdo->query("SELECT id, name, city, shortname, logo, color1, color2, update_date FROM review_team ORDER BY name ASC");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teams – list</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
      crossorigin="anonymous">
<style>
  .logo-box { width:52px; height:52px; border:1px solid #ddd; border-radius:6px; background:#fff;
              display:flex; align-items:center; justify-content:center; overflow:hidden; }
  .logo-box img { max-width:100%; max-height:100%; object-fit:contain; }
  .chip { width:28px; height:28px; border:1px solid rgba(0,0,0,.15); border-radius:6px; display:inline-block; }
  .nowrap { white-space: nowrap; }
  .table-sm td, .table-sm th { vertical-align: middle; }
</style>
</head>
<body class="p-4">
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Teams</h4>
    <div>
      <a href="13_edit_team.php" class="btn btn-primary">+ Add new team</a>
    </div>
  </div>

  <!-- Quick client-side filter -->
  <div class="form-row mb-3">
    <div class="col-md-4">
      <input id="filterInput" type="text" class="form-control" placeholder="Filter by team, city or shortname…">
    </div>
  </div>

  <?php if (empty($teams)): ?>
    <div class="alert alert-warning">No teams found.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover" id="teamsTable">
        <thead class="thead-light">
          <tr>
            <th class="text-center">Logo</th>
            <th>Name</th>
            <th>City</th>
            <th>Short</th>
            <th class="nowrap">Color 1</th>
            <th class="nowrap">Color 2</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($teams as $t): 
              $logo = trim((string)($t['logo'] ?? ''));
              $logoRel = $logo ? 'logo/' . $logo : '';
              $logoOk  = $logo && file_exists(__DIR__ . '/' . $logoRel);
              $c1 = trim((string)($t['color1'] ?? ''));
              $c2 = trim((string)($t['color2'] ?? ''));
              $bg1 = $c1 !== '' ? safe($c1) : '#f0f0f0';
              $bg2 = $c2 !== '' ? safe($c2) : '#f0f0f0';
        ?>
          <tr data-filter="<?= safe(($t['name'] ?? '') . ' ' . ($t['city'] ?? '') . ' ' . ($t['shortname'] ?? '')) ?>">
            <td class="text-center">
              <div class="logo-box">
                <?php if ($logoOk): ?>
                  <img src="<?= safe($logoRel) ?>" alt="Logo">
                <?php else: ?>
                  <span class="text-muted" title="No logo">—</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <a href="13_edit_team.php?id=<?= (int)$t['id'] ?>" title="Edit this team">
                <?= safe($t['name']) ?>
              </a>
            </td>
            <td><?= safe($t['city'] ?? '') ?></td>
            <td><?= safe($t['shortname'] ?? '') ?></td>
            <td class="nowrap">
              <span class="chip mr-2" style="background: <?= $bg1 ?>"></span>
              <small class="text-monospace"><?= $c1 !== '' ? safe($c1) : '—' ?></small>
            </td>
            <td class="nowrap">
              <span class="chip mr-2" style="background: <?= $bg2 ?>"></span>
              <small class="text-monospace"><?= $c2 !== '' ? safe($c2) : '—' ?></small>
            </td>
            <td class="text-right">
              <a class="btn btn-sm btn-outline-primary" href="13_edit_team.php?id=<?= (int)$t['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
// Simple client-side filter on name/city/shortname
document.getElementById('filterInput').addEventListener('input', function() {
  var q = this.value.toLowerCase();
  document.querySelectorAll('#teamsTable tbody tr').forEach(function(row){
    var s = (row.getAttribute('data-filter') || '').toLowerCase();
    row.style.display = s.indexOf(q) !== -1 ? '' : 'none';
  });
});
</script>
</body>
</html>
