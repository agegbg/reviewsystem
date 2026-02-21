<?php
// 201_review_list.php
// Lists games for the logged-in user.
// ?tab=officiated  → games where user is in crew
// ?tab=reviewed    → games assigned to user as reviewer
// Default: reviewed
//
// Uses: review_game, review_team, review_crew, review_evaluation, review_observation
// Shows team logos, date, league, and review counters (my/total).
//
// Includes auto-registration into web_files for menu.

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Games list: Officiated / Review assignments with logos and review counts.', 'review', 1, 201);

$pdo = getDatabaseConnection();

// Enforce login
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: 00_login.php");
    exit;
}

$tab = isset($_GET['tab']) ? strtolower(trim($_GET['tab'])) : 'reviewed';
if (!in_array($tab, ['reviewed','officiated'], true)) {
    $tab = 'reviewed';
}

// Base select fields (shared)
$baseSelect = "
    SELECT
        g.id AS game_id,
        g.date,
        g.league,
        t1.name AS home_name, t1.logo AS home_logo,
        t2.name AS away_name, t2.logo AS away_logo,
        -- My obs for game
        (SELECT COUNT(*) FROM review_evaluation e1
            JOIN review_observation o1 ON e1.id = o1.evaluation_id
            WHERE e1.user_id = :uid AND e1.game_id = g.id
        ) AS my_reviews,
        -- Total obs for game
        (SELECT COUNT(*) FROM review_evaluation e2
            JOIN review_observation o2 ON e2.id = o2.evaluation_id
            WHERE e2.game_id = g.id
        ) AS total_reviews
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
";

// Build query per tab
if ($tab === 'officiated') {
    // Games where user is in any crew position
    $sql = $baseSelect . "
        JOIN review_crew rc ON rc.game_id = g.id
        WHERE :uid IN (rc.referee_id, rc.umpire_id, rc.lj_id, rc.hl_id, rc.fj_id, rc.sj_id, rc.bj_id, rc.cj_id)
        GROUP BY g.id
        ORDER BY g.date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
} else {
    // Games assigned to this reviewer
    $sql = $baseSelect . "
        JOIN review_evaluation e ON e.game_id = g.id
        WHERE e.user_id = :uid
        GROUP BY g.id
        ORDER BY g.date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
}

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counters for tabs
$cntReviewed = 0;
$cntOfficiated = 0;

// Reviewed count
$c1 = $pdo->prepare("SELECT COUNT(DISTINCT e.game_id) AS c FROM review_evaluation e WHERE e.user_id = ?");
$c1->execute([$user_id]);
$cntReviewed = (int)($c1->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

// Officiated count
$c2 = $pdo->prepare("
    SELECT COUNT(DISTINCT g.id) AS c
    FROM review_game g
    JOIN review_crew rc ON rc.game_id = g.id
    WHERE :uid IN (rc.referee_id, rc.umpire_id, rc.lj_id, rc.hl_id, rc.fj_id, rc.sj_id, rc.bj_id, rc.cj_id)
");
$c2->execute([':uid' => $user_id]);
$cntOfficiated = (int)($c2->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $tab === 'officiated' ? 'My Officiated Games' : 'My Review Assignments' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 4.3.1 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
          crossorigin="anonymous">
    <style>
        .logo { height:30px; vertical-align:middle; }
        .text-right  { text-align:right; }
        .text-left   { text-align:left; }
        .text-center { text-align:center; }
        .nowrap { white-space:nowrap; }
        .pill { text-transform: uppercase; letter-spacing: .03em; }
    </style>
</head>
<body class="p-4">
<div class="container">

    <div class="d-flex align-items-center mb-3">
        <h3 class="mb-0">
            <?= $tab === 'officiated' ? 'My Officiated Games' : 'My Review Assignments' ?>
        </h3>
        <a href="01_mypage.php" class="btn btn-link ml-3">My Page</a>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link pill <?= $tab==='reviewed' ? 'active' : '' ?>" href="?tab=reviewed">
                Reviews <span class="badge badge-light"><?= $cntReviewed ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link pill <?= $tab==='officiated' ? 'active' : '' ?>" href="?tab=officiated">
                Officiated <span class="badge badge-light"><?= $cntOfficiated ?></span>
            </a>
        </li>
    </ul>

    <?php if (empty($games)): ?>
        <div class="alert alert-info mb-0">
            <?= $tab === 'officiated' ? 'No games found where you are in the crew.' : 'No review assignments found.' ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="thead-light text-center">
                <tr>
                    <th></th>
                    <th>Home</th>
                    <th>Away</th>
                    <th></th>
                    <th>Date</th>
                    <th>Event</th>
                    <th>Reviews</th>
                    <th class="nowrap">Open</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($games as $g): ?>
                    <tr>
                        <!-- Home logo -->
                        <td class="text-center">
                            <?php if (!empty($g['home_logo'])): ?>
                                <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo mr-1" alt="">
                            <?php endif; ?>
                        </td>

                        <!-- Home name -->
                        <td class="text-right"><?= htmlspecialchars($g['home_name'] ?? '') ?></td>

                        <!-- Away name -->
                        <td class="text-left"><?= htmlspecialchars($g['away_name'] ?? '') ?></td>

                        <!-- Away logo -->
                        <td class="text-center">
                            <?php if (!empty($g['away_logo'])): ?>
                                <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo ml-1" alt="">
                            <?php endif; ?>
                        </td>

                        <!-- Date -->
                        <td class="text-center"><?= htmlspecialchars($g['date']) ?></td>

                        <!-- League/Event -->
                        <td class="text-center"><?= htmlspecialchars($g['league'] ?? '') ?></td>

                        <!-- Reviews: my / total -->
                        <td class="text-center">
                            <?= (int)$g['my_reviews'] ?> / <?= (int)$g['total_reviews'] ?>
                        </td>

                        <!-- Open -->
                        <td class="text-center">
                            <!-- If you have a game details page for officiated, you could branch it.
                                 For now, both go to review list for that game. -->
                            <a href="502_show_reviews.php?game_id=<?= (int)$g['game_id'] ?>"
                               class="btn btn-outline-primary btn-sm">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
