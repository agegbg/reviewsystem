<?php

/**

 * File: 01_mypage.php

 * Description: Personal landing page with tabs (Menu / Officiated / Review).

 *              Email-based identity block (name, email, city). Admin button if role 'admin'.

 *              Lists:

 *                - Officiated: games where the user appears in review_crew

 *                - Review:    games where the user is reviewer in review_evaluation

 * Calls: 999_admin_menu.php, 200_my_reviews.php, 01_editmypage.php, 00_logout.php

 * Notes: English comments. Bootstrap 4.3.1. Uses PDO via php/db.php.

 */



require_once __DIR__ . '/php/session.php';

require_once __DIR__ . '/php/db.php';

require_once __DIR__ . '/php/file_register.php';

updateFileInfo(basename(__FILE__), 'My Page with tabs: Menu / Officiated / Review');



if (!isset($_SESSION['user_id'])) {

    header('Location: 01_select_user.php');

    exit;

}



$pdo = getDatabaseConnection();

$userId = (int)$_SESSION['user_id'];



/* -----------------------------------------------------------------------

 * Load base user + optional user_info (photo/city override)

 * ---------------------------------------------------------------------*/

$st = $pdo->prepare("SELECT id, name, email, city AS base_city, picture AS base_picture FROM review_user WHERE id = ? LIMIT 1");

$st->execute([$userId]);

$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    session_destroy();

    header('Location: 01_select_user.php');

    exit;

}



$st = $pdo->prepare("SELECT photo, city FROM review_user_info WHERE user_id = ? LIMIT 1");

$st->execute([$userId]);

$uinfo = $st->fetch(PDO::FETCH_ASSOC) ?: ['photo'=>null, 'city'=>null];



$displayPhoto = $uinfo['photo'] ?: $user['base_picture'] ?: 'photo/default_user.png';

$displayCity  = $uinfo['city']  ?: $user['base_city']  ?: '';



/* -----------------------------------------------------------------------

 * Check admin role

 * ---------------------------------------------------------------------*/

$st = $pdo->prepare("

    SELECT 1

    FROM review_user_roles ur

    JOIN review_role r ON r.id = ur.role_id

    WHERE ur.user_id = ? AND r.code = 'admin'

    LIMIT 1

");

$st->execute([$userId]);

$isAdmin = (bool)$st->fetchColumn();



/* -----------------------------------------------------------------------

 * Fetch Officiated list (games where user is on crew)

 * We pull all crew columns and identify the user's position(s) in PHP.

 * ---------------------------------------------------------------------*/

$officiated = [];

$crewSql = "
    SELECT
        g.id, g.date, g.time, g.league, g.level, g.game_type, g.place,
        g.score_home, g.score_away,
        ht.name AS home_name, ht.logo AS home_logo,
        at.name AS away_name, at.logo AS away_logo,
        rc.crew_size,
        rc.referee_id, rc.umpire_id, rc.hl_id, rc.lj_id, rc.fj_id, rc.sj_id,
        rc.bj_id, rc.cj_id, rc.observer_id, rc.evaluator_id,
        ev.id AS evaluation_id
    FROM review_crew rc
    JOIN review_game g  ON g.id = rc.game_id
    LEFT JOIN review_team ht ON ht.id = g.home_team_id
    LEFT JOIN review_team at ON at.id = g.away_team_id
    LEFT JOIN review_evaluation ev ON ev.game_id = g.id AND ev.user_id = :uid
    WHERE
        rc.referee_id = :uid OR rc.umpire_id = :uid OR rc.hl_id = :uid OR rc.lj_id = :uid
        OR rc.fj_id = :uid OR rc.sj_id = :uid OR rc.bj_id = :uid OR rc.cj_id = :uid
        OR rc.observer_id = :uid
    ORDER BY g.date DESC, g.time DESC, g.id DESC
";


$st = $pdo->prepare($crewSql);

$st->execute([':uid' => $userId]);

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {

    // Determine your position(s) in this game

    $pos = [];

    $map = [

        'referee_id'  => 'R',

        'umpire_id'   => 'U',

        'hl_id'       => 'HL', // Will still show HL here even if UI elsewhere maps to DJ

        'lj_id'       => 'LJ',

        'fj_id'       => 'FJ',

        'sj_id'       => 'SJ',

        'bj_id'       => 'BJ',

        'cj_id'       => 'CJ',

        'observer_id' => 'Observer',

    ];

    foreach ($map as $col => $code) {

        if (!empty($row[$col]) && (int)$row[$col] === $userId) {

            $pos[] = $code;

        }

    }

    $row['position'] = implode(', ', $pos);

    $officiated[] = $row;

}



/* -----------------------------------------------------------------------

 * Fetch Review list (games where user is reviewer)

 * ---------------------------------------------------------------------*/

$reviews = [];

$revSql = "

    SELECT

        g.id, g.date, g.time, g.league, g.level, g.game_type, g.place,

        g.score_home, g.score_away,

        ht.name AS home_name, ht.logo AS home_logo,

        at.name AS away_name, at.logo AS away_logo,

        ev.is_head_evaluator, ev.evaluation_type, ev.comment, ev.id AS evaluation_id

    FROM review_evaluation ev

    JOIN review_game g    ON g.id = ev.game_id

    LEFT JOIN review_team ht ON ht.id = g.home_team_id

    LEFT JOIN review_team at ON at.id = g.away_team_id

    WHERE ev.user_id = ?

    ORDER BY g.date DESC, g.time DESC, g.id DESC

";

$st = $pdo->prepare($revSql);

$st->execute([$userId]);

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {

    $reviews[] = $row;

}



$officiatedCount = count($officiated);

$reviewsCount    = count($reviews);

?>

<!doctype html>

<html lang="en">

<head>

  <meta charset="utf-8">

  <title>My Page</title>

  <meta name="viewport" content="width=device-width, initial-scale=1">



  <!-- Bootstrap 4.3.1 CSS (no SRI / crossorigin) -->

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">



  <style>

    body { background:#f5f7fb; }

    .card-soft { background:#fff; border-radius:.5rem; box-shadow:0 2px 8px rgba(0,0,0,.06); }

    .profile-card { padding:1rem 1.25rem; margin-top:1.25rem; }

    .avatar {  width:80px;  height:80px;  object-fit:cover;  border:2px solid #e9ecef;  border-radius:8px; /* square with slight rounding */}

    .btn-pill { border-radius:2rem; padding:.5rem 1.1rem; }

    .btn-admin { color:#b30000; background:#ffecec; border-color:#ffdbdb; }

    .btn-admin:hover { background:#ffd6d6; }

    .nav-tabs .nav-link { border: none; }

    .nav-tabs .nav-link.active { border-bottom: 3px solid #007bff; font-weight: 600; }

    .logo-sm { width:28px; height:28px; object-fit:contain; }

    .table thead th { border-top: none; }

    .badge-soft { background:#eef3ff; color:#2f5aff; }

  </style>

</head>



<body>

<div class="container">



   <!-- Identity -->

<div class="card-soft profile-card">

  <div class="d-flex align-items-center">

    <img src="photo/<?= htmlspecialchars(basename($displayPhoto)) ?>" class="avatar mr-3" alt="User photo">

    <div class="flex-grow-1">

      <h3 class="h4 mb-1">Welcome <?= htmlspecialchars($user['name']) ?>!</h3>

      <div><a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a></div>

      <?php if ($displayCity): ?>

        <div>City: <?= htmlspecialchars($displayCity) ?></div>

      <?php endif; ?>

    </div>



    <!-- Right-aligned quick actions -->

    <div class="ml-auto d-none d-sm-block">

      <a href="01_editmypage.php" class="btn btn-secondary btn-pill mr-2 mb-2">My Info</a>

      <a href="00_logout.php" class="btn btn-outline-danger btn-pill mb-2">Log Out</a>

    </div>

  </div>



  <!-- On very small screens, show the buttons below for better layout -->

  <div class="d-block d-sm-none mt-2">

    <a href="01_editmypage.php" class="btn btn-secondary btn-pill mr-2 mb-2">My Info</a>

    <a href="00_logout.php" class="btn btn-outline-danger btn-pill mb-2">Log Out</a>

  </div>

</div>





  <!-- Tabs -->

<div class="card-soft mt-3">

  <ul class="nav nav-tabs px-3 pt-3" id="mypageTabs" role="tablist">

    <li class="nav-item">

      <a class="nav-link active" id="menu-tab" data-toggle="tab" href="#tab-menu" role="tab" aria-controls="tab-menu" aria-selected="true">

        Menu

      </a>

    </li>

    <li class="nav-item">

      <a class="nav-link" id="officiated-tab" data-toggle="tab" href="#tab-officiated" role="tab" aria-controls="tab-officiated" aria-selected="false">

        Officiated <span class="badge badge-soft ml-1"><?= (int)$officiatedCount ?></span>

      </a>

    </li>

    <li class="nav-item">

      <a class="nav-link" id="review-tab" data-toggle="tab" href="#tab-review" role="tab" aria-controls="tab-review" aria-selected="false">

        Review <span class="badge badge-soft ml-1"><?= (int)$reviewsCount ?></span>

      </a>

    </li>



<li class="nav-item">

  <a class="nav-link" id="stats-tab" data-toggle="tab" href="#tab-stats" role="tab" aria-controls="tab-stats" aria-selected="false">

    Stats

  </a>

</li>





    <?php if ($isAdmin): ?>

    <li class="nav-item">

      <a class="nav-link" id="admin-tab" data-toggle="tab" href="#tab-admin" role="tab" aria-controls="tab-admin" aria-selected="false">

        Admin

      </a>

    </li>

    <?php endif; ?>

  </ul>



  <div class="tab-content p-3">



<!-- TABS configuration: tab panes inside .tab-content -->

<!-- MENU TAB -->

<div class="tab-pane fade show active" id="tab-menu" role="tabpanel" aria-labelledby="menu-tab">

  <div class="d-flex flex-wrap">

    <a href="200_my_reviews.php" class="btn btn-success btn-pill mr-2 mb-2">My Games</a>

  </div>

</div>





<!-- OFFICIATED TAB (list only) -->

<div class="tab-pane fade" id="tab-officiated" role="tabpanel" aria-labelledby="officiated-tab">

  <?php if ($officiatedCount === 0): ?>

    <p class="text-muted mb-0">No officiated games found.</p>

  <?php else: ?>

    <div class="table-responsive">

      <table class="table table-sm table-hover">

        <thead>

          <tr>

            <th></th>

            <th>Home</th>

            <th></th>

            <th>Away</th>

            <th>Date</th>

            <th>Event</th>

            <th>Pos</th>

            <th>Score</th>

            <th></th>

          </tr>

        </thead>

        <tbody>

        <?php foreach ($officiated as $g): ?>

          <?php

            $dateStr = $g['date'] ? htmlspecialchars($g['date']) : '';

            $event   = $g['level'] ?: $g['league'] ?: $g['game_type'] ?: '';

            $score   = (is_numeric($g['score_home']) && is_numeric($g['score_away'])) ? ($g['score_home'].' - '.$g['score_away']) : '';

          ?>

          <tr>

            <td><img src="logo/<?= htmlspecialchars($g['home_logo'] ?? '') ?>" class="logo-sm" alt=""></td>

            <td><?= htmlspecialchars($g['home_name'] ?? '') ?></td>

            <td><img src="logo/<?= htmlspecialchars($g['away_logo'] ?? '') ?>" class="logo-sm" alt=""></td>

            <td><?= htmlspecialchars($g['away_name'] ?? '') ?></td>

            <td><?= $dateStr ?></td>

            <td><?= htmlspecialchars($event) ?></td>

            <td><?= htmlspecialchars($g['position']) ?></td>

            <td><?= htmlspecialchars($score) ?></td>

            <td><a href="202_add_observation.php?game_id=<?= (int)$g['id'] ?>&evaluation_id=<?= (int)$g['evaluation_id'] ?>" class="btn btn-sm btn-outline-primary">Open</a></td>

          </tr>

        <?php endforeach; ?>

        </tbody>

      </table>

    </div>

  <?php endif; ?>

</div>



<!-- REVIEW TAB (list only) -->

<div class="tab-pane fade" id="tab-review" role="tabpanel" aria-labelledby="review-tab">

  <?php if ($reviewsCount === 0): ?>

    <p class="text-muted mb-0">No reviews found.</p>

  <?php else: ?>

    <div class="table-responsive">

      <table class="table table-sm table-hover">

        <thead>

          <tr>

            <th></th>

            <th>Home</th>

            <th></th>

            <th>Away</th>

            <th>Date</th>

            <th>Event</th>

            <th>Head</th>

            <th></th>

          </tr>

        </thead>

        <tbody>

        <?php foreach ($reviews as $r): ?>

          <?php

            $dateStr = $r['date'] ? htmlspecialchars($r['date']) : '';

            $event   = $r['level'] ?: $r['league'] ?: $r['game_type'] ?: '';

            $isHead  = ((int)$r['is_head_evaluator'] === 1) ? 'Yes' : 'No';

          ?>

          <tr>

            <td><img src="logo/<?= htmlspecialchars($r['home_logo'] ?? '') ?>" class="logo-sm" alt=""></td>

            <td><?= htmlspecialchars($r['home_name'] ?? '') ?></td>

            <td><img src="logo/<?= htmlspecialchars($r['away_logo'] ?? '') ?>" class="logo-sm" alt=""></td>

            <td><?= htmlspecialchars($r['away_name'] ?? '') ?></td>

            <td><?= $dateStr ?></td>

            <td><?= htmlspecialchars($event) ?></td>

            <td><?= $isHead ?></td>

            <td><a href="202_add_observation.php?game_id=<?= (int)$r['id'] ?>&evaluation_id=<?= (int)$r['evaluation_id'] ?>" class="btn btn-sm btn-outline-primary">Open</a></td>

          </tr>

        <?php endforeach; ?>

        </tbody>

      </table>

    </div>

  <?php endif; ?>

</div>



<!-- STATS TAB (placeholder, extend later with DB queries) -->

<div class="tab-pane fade" id="tab-stats" role="tabpanel" aria-labelledby="stats-tab">

  <div class="row">

    <div class="col-md-4 mb-3">

      <div class="card card-soft p-3">

        <div class="text-muted small mb-1">Officiated games</div>

        <div class="h4 mb-0"><?= (int)$officiatedCount ?></div>

      </div>

    </div>

    <div class="col-md-4 mb-3">

      <div class="card card-soft p-3">

        <div class="text-muted small mb-1">Reviews you’re on</div>

        <div class="h4 mb-0"><?= (int)$reviewsCount ?></div>

      </div>

    </div>

    <div class="col-md-4 mb-3">

      <div class="card card-soft p-3">

        <div class="text-muted small mb-1">Penalties / flags</div>

        <div class="h6 mb-0 text-muted">Coming soon</div>

      </div>

    </div>

  </div>

  <small class="text-muted">We’ll add breakdowns here (e.g., penalties thrown, grading distribution, positions) next.</small>

</div>







<?php if ($isAdmin): ?>

  <!-- ADMIN TAB (buttons only, visible for admins) -->

  <div class="tab-pane fade" id="tab-admin" role="tabpanel" aria-labelledby="admin-tab">

    <div class="mb-3">

      <h5 class="mb-2">Admin tools</h5>

      <small class="text-muted">Minimal admin menu. Adjust targets as we add pages.</small>

    </div>



    <div class="d-flex flex-wrap">

      <a href="101_add_game.php" class="btn btn-primary btn-pill mr-2 mb-2">Games</a>

      <a href="999_list_user.php" class="btn btn-outline-secondary btn-pill mr-2 mb-2">Users</a>

      <a href="504_show_reviews.php" class="btn btn-outline-secondary btn-pill mr-2 mb-2">Reviews</a>

    </div>

  </div>

<?php endif; ?>



<!-- jQuery + Bootstrap bundle (no SRI / crossorigin) -->

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>



<?php

// Include shared footer (version, copyright, JS, Matomo slot)

require_once __DIR__ . '/php/footer.php';

?>

</body>

</html>





