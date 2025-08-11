<?php
// 21_ref.php
// Simple referee profile page by ID: shows basic info and aggregated counts per position.

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Referee profile with per-position counts.');

$pdo = getDatabaseConnection();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('Missing or invalid id'); }

// Load user
$u = $pdo->prepare("SELECT id, name, email, city, phone, license, picture FROM review_user WHERE id = ?");
$u->execute([$id]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) { http_response_code(404); die('User not found'); }

// Counts per role for this user
$counts = [
  'R'  => 0, 'U' => 0, 'LJ' => 0, 'DJ' => 0,
  'FJ' => 0, 'SJ'=> 0, 'BJ' => 0, 'C'  => 0, 'Reviewer' => 0
]; 

$q = $pdo->prepare("
  SELECT
    (SELECT COUNT(*) FROM review_crew WHERE referee_id = ?)  AS c_r,
    (SELECT COUNT(*) FROM review_crew WHERE umpire_id  = ?)  AS c_u,
    (SELECT COUNT(*) FROM review_crew WHERE lj_id      = ?)  AS c_lj,
    (SELECT COUNT(*) FROM review_crew WHERE hl_id      = ?)  AS c_dj,
    (SELECT COUNT(*) FROM review_crew WHERE fj_id      = ?)  AS c_fj,
    (SELECT COUNT(*) FROM review_crew WHERE sj_id      = ?)  AS c_sj,
    (SELECT COUNT(*) FROM review_crew WHERE bj_id      = ?)  AS c_bj,
    (SELECT COUNT(*) FROM review_crew WHERE cj_id      = ?)  AS c_c,
    (SELECT COUNT(*) FROM review_evaluation WHERE user_id = ?) AS c_rev
");
$q->execute([$id,$id,$id,$id,$id,$id,$id,$id,$id]);
$c = $q->fetch(PDO::FETCH_ASSOC);
$counts['R']  = (int)$c['c_r'];
$counts['U']  = (int)$c['c_u'];
$counts['LJ'] = (int)$c['c_lj'];
$counts['DJ'] = (int)$c['c_dj'];
$counts['FJ'] = (int)$c['c_fj'];
$counts['SJ'] = (int)$c['c_sj'];
$counts['BJ'] = (int)$c['c_bj'];
$counts['C']  = (int)$c['c_c'];
$counts['Reviewer'] = (int)$c['c_rev'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Referee: <?= htmlspecialchars($user['name']) ?></title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
<style>.avatar{width:96px;height:96px;object-fit:cover;border-radius:50%}</style>
</head>
<body class="p-4">
<div class="container">
  <a href="20_ref.php" class="btn btn-link mb-3">&larr; Back to list</a>
  <div class="d-flex align-items-center mb-3">
    <div class="mr-3">
      <?php if ($user['picture']): ?>
        <img class="avatar" src="photo/<?= htmlspecialchars($user['picture']) ?>" alt="Photo">
      <?php else: ?>
        <div class="avatar bg-light d-flex align-items-center justify-content-center">—</div>
      <?php endif; ?>
    </div>
    <div>
      <h2 class="mb-1"><?= htmlspecialchars($user['name']) ?></h2>
      <div><?= htmlspecialchars($user['city'] ?? '') ?></div>
      <div><a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a></div>
      <div><?= htmlspecialchars($user['phone'] ?? '') ?></div>
      <div>License: <?= htmlspecialchars($user['license'] ?? '') ?></div>
    </div>
  </div>

  <h4 class="mt-4">Role Counts</h4>
  <table class="table table-bordered table-sm" style="max-width:560px">
    <thead class="thead-light">
      <tr><th>R</th><th>U</th><th>LJ</th><th>DJ</th><th>FJ</th><th>SJ</th><th>BJ</th><th>C</th><th>Reviewer</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><?= $counts['R'] ?></td>
        <td><?= $counts['U'] ?></td>
        <td><?= $counts['LJ'] ?></td>
        <td><?= $counts['DJ'] ?></td>
        <td><?= $counts['FJ'] ?></td>
        <td><?= $counts['SJ'] ?></td>
        <td><?= $counts['BJ'] ?></td>
        <td><?= $counts['C'] ?></td>
        <td><?= $counts['Reviewer'] ?></td>
      </tr>
    </tbody>
  </table>
</div>
</body>
</html>
