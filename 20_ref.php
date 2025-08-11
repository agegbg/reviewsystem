<?php
// 20_ref.php
// Referee statistics per user: counts for R, U, LJ, DJ(HL), FJ, SJ, BJ, C(CJ) + Reviewer.
// Shows only active users (at least one count). Name links to 21_stats.php?id=...

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Referee statistics for all positions (R, U, LJ, DJ/HL, FJ, SJ, BJ, C) + reviews.');

$pdo = getDatabaseConnection();

$sql = "
    SELECT
        u.id,
        u.name,
        COALESCE(r_ref.cnt, 0) AS cnt_r,
        COALESCE(r_ump.cnt, 0) AS cnt_u,
        COALESCE(r_lj.cnt, 0)  AS cnt_lj,
        COALESCE(r_hl.cnt, 0)  AS cnt_dj,   -- HL = Down Judge
        COALESCE(r_fj.cnt, 0)  AS cnt_fj,
        COALESCE(r_sj.cnt, 0)  AS cnt_sj,
        COALESCE(r_bj.cnt, 0)  AS cnt_bj,
        COALESCE(r_cj.cnt, 0)  AS cnt_c,
        COALESCE(rev.cnt, 0)   AS cnt_reviewer
    FROM review_user u
    LEFT JOIN (SELECT referee_id AS user_id, COUNT(*) AS cnt FROM review_crew WHERE referee_id IS NOT NULL GROUP BY referee_id) r_ref ON u.id = r_ref.user_id
    LEFT JOIN (SELECT umpire_id  AS user_id, COUNT(*) AS cnt FROM review_crew WHERE umpire_id  IS NOT NULL GROUP BY umpire_id) r_ump ON u.id = r_ump.user_id
    LEFT JOIN (SELECT lj_id      AS user_id, COUNT(*) AS cnt FROM review_crew WHERE lj_id      IS NOT NULL GROUP BY lj_id) r_lj  ON u.id = r_lj.user_id
    LEFT JOIN (SELECT hl_id      AS user_id, COUNT(*) AS cnt FROM review_crew WHERE hl_id      IS NOT NULL GROUP BY hl_id) r_hl  ON u.id = r_hl.user_id
    LEFT JOIN (SELECT fj_id      AS user_id, COUNT(*) AS cnt FROM review_crew WHERE fj_id      IS NOT NULL GROUP BY fj_id) r_fj  ON u.id = r_fj.user_id
    LEFT JOIN (SELECT sj_id      AS user_id, COUNT(*) AS cnt FROM review_crew WHERE sj_id      IS NOT NULL GROUP BY sj_id) r_sj  ON u.id = r_sj.user_id
    LEFT JOIN (SELECT bj_id      AS user_id, COUNT(*) AS cnt FROM review_crew WHERE bj_id      IS NOT NULL GROUP BY bj_id) r_bj  ON u.id = r_bj.user_id
    LEFT JOIN (SELECT cj_id      AS user_id, COUNT(*) AS cnt FROM review_crew WHERE cj_id      IS NOT NULL GROUP BY cj_id) r_cj  ON u.id = r_cj.user_id
    LEFT JOIN (SELECT user_id, COUNT(*) AS cnt FROM review_evaluation GROUP BY user_id) rev ON u.id = rev.user_id
    WHERE COALESCE(r_ref.cnt,0)+COALESCE(r_ump.cnt,0)+COALESCE(r_lj.cnt,0)+COALESCE(r_hl.cnt,0)
        +COALESCE(r_fj.cnt,0)+COALESCE(r_sj.cnt,0)+COALESCE(r_bj.cnt,0)+COALESCE(r_cj.cnt,0)
        +COALESCE(rev.cnt,0) > 0
    ORDER BY u.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totals = [
    'cnt_r' => 0, 'cnt_u' => 0, 'cnt_lj' => 0, 'cnt_dj' => 0,
    'cnt_fj' => 0, 'cnt_sj' => 0, 'cnt_bj' => 0, 'cnt_c' => 0, 'cnt_reviewer' => 0
];
foreach ($rows as $r) foreach ($totals as $k => $_) $totals[$k] += (int)$r[$k];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Referee Statistics</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
<style>
  .nowrap{white-space:nowrap}
  .table thead th{text-align:center}
  .table tbody td{text-align:center}
  .table tbody td.name{text-align:left}
  .totals-row{font-weight:700;background:#f8f9fa}
</style>
</head>
<body class="p-4">
<div class="container">
  <h1 class="mb-4 text-center">Referee Statistics</h1>

  <?php if (empty($rows)): ?>
    <div class="alert alert-warning">No data found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-bordered table-sm">
      <thead class="thead-light">
        <tr>
          <th style="text-align:left;">Name</th>
          <th>R</th><th>U</th><th>LJ</th><th>DJ</th>
          <th>FJ</th><th>SJ</th><th>BJ</th><th>C</th><th>Reviewer</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="name nowrap">
            <a href="21_ref.php?id=<?= (int)$r['id'] ?>">
              <?= htmlspecialchars($r['name']) ?>
            </a>
          </td>
          <td><?= (int)$r['cnt_r'] ?></td>
          <td><?= (int)$r['cnt_u'] ?></td>
          <td><?= (int)$r['cnt_lj'] ?></td>
          <td><?= (int)$r['cnt_dj'] ?></td>
          <td><?= (int)$r['cnt_fj'] ?></td>
          <td><?= (int)$r['cnt_sj'] ?></td>
          <td><?= (int)$r['cnt_bj'] ?></td>
          <td><?= (int)$r['cnt_c'] ?></td>
          <td><?= (int)$r['cnt_reviewer'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="totals-row">
          <td class="text-right">Totals</td>
          <td><?= $totals['cnt_r'] ?></td>
          <td><?= $totals['cnt_u'] ?></td>
          <td><?= $totals['cnt_lj'] ?></td>
          <td><?= $totals['cnt_dj'] ?></td>
          <td><?= $totals['cnt_fj'] ?></td>
          <td><?= $totals['cnt_sj'] ?></td>
          <td><?= $totals['cnt_bj'] ?></td>
          <td><?= $totals['cnt_c'] ?></td>
          <td><?= $totals['cnt_reviewer'] ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
