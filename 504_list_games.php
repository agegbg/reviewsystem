<?php
/**
 * File: 504_list_games.php
 * Description: Lists games with filters for Tournament (league) and Reviewer status (all/none/active).
 *              Columns: Home, Away, Date, Tournament, Crew, Reviewers (number in button).
 * Calls: 505_add_crew.php?game_id=ID, 504_show_reviewers.php?game_id=ID
 * Conventions: English comments, Bootstrap 4.3.1, shared footer at bottom.
 */

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'List games with filters and reviewers count');

$pdo = getDatabaseConnection();

// --- Read filters from GET ---
$tournament = isset($_GET['t']) ? trim($_GET['t']) : 'ALL'; // league filter
$rf         = isset($_GET['rf']) ? strtolower(trim($_GET['rf'])) : 'all'; // reviewers: all|none|active
if (!in_array($rf, ['all','none','active'], true)) $rf = 'all';

// --- Fetch distinct tournaments for dropdown ---
$tournaments = $pdo->query("
    SELECT DISTINCT league 
    FROM review_game 
    WHERE league IS NOT NULL AND league <> ''
    ORDER BY league ASC
")->fetchAll(PDO::FETCH_COLUMN);

// --- Build main query with optional filters ---
$conditions = [];
$params = [];

if ($tournament !== 'ALL') {
    $conditions[] = "g.league = ?";
    $params[] = $tournament;
}
if ($rf === 'active') {
    $conditions[] = "(SELECT COUNT(*) FROM review_evaluation e WHERE e.game_id = g.id) > 0";
} elseif ($rf === 'none') {
    $conditions[] = "(SELECT COUNT(*) FROM review_evaluation e WHERE e.game_id = g.id) = 0";
}

$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// --- SQL: lägg till review_count ---
$sql = "
    SELECT 
        g.id,
        g.date,
        g.league,                         -- shown as Tournament
        ht.name  AS home_name,
        at.name  AS away_name,
        ht.logo  AS home_logo,
        at.logo  AS away_logo,
        (SELECT COUNT(*) FROM review_evaluation e WHERE e.game_id = g.id) AS reviewer_count,
        (SELECT COUNT(*) FROM review_observation o WHERE o.game_id = g.id) AS review_count
    FROM review_game g
    LEFT JOIN review_team ht ON g.home_team_id = ht.id
    LEFT JOIN review_team at ON g.away_team_id = at.id
    $where
    ORDER BY g.date DESC, g.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);
$games = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>List Games</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 4.3.1 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <style>
        .table td, .table th { vertical-align: middle; }
        .logo { height: 22px; width: auto; margin-right: 8px; vertical-align: middle; }
        .nowrap { white-space: nowrap; }
        .filters .form-control { max-width: 260px; }
        .filters .form-group { margin-bottom: .5rem; }
        .btn-tiny { padding: .25rem .5rem; font-size: .825rem; }
    </style>
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h1 class="h4 mb-0">Games</h1>
    </div>

    <!-- Filters -->
    <form method="get" class="filters mb-3">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="form-row align-items-end">
                    <div class="form-group col-sm-6 col-md-4">
                        <label for="t" class="mb-1">Tournament</label>
                        <select id="t" name="t" class="form-control" onchange="this.form.submit()">
                            <option value="ALL"<?= $tournament==='ALL' ? ' selected' : '' ?>>ALL</option>
                            <?php foreach ($tournaments as $lg): ?>
                                <option value="<?= htmlspecialchars($lg) ?>"<?= $tournament===$lg ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($lg) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-sm-6 col-md-4">
                        <label for="rf" class="mb-1">Reviewers</label>
                        <select id="rf" name="rf" class="form-control" onchange="this.form.submit()">
                            <option value="all"   <?= $rf==='all'    ? ' selected' : '' ?>>All</option>
                            <option value="none"  <?= $rf==='none'   ? ' selected' : '' ?>>None</option>
                            <option value="active"<?= $rf==='active' ? ' selected' : '' ?>>Active</option>
                        </select>
                    </div>

                    <div class="form-group col-sm-12 col-md-4">
                        <button type="submit" class="btn btn-secondary btn-tiny mt-3 mt-md-0">Apply</button>
                        <a href="504_list_games.php" class="btn btn-light btn-tiny mt-3 mt-md-0">Reset</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-dark">
                    <tr>
                        <th>Home</th>
                        <th>Away</th>
                        <th style="width:120px;">Date</th>
                        <th style="width:180px;">Tournament</th>
                        <th style="width:150px;">Crew</th>
                        <th style="width:130px;" class="text-right">Reviewers</th>
                        <th style="width:130px;" class="text-right">Reviews</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($games)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No games found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($games as $g): ?>
                            <?php $rc = (int)($g['reviewer_count'] ?? 0); ?>
                            <tr>
                                <td class="nowrap">
                                    <?php if (!empty($g['home_logo'])): ?>
                                        <img src="logo/<?= htmlspecialchars($g['home_logo']) ?>" class="logo" alt="">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($g['home_name'] ?? '—') ?>
                                </td>
                                <td class="nowrap">
                                    <?php if (!empty($g['away_logo'])): ?>
                                        <img src="logo/<?= htmlspecialchars($g['away_logo']) ?>" class="logo" alt="">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($g['away_name'] ?? '—') ?>
                                </td>
                                <td><?= htmlspecialchars($g['date'] ?? '') ?></td>
                                <td><?= htmlspecialchars($g['league'] ?? '') ?></td>
                                <td>
                                    <a href="505_add_crew.php?game_id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-primary">
                                        Add/Edit Crew
                                    </a>
                                </td>
                                <!-- Sista två cellerna i raden: Reviewers + Reviews -->
<?php $rc = (int)($g['reviewer_count'] ?? 0); ?>
<?php $rv = (int)($g['review_count'] ?? 0); ?>
<td class="text-right">
    <a class="btn btn-sm <?= $rc > 0 ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="504_show_reviewers.php?game_id=<?= (int)$g['id'] ?>">
        (<?= $rc ?>)
    </a>
</td>
<td class="text-right">
    <a class="btn btn-sm <?= $rv > 0 ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="504_show_reviews.php?game_id=<?= (int)$g['id'] ?>">
        (<?= $rv ?>)
    </a>
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

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
?>
</body>
</html>
