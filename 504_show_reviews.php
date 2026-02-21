<?php
/**
 * File: 504_show_reviews.php
 * Purpose: Show all observations (reviews) for a given game with a clean header, quick filters,
 *          counts (reviewers & reviews), CSV export, and links to open the observation form.
 * Calls:   -
 * Called by: list pages linking with ?game_id=ID
 *
 * Notes:
 * - All comments in English.
 * - Robust to missing/null fields (shows em dashes).
 * - Reviewer column is placed at the far right.
 * - Head evaluator is shown with a pill/chip.
 */

require_once __DIR__ . '/php/session.php';   // Start session / auth (project-standard)
require_once __DIR__ . '/php/db.php';        // PDO connection (utf8mb4)
require_once __DIR__ . '/php/file_register.php'; // Register this file (menu/admin tracking)
updateFileInfo(basename(__FILE__), 'Show reviews (observations) for a game with filters, counters, CSV export, and header.');

// Helper: safe echo for strings
function h(?string $s): string { return htmlspecialchars((string)$s ?? '', ENT_QUOTES, 'UTF-8'); }

// Read input
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if ($game_id <= 0) {
    http_response_code(400);
    die('Missing or invalid game_id');
}

$pdo = getDatabaseConnection();

// --- Load game + teams (logos, names) ---------------------------------------------------------
$sqlGame = "
SELECT
  g.id, g.date, g.time,
  g.score_home, g.score_away,
  g.tournament_name, g.start_time,
  th.name AS home_name, th.logo AS home_logo,
  ta.name AS away_name, ta.logo AS away_logo
FROM review_game g
LEFT JOIN review_team th ON th.id = g.home_team_id
LEFT JOIN review_team ta ON ta.id = g.away_team_id
WHERE g.id = ?
LIMIT 1";
$st = $pdo->prepare($sqlGame);
$st->execute([$game_id]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game) {
    http_response_code(404);
    die('Game not found');
}

// --- Load evaluators for this game (for filters and “Open review” links) ----------------------
$sqlEvals = "
SELECT e.id AS evaluation_id, e.user_id, e.is_head_evaluator,
       u.name AS reviewer_name
FROM review_evaluation e
LEFT JOIN review_user u ON u.id = e.user_id
WHERE e.game_id = ?
ORDER BY e.is_head_evaluator DESC, reviewer_name ASC";
$st = $pdo->prepare($sqlEvals);
$st->execute([$game_id]);
$evaluators = $st->fetchAll(PDO::FETCH_ASSOC);

// Reviewer lists
$reviewerOptions = ['all' => 'All', 'head' => 'Head'];
$evalById = [];
foreach ($evaluators as $ev) {
    $label = $ev['reviewer_name'] ? $ev['reviewer_name'] : ('User #' . (int)$ev['user_id']);
    $reviewerOptions['u' . (int)$ev['user_id']] = $label;
    $evalById[(int)$ev['evaluation_id']] = $ev;
}

// --- Build filters ---------------------------------------------------------------------------
// GET params: reviewer (all|head|u{user_id}), type (play_type), position, q (search in comment)
$flt_reviewer = $_GET['reviewer'] ?? 'all';
$flt_type     = trim($_GET['type'] ?? '');
$flt_pos      = trim($_GET['position'] ?? '');
$flt_q        = trim($_GET['q'] ?? '');

// Base query for observations (joined with evaluation+user for reviewer name)
$sqlBase = "
SELECT
  o.id,
  o.time,
  o.play_type,
  o.position,
  o.foul,
  o.grading,
  o.comment,
  o.evaluation_id,
  u.name AS reviewer_name,
  e.is_head_evaluator
FROM review_observation o
LEFT JOIN review_evaluation e ON e.id = o.evaluation_id
LEFT JOIN review_user u ON u.id = e.user_id
WHERE o.game_id = :game_id
";
$params = ['game_id' => $game_id];

// Reviewer filter
if ($flt_reviewer === 'head') {
    $sqlBase .= " AND e.is_head_evaluator = 1";
} elseif (preg_match('/^u(\d+)$/', $flt_reviewer, $m)) {
    $sqlBase .= " AND e.user_id = :uid";
    $params['uid'] = (int)$m[1];
}

// Type filter
if ($flt_type !== '') {
    $sqlBase .= " AND o.play_type = :ptype";
    $params['ptype'] = $flt_type;
}

// Position filter
if ($flt_pos !== '') {
    $sqlBase .= " AND o.position = :ppos";
    $params['ppos'] = $flt_pos;
}

// Search in comment
if ($flt_q !== '') {
    $sqlBase .= " AND o.comment LIKE :q";
    $params['q'] = '%' . $flt_q . '%';
}

$sqlOrder = " ORDER BY o.create_date ASC, o.id ASC";

// Distinct values for quick filters (built without type/pos filters to show all options for the game)
$distinctTypes = $pdo->prepare("SELECT DISTINCT play_type FROM review_observation WHERE game_id = ? AND play_type IS NOT NULL AND play_type<>'' ORDER BY play_type ASC");
$distinctTypes->execute([$game_id]);
$types = array_column($distinctTypes->fetchAll(PDO::FETCH_ASSOC), 'play_type');

$distinctPositions = $pdo->prepare("SELECT DISTINCT position FROM review_observation WHERE game_id = ? AND position IS NOT NULL AND position<>'' ORDER BY position ASC");
$distinctPositions->execute([$game_id]);
$positions = array_column($distinctPositions->fetchAll(PDO::FETCH_ASSOC), 'position');

// Counts (reviewers & reviews) – reviewers count is distinct by user in evaluation
$stCntRev = $pdo->prepare("SELECT COUNT(DISTINCT user_id) AS c FROM review_evaluation WHERE game_id = ?");
$stCntRev->execute([$game_id]);
$reviewers_count = (int)($stCntRev->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

$stCntObs = $pdo->prepare(str_replace(
    "SELECT
  o.id,
  o.time,
  o.play_type,
  o.position,
  o.foul,
  o.grading,
  o.comment,
  o.evaluation_id,
  u.name AS reviewer_name,
  e.is_head_evaluator",
    "SELECT COUNT(*) AS c",
    $sqlBase
) . $sqlOrder);
$stCntObs->execute($params);
$observations_count = (int)($stCntObs->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

// Export CSV branch --------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $stCsv = $pdo->prepare($sqlBase . $sqlOrder);
    $stCsv->execute($params);
    $rows = $stCsv->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reviews_game_' . $game_id . '.csv');

    $out = fopen('php://output', 'w');
    // Header
    fputcsv($out, ['When', 'Qtr', 'Offense', 'Type', 'Position', 'Foul', 'Grade', 'Comment', 'Reviewer', 'Head?']);
    // We don’t have qtr/offense in schema; leave blank.
    foreach ($rows as $r) {
        fputcsv($out, [
            (string)($r['time'] ?? ''),
            '', // Qtr not present
            '', // Offense not present
            (string)($r['play_type'] ?? ''),
            (string)($r['position'] ?? ''),
            (string)($r['foul'] ?? ''),
            (string)($r['grading'] ?? ''),
            (string)($r['comment'] ?? ''),
            (string)($r['reviewer_name'] ?? ''),
            ((int)($r['is_head_evaluator'] ?? 0) === 1 ? 'Yes' : 'No'),
        ]);
    }
    fclose($out);
    exit;
}

// Load rows for on-screen table
$stRows = $pdo->prepare($sqlBase . $sqlOrder);
$stRows->execute($params);
$rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

// Helper for logos (fallback to placeholder if missing)
function logoImg(?string $file): string {
    if (!$file) return '<div class="logo-ph"></div>';
    $safe = h($file);
    return '<img class="team-logo" src="logo/' . $safe . '" alt="">';
}

// Small helper: badge for score (e.g., 32–43)
$score_badge = '';
if ($game['score_home'] !== null && $game['score_away'] !== null) {
    $score_badge = '<span class="badge badge-dark px-2 py-1">' .
                   (int)$game['score_home'] . ' – ' . (int)$game['score_away'] .
                   '</span>';
}

// Tournament label (fallback “Dukes Tourney” instead of “Stream” text)
$tournament = $game['tournament_name'] ?: 'Dukes Tourney';

// Start time (uses game.start_time if present; otherwise game.time)
$start_label = $game['start_time'] ?: $game['time'] ?: '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Reviews – <?= h($game['home_name'] ?? 'Home') ?> vs <?= h($game['away_name'] ?? 'Away') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
<style>
/* Minimal, clean look */
.team-logo { width:56px; height:56px; object-fit:contain; }
.logo-ph { width:56px; height:56px; background:#eee; border-radius:8px; }
.header-wrap { display:flex; justify-content:space-between; align-items:center; gap:12px; }
.header-team { display:flex; align-items:center; gap:10px; min-width:220px; }
.header-mid { text-align:center; flex:1; }
.small-counters .badge { font-size:90%; }
.filter-row .form-control { max-width: 220px; }
.table thead th { white-space:nowrap; }
.badge-chip { border-radius: 999px; }
</style>
</head>
<body class="bg-light">

<div class="container my-3">

  <!-- Header -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="header-wrap">
        <div class="header-team">
          <?= logoImg($game['home_logo'] ?? null) ?>
          <div class="text-left">
            <div class="font-weight-bold"><?= h($game['home_name'] ?? 'Home') ?></div>
          </div>
        </div>

        <div class="header-mid">
          <div class="h5 mb-1"><?= h(($game['home_name'] ?? 'Home') . ' vs ' . ($game['away_name'] ?? 'Away')) ?></div>
          <div class="mb-1">
            <strong><?= h($tournament) ?></strong>
            <?= $score_badge ? ('&nbsp;' . $score_badge) : '' ?>
          </div>
          <div class="text-muted">
            <?= h($game['date'] ?? '') ?>
            <?= $start_label ? ('&nbsp;•&nbsp;Start: ' . h($start_label)) : '' ?>
          </div>
          <div class="small-counters mt-2">
            <span class="badge badge-primary">Reviewers: <?= (int)$reviewers_count ?></span>
            <span class="badge badge-secondary">Reviews: <?= (int)$observations_count ?></span>
          </div>
        </div>

        <div class="header-team justify-content-end">
          <div class="text-right">
            <div class="font-weight-bold"><?= h($game['away_name'] ?? 'Away') ?></div>
          </div>
          <?= logoImg($game['away_logo'] ?? null) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Toolbar: Open review + Export -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="btn-group">
      <a class="btn btn-sm btn-success" href="<?= h('202_add_observation.php?game_id=' . $game_id) ?>">Open review</a>
      <?php if (!empty($evaluators)) : ?>
      <button type="button" class="btn btn-sm btn-success dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="sr-only">Toggle Dropdown</span>
      </button>
      <div class="dropdown-menu">
        <?php foreach ($evaluators as $ev): ?>
          <?php
            $chip = ((int)$ev['is_head_evaluator'] === 1) ? ' <span class="badge badge-warning badge-chip">Head</span>' : '';
            $label = h($ev['reviewer_name'] ?? ('User #' . (int)$ev['user_id']));
            $url = '202_add_observation.php?game_id=' . $game_id . '&evaluation_id=' . (int)$ev['evaluation_id'];
          ?>
          <a class="dropdown-item" href="<?= h($url) ?>"><?= $label ?><?= $chip ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <a class="btn btn-sm btn-outline-secondary" href="<?= h($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['export' => 1]))) ?>">Export CSV</a>
  </div>

  <!-- Quick Filters -->
  <form method="get" class="card mb-3">
    <div class="card-body filter-row">
      <input type="hidden" name="game_id" value="<?= (int)$game_id ?>">
      <div class="form-row align-items-end">
        <div class="col-auto">
          <label for="fReviewer" class="mb-1">Reviewer</label>
          <select id="fReviewer" name="reviewer" class="form-control form-control-sm">
            <?php foreach ($reviewerOptions as $key => $label): ?>
              <option value="<?= h($key) ?>" <?= ($flt_reviewer === $key ? 'selected' : '') ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        
        </div>

        <div class="col-auto">
          <label for="fType" class="mb-1">Type</label>
          <select id="fType" name="type" class="form-control form-control-sm">
            <option value="">ALL</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= h($t) ?>" <?= ($flt_type === $t ? 'selected' : '') ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-auto">
          <label for="fPos" class="mb-1">Position</label>
          <select id="fPos" name="position" class="form-control form-control-sm">
            <option value="">ALL</option>
            <?php foreach ($positions as $p): ?>
              <option value="<?= h($p) ?>" <?= ($flt_pos === $p ? 'selected' : '') ?>><?= h($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-auto">
          <label for="fQ" class="mb-1">Search in comment</label>
          <input id="fQ" type="text" name="q" class="form-control form-control-sm" value="<?= h($flt_q) ?>" placeholder="Enter text…">
        </div>

        <div class="col-auto">
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
          <a class="btn btn-sm btn-light" href="<?= h($_SERVER['PHP_SELF'] . '?game_id=' . $game_id) ?>">Reset</a>
        </div>
      </div>
    </div>
  </form>

  <!-- Reviews table -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead class="thead-light">
          <tr>
            <th>When</th>
            
            <th>Type</th>
            <th>Position</th>
            <th>Foul</th>
            <th>Grade</th>
            <th>Comment</th>
            <th class="text-right">Reviewer</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No reviews found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $when   = $r['time'] ?: '—';
                $type   = $r['play_type'] ?: '—';
                $pos    = $r['position'] ?: '—';
                $foul   = $r['foul'] ?: '—';
                $grade  = $r['grading'] ?: '—';
                $comm   = $r['comment'] ?: '—';
                $name   = $r['reviewer_name'] ?: '—';
                $isHead = ((int)$r['is_head_evaluator'] === 1);
              ?>
              <tr>
                <td><?= h($when) ?></td>
                
                <td><?= h($type) ?></td>
                <td><?= h($pos) ?></td>
                <td><?= h($foul) ?></td>
                <td><?= h($grade) ?></td>
                <td><?= nl2br(h($comm)) ?></td>
                <td class="text-right">
                  <?= h($name) ?>
                  <?php if ($isHead): ?>
                    &nbsp;<span class="badge badge-warning badge-chip">Head</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- jQuery + Bootstrap bundle (no SRI / crossorigin) -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
?>
</body>
</html>
