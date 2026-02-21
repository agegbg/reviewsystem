<?php
// 13_edit_team.php
// Description: Create or edit a team in review_team (name, city, shortname, colors, logo).
// Calls:      (optional) 13_list_teams.php (as a "Back" link)
// Notes:      Uses PDO getDatabaseConnection(); uploads logo to /logo; safe delete if unused.

// --- Session & DB ---
require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Edit team (review_team): name, city, shortname, colors, logo. Safe delete.');

$pdo = getDatabaseConnection();

// --- Helpers ---
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '_');
    $text = preg_replace('~_+~', '_', $text);
    return strtolower($text) ?: 'team';
}
function nullIfEmpty($v) { $v = trim((string)$v); return ($v === '') ? null : $v; }

// --- Load current team if editing ---
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$team = [
    'name'      => '',
    'city'      => null,
    'shortname' => null,
    'logo'      => null,
    'color1'    => null,
    'color2'    => null
];
if ($id > 0) {
    $st = $pdo->prepare("SELECT id, name, city, shortname, logo, color1, color2 FROM review_team WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) { $team = $row; } else { header('Location: 13_list_teams.php'); exit; }
}

$msg  = '';
$err  = '';

// --- Handle POST (Save / Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Distinguish actions by button name
    if (isset($_POST['delete']) && $id > 0) {
        // Safety: block delete if team is used in review_game
        $check = $pdo->prepare("SELECT 
                                  SUM(CASE WHEN home_team_id = ? THEN 1 ELSE 0 END) +
                                  SUM(CASE WHEN away_team_id = ? THEN 1 ELSE 0 END) AS cnt
                                FROM review_game");
        $check->execute([$id, $id]);
        $cnt = (int)($check->fetchColumn() ?: 0);

        if ($cnt > 0) {
            $err = "Cannot delete: team is referenced by $cnt game(s). Remove/replace it from games first.";
        } else {
            // Remove logo file if present
            if (!empty($team['logo']) && file_exists(__DIR__ . '/logo/' . $team['logo'])) {
                @unlink(__DIR__ . '/logo/' . $team['logo']);
            }
            $del = $pdo->prepare("DELETE FROM review_team WHERE id = ? LIMIT 1");
            $del->execute([$id]);
            $msg = '✅ Team deleted.';
            // After delete, bounce back to list (or show empty form)
            header('Location: 13_list_teams.php');
            exit;
        }
    } else {
        // Save (create or update)
        $name      = trim($_POST['name'] ?? '');
        $city      = nullIfEmpty($_POST['city'] ?? '');
        $shortname = nullIfEmpty($_POST['shortname'] ?? '');
        $color1    = nullIfEmpty($_POST['color1'] ?? '');
        $color2    = nullIfEmpty($_POST['color2'] ?? '');
        $logoName  = $team['logo'] ?? null;

        if ($name === '') {
            $err = 'Name is required.';
        } else {
            // Handle "remove logo" toggle
            if (!empty($_POST['remove_logo']) && $logoName) {
                $path = __DIR__ . '/logo/' . $logoName;
                if (file_exists($path)) { @unlink($path); }
                $logoName = null;
            }

            // Handle new logo upload
            if (!empty($_FILES['logo']['name']) && isset($_FILES['logo']['error']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['png','jpg','jpeg','webp','gif'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed, true)) {
                    // Ensure /logo exists
                    $dir = __DIR__ . '/logo';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    // Build filename
                    $base = $name !== '' ? $name : ($shortname ?: ('team_' . ($id ?: 'new')));
                    $safe = slugify($base) . '.' . $ext;
                    $dest = $dir . '/' . $safe;

                    // If file exists, add a timestamp suffix
                    if (file_exists($dest)) {
                        $safe = slugify($base) . '_' . date('Ymd_His') . '.' . $ext;
                        $dest = $dir . '/' . $safe;
                    }
                    if (@move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        // Remove old logo if different
                        if ($logoName && $logoName !== $safe && file_exists($dir . '/' . $logoName)) {
                            @unlink($dir . '/' . $logoName);
                        }
                        $logoName = $safe;
                    } else {
                        $err = 'Failed to store uploaded logo (permissions/path).';
                    }
                } else {
                    $err = 'Invalid logo type. Allowed: png, jpg, jpeg, webp, gif.';
                }
            }

            // Persist if no error so far
            if ($err === '') {
                if ($id > 0) {
                    $sql = "UPDATE review_team 
                               SET name = ?, city = ?, shortname = ?, logo = ?, color1 = ?, color2 = ?, update_date = NOW()
                             WHERE id = ?";
                    $pdo->prepare($sql)->execute([$name, $city, $shortname, $logoName, $color1, $color2, $id]);
                    $msg = '✅ Saved.';
                } else {
                    $sql = "INSERT INTO review_team (name, city, shortname, logo, color1, color2, create_date, update_date)
                            VALUES (?,?,?,?,?,?, NOW(), NOW())";
                    $pdo->prepare($sql)->execute([$name, $city, $shortname, $logoName, $color1, $color2]);
                    $id = (int)$pdo->lastInsertId();
                    $msg = '✅ Team created (ID ' . $id . ').';
                }
                // Reload fresh values after save
                $st = $pdo->prepare("SELECT id, name, city, shortname, logo, color1, color2 FROM review_team WHERE id = ? LIMIT 1");
                $st->execute([$id]);
                $team = $st->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}

// For color chips
$c1 = trim((string)($team['color1'] ?? ''));
$c2 = trim((string)($team['color2'] ?? ''));
$chip1 = $c1 !== '' ? htmlspecialchars($c1) : '#f0f0f0';
$chip2 = $c2 !== '' ? htmlspecialchars($c2) : '#f0f0f0';
$logoFile = $team['logo'] ?? null;
$logoPathRel = $logoFile ? ('logo/' . $logoFile) : '';
$logoExists  = $logoFile && file_exists(__DIR__ . '/' . $logoPathRel);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $id>0 ? 'Edit Team' : 'Add Team' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
      crossorigin="anonymous">
<style>
  .logo-box{width:100px;height:100px;border:1px solid #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fff;object-fit:contain}
  .color-chip{width:32px;height:32px;border:1px solid rgba(0,0,0,.15);border-radius:6px;display:inline-block;vertical-align:middle;margin-right:8px;background:#f0f0f0}
</style>
</head>
<body class="p-4">
<div class="container" style="max-width:820px">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0"><?= $id>0 ? 'Edit Team' : 'Add Team' ?></h4>
    <a href="13_list_teams.php" class="btn btn-link ml-2">Back</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="border rounded p-3 bg-light">
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Name *</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($team['name'] ?? '') ?>" required>
      </div>
      <div class="form-group col-md-6">
        <label>City</label>
        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($team['city'] ?? '') ?>">
      </div>
      <div class="form-group col-md-6">
        <label>Short name</label>
        <input type="text" name="shortname" class="form-control" value="<?= htmlspecialchars($team['shortname'] ?? '') ?>">
      </div>
      <div class="form-group col-md-3">
        <label>Color 1</label>
        <div class="d-flex align-items-center">
          <span class="color-chip" style="background: <?= $chip1 ?>"></span>
          <input type="text" name="color1" class="form-control" placeholder="#112233 or red" value="<?= htmlspecialchars($team['color1'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group col-md-3">
        <label>Color 2</label>
        <div class="d-flex align-items-center">
          <span class="color-chip" style="background: <?= $chip2 ?>"></span>
          <input type="text" name="color2" class="form-control" placeholder="#aabbcc or blue" value="<?= htmlspecialchars($team['color2'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="form-row align-items-center">
      <div class="form-group col-md-3 text-center">
        <?php if ($logoExists): ?>
          <img src="<?= htmlspecialchars($logoPathRel) ?>" class="logo-box" alt="Logo">
        <?php else: ?>
          <div class="logo-box">No logo</div>
        <?php endif; ?>
      </div>
      <div class="form-group col-md-9">
        <label>Logo (png/jpg/jpeg/webp/gif)</label>
        <div class="custom-file">
          <input type="file" class="custom-file-input" name="logo" id="logo_file">
          <label class="custom-file-label" for="logo_file">Choose file</label>
        </div>
        <?php if ($logoExists): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo">
            <label for="remove_logo" class="form-check-label">Remove current logo</label>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
      <div>
        <button type="submit" class="btn btn-primary"><?= $id>0 ? 'Save' : 'Create' ?></button>
        <?php if ($id>0): ?>
          <a class="btn btn-outline-secondary ml-1" href="?id=<?= (int)$id ?>">Reset</a>
        <?php endif; ?>
      </div>
      <?php if ($id>0): ?>
        <button type="submit" name="delete" value="1" class="btn btn-danger"
                onclick="return confirm('Delete this team? This cannot be undone.');">
          Delete team
        </button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($id>0): ?>
    <p class="text-muted mt-3 mb-0">
      Tip: Deleting is blocked if the team is used in <code>review_game</code> as home or away. 
      Update the games first if you really need to remove the team. (Tables per DB dump.) 
    </p>
  <?php endif; ?>
</div>

<script>
document.addEventListener('change', function(e){
  if (e.target && e.target.classList.contains('custom-file-input')) {
    var label = e.target.nextElementSibling;
    if (label && e.target.files && e.target.files.length > 0) {
      label.textContent = e.target.files[0].name;
    }
  }
});
</script>
</body>
</html>
