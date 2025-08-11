<?php
// 01_mypage.php
// Personal landing page for the logged-in user.
// Header shows avatar, contact, city and license pulled primarily from review_user_info,
// with fallback to review_user. Also shows role counts and personal menu.

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'My Page: identity from user + user_info, role counts, and quick menu.');

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header('Location: 00_login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$pdo = getDatabaseConnection();

// Load base user
$u = $pdo->prepare("SELECT id, name, email, city, phone, license, picture FROM review_user WHERE id = ?");
$u->execute([$user_id]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) { session_destroy(); header('Location: 00_login.php'); exit; }

// Load extended info (photo/city/license_number)
$inf = $pdo->prepare("SELECT photo, city AS info_city, license_number FROM review_user_info WHERE user_id = ?");
$inf->execute([$user_id]);
$info = $inf->fetch(PDO::FETCH_ASSOC) ?: ['photo'=>null,'info_city'=>null,'license_number'=>null];

// Decide what to show (prefer user_info values)
$photo    = $info['photo'] ?: $user['picture'];          // user_info.photo > user.picture
$city     = $info['info_city'] ?: $user['city'];          // user_info.city > user.city
$license  = $info['license_number'] ?: $user['license'];  // user_info.license_number > user.license

// Role counts for this user (R, U, LJ, DJ(HL), FJ, SJ, BJ, C, Reviewer)
$c = $pdo->prepare("
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
$params = array_fill(0, 9, $user_id);
$c->execute($params);
$counts = $c->fetch(PDO::FETCH_ASSOC);

// helper
function sv($a,$k,$d=0){ return isset($a[$k]) ? (int)$a[$k] : $d; }

// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Page – <?= htmlspecialchars($user['name']) ?></title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
<style>
  .avatar { width:96px;height:96px;object-fit:cover;border-radius:50%;background:#f1f3f5;display:flex;align-items:center;justify-content:center;font-weight:600;color:#999; }
  .counts th, .counts td { text-align:center; }
  .counts th { background:#f8f9fa; }
  .btn-wide { min-width: 160px; }
</style>
</head>
<body class="p-4">
<div class="container">

  <!-- Header (same look as 21_ref) -->
  <div class="d-flex align-items-center mb-3">
    <div class="mr-3">
      <?php if (!empty($photo)): ?>
        <img src="photo/<?= htmlspecialchars($photo) ?>" alt="Photo" class="avatar">
      <?php else: ?>
        <div class="avatar">—</div>
      <?php endif; ?>
    </div>
    <div>
      <h2 class="mb-1">Welcome <?= htmlspecialchars($user['name']) ?>!</h2>
      <?php if (!empty($user['email'])): ?>
        <div><a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a></div>
      <?php endif; ?>
      <?php if (!empty($user['phone'])): ?>
        <div><?= htmlspecialchars($user['phone']) ?></div>
      <?php endif; ?>
      <?php if (!empty($city)): ?>
        <div>City: <?= htmlspecialchars($city) ?></div>
      <?php endif; ?>
      <?php if (!empty($license)): ?>
        <div>License: <?= htmlspecialchars($license) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Role Counts -->
  <h4 class="mb-3">Role Counts</h4>
  <table class="table table-bordered table-sm counts" style="max-width:720px">
    <thead>
      <tr>
        <th>R</th><th>U</th><th>LJ</th><th>DJ</th>
        <th>FJ</th><th>SJ</th><th>BJ</th><th>C</th><th>Reviewer</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?= sv($counts,'c_r')   ?></td>
        <td><?= sv($counts,'c_u')   ?></td>
        <td><?= sv($counts,'c_lj')  ?></td>
        <td><?= sv($counts,'c_dj')  ?></td>
        <td><?= sv($counts,'c_fj')  ?></td>
        <td><?= sv($counts,'c_sj')  ?></td>
        <td><?= sv($counts,'c_bj')  ?></td>
        <td><?= sv($counts,'c_c')   ?></td>
        <td><?= sv($counts,'c_rev') ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Personal quick menu -->
  <div class="mt-4 d-flex flex-wrap">
    <a href="201_review_list.php" class="btn btn-success mr-3 mb-2 btn-wide">My Games</a>
    <a href="01_editmypage.php" class="btn btn-secondary mr-3 mb-2 btn-wide">My Info</a>
    <a href="00_login.php?logout=1" class="btn btn-outline-danger mb-2 btn-wide">Log Out</a>
  </div>

</div>
</body>
</html>
