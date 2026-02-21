<?php
// 999_list_user.php
// Admin • Users — list all users with officiated/reviewed counts and roles.
// Requirements:
// - Access control: only logged-in admin (role code 'admin'); otherwise redirect to 01_mypage.php
// - Search by name/email with simple pagination (25/page)
// - Columns: User (name, email), Officiated, Reviewed, Roles, Actions ("Edit rights")
// - Responsive Bootstrap 4.3.1 table, preserve search + page in query params

// --- Session & DB bootstrap ---
require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';

// --- Helper: redirect convenience ---
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// --- Require login ---
if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    redirect('00_login.php');
}

$pdo = getDatabaseConnection();

// --- Access control: must have role 'admin' ---
$currentUserId = (int)$_SESSION['user_id'];
$st = $pdo->prepare("
    SELECT COUNT(*) AS is_admin
    FROM review_user_roles ur
    JOIN review_role r ON r.id = ur.role_id
    WHERE ur.user_id = ? AND r.code = 'admin'
    LIMIT 1
");
$st->execute([$currentUserId]);
$isAdmin = (int)$st->fetchColumn();

if ($isAdmin !== 1) {
    redirect('01_mypage.php');
}

// --- Input (search & pagination) ---
$perPage = 25; // page size
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search  = isset($_GET['search']) ? trim($_GET['search']) : '';
$qParam  = '%' . $search . '%';

// --- Count total matching users ---
$sqlCount = "
    SELECT COUNT(*) 
    FROM review_user u
    WHERE (:q = '%%') OR (u.name LIKE :q OR u.email LIKE :q)
";
$st = $pdo->prepare($sqlCount);
$st->bindValue(':q', $qParam, PDO::PARAM_STR);
$st->execute();
$totalRows = (int)$st->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$offset     = ($page - 1) * $perPage;

// --- Fetch page rows with correlated sub-queries (fast enough for 25 rows) ---
// Officiated: distinct games where user appears in any crew slot (referee_id, umpire_id, hl_id, lj_id, fj_id, sj_id, bj_id, cj_id, observer_id)
// Reviewed: distinct games in review_evaluation for user_id
// Roles: comma-separated label (code)
$sqlList = "
    SELECT 
        u.id,
        u.name,
        u.email,

        -- Distinct games officiated (any crew position)
        COALESCE((
            SELECT COUNT(DISTINCT rc.game_id)
            FROM review_crew rc
            WHERE rc.referee_id = u.id
               OR rc.umpire_id  = u.id
               OR rc.hl_id      = u.id
               OR rc.lj_id      = u.id
               OR rc.fj_id      = u.id
               OR rc.sj_id      = u.id
               OR rc.bj_id      = u.id
               OR rc.cj_id      = u.id
               OR rc.observer_id= u.id
        ), 0) AS officiated_count,

        -- Distinct games reviewed
        COALESCE((
            SELECT COUNT(DISTINCT re.game_id)
            FROM review_evaluation re
            WHERE re.user_id = u.id
        ), 0) AS reviewed_count,

        -- Roles as 'Label (code)' list
        COALESCE((
            SELECT GROUP_CONCAT(CONCAT(r.label, ' (', r.code, ')') ORDER BY r.label SEPARATOR ', ')
            FROM review_user_roles ur
            JOIN review_role r ON r.id = ur.role_id
            WHERE ur.user_id = u.id
        ), '') AS roles_list

    FROM review_user u
    WHERE (:q = '%%') OR (u.name LIKE :q OR u.email LIKE :q)
    ORDER BY u.name ASC
    LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sqlList);
$st->bindValue(':q', $qParam, PDO::PARAM_STR);
$st->bindValue(':limit', $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Helper to rebuild query string preserving search + new page
function buildPageUrl($page, $search) {
    $params = [
        'page'   => max(1, (int)$page),
        'search' => $search,
    ];
    return '999_list_user.php?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin • Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Bootstrap 4.3.1 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
<style>
  .table thead th { white-space: nowrap; }
  .pagination { margin-bottom: 0; }
  .search-form .form-control { max-width: 320px; }
</style>
</head>
<body>

<div class="container my-4">
  <!-- Page title -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Admin • Users</h1>
  </div>

  <!-- Search + meta row -->
  <form class="search-form mb-3" method="get" action="999_list_user.php">
    <div class="form-row align-items-center">
      <div class="col-auto">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or email..."
               value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Search</button>
        <a class="btn btn-sm btn-outline-secondary" href="999_list_user.php">Reset</a>
      </div>
      <div class="col-auto ml-auto text-muted small">
        <?php
          $from = $totalRows ? ($offset + 1) : 0;
          $to   = min($offset + $perPage, $totalRows);
          echo htmlspecialchars("Showing $from–$to of $totalRows", ENT_QUOTES, 'UTF-8');
        ?>
      </div>
    </div>
    <!-- Preserve current page? When searching, go to page 1 implicitly -->
    <?php if ($search !== ''): ?>
      <input type="hidden" name="page" value="1">
    <?php else: ?>
      <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead class="thead-light">
        <tr>
          <th>User</th>
          <th class="text-center">Officiated</th>
          <th class="text-center">Reviewed</th>
          <th>Roles</th>
          <th style="width:130px;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="5" class="text-muted">No users found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <div class="font-weight-bold"><?php echo htmlspecialchars($r['name'] ?: '(no name)', ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="small text-muted"><?php echo htmlspecialchars($r['email'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
            </td>
            <td class="text-center"><?php echo (int)$r['officiated_count']; ?></td>
            <td class="text-center"><?php echo (int)$r['reviewed_count']; ?></td>
            <td><?php echo htmlspecialchars($r['roles_list'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <a class="btn btn-sm btn-outline-primary"
                 href="<?php echo '999_edit_rights.php?user_id=' . (int)$r['id']; ?>">
                Edit rights
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav aria-label="User list pages" class="d-flex justify-content-between align-items-center">
    <ul class="pagination mb-0">
      <?php
        // Previous
        $prevDisabled = ($page <= 1) ? ' disabled' : '';
        $prevUrl = buildPageUrl($page - 1, $search);
      ?>
      <li class="page-item<?php echo $prevDisabled; ?>">
        <a class="page-link" href="<?php echo $prevDisabled ? '#' : htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1">« Prev</a>
      </li>

      <?php
        // Simple numeric window: current +/- 2
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(buildPageUrl(1, $search), ENT_QUOTES, 'UTF-8') . '">1</a></li>';
            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($p = $start; $p <= $end; $p++) {
            $active = ($p === $page) ? ' active' : '';
            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . htmlspecialchars(buildPageUrl($p, $search), ENT_QUOTES, 'UTF-8') . '">' . (int)$p . '</a></li>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(buildPageUrl($totalPages, $search), ENT_QUOTES, 'UTF-8') . '">' . (int)$totalPages . '</a></li>';
        }
      ?>

      <?php
        // Next
        $nextDisabled = ($page >= $totalPages) ? ' disabled' : '';
        $nextUrl = buildPageUrl($page + 1, $search);
      ?>
      <li class="page-item<?php echo $nextDisabled; ?>">
        <a class="page-link" href="<?php echo $nextDisabled ? '#' : htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8'); ?>">Next »</a>
      </li>
    </ul>
    <div class="small text-muted">
      Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
    </div>
  </nav>
  <?php endif; ?>

</div>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
?>
</body>
</html>
