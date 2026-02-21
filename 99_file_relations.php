<?php
// Filename: 99_file_relations.php
// Description: Admin page to document relationships between files and keep per-file notes.
// Calls: php/session.php, php/db.php, php/file_register.php
// Called from: Admin menu or direct navigation

// Start the session and optionally enforce login (controlled via php/session.php)
require_once __DIR__ . '/php/session.php';

// Load database connection (PDO with utf8mb4)
require_once __DIR__ . '/php/db.php';

// Add file info to database for menu/system tracking
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Manage file relations and comments for web files.');

$pdo = getDatabaseConnection();

/** ---- Helpers ----------------------------------------------------------- */

/** Get all files from web_files (system filter optional) */
function getAllFiles(PDO $pdo, ?string $system = null): array {
    if ($system) {
        $st = $pdo->prepare("SELECT id, filename, description, system FROM web_files WHERE system = ? ORDER BY filename ASC");
        $st->execute([$system]);
    } else {
        $st = $pdo->query("SELECT id, filename, description, system FROM web_files ORDER BY filename ASC");
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Get one file by id */
function getFileById(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT id, filename, description, system FROM web_files WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Get relations for a file (outgoing) */
function getRelationsForFile(PDO $pdo, int $fileId): array {
    $sql = "
        SELECT l.id, l.relation, l.note,
               f2.id AS related_id, f2.filename AS related_filename, f2.description AS related_description, f2.system AS related_system
        FROM web_file_links l
        JOIN web_files f2 ON f2.id = l.related_id
        WHERE l.file_id = ?
        ORDER BY l.relation ASC, f2.filename ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$fileId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Get inbound relations (who points to this file) */
function getInboundRelations(PDO $pdo, int $fileId): array {
    $sql = "
        SELECT l.id, l.relation, l.note,
               f1.id AS src_id, f1.filename AS src_filename, f1.description AS src_description, f1.system AS src_system
        FROM web_file_links l
        JOIN web_files f1 ON f1.id = l.file_id
        WHERE l.related_id = ?
        ORDER BY l.relation ASC, f1.filename ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$fileId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Get comments for a file */
function getCommentsForFile(PDO $pdo, int $fileId): array {
    $sql = "SELECT id, user, comment, comment_date FROM web_file_comments WHERE file_id = ? ORDER BY comment_date DESC, id DESC";
    $st = $pdo->prepare($sql);
    $st->execute([$fileId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** ---- Post actions ------------------------------------------------------ */

$msg_ok = $msg_err = '';
$selected_id = (int)($_GET['id'] ?? 0);
$system_filter = isset($_GET['system']) && $_GET['system'] !== '' ? trim($_GET['system']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_relation') {
        $file_id    = (int)($_POST['file_id'] ?? 0);
        $related_id = (int)($_POST['related_id'] ?? 0);
        $relation   = $_POST['relation'] ?? '';
        $note       = trim($_POST['note'] ?? '');
        $selected_id = $file_id;

        $valid = ['calls','called_by','includes','redirects_to','used_for','comes_from','duplicates','supersedes'];
        if ($file_id > 0 && $related_id > 0 && in_array($relation, $valid, true)) {
            try {
                $st = $pdo->prepare("
                    INSERT INTO web_file_links (file_id, related_id, relation, note)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE note = VALUES(note), update_date = CURRENT_TIMESTAMP
                ");
                $st->execute([$file_id, $related_id, $relation, $note]);
                $msg_ok = 'Relation added/updated.';
            } catch (Throwable $e) {
                $msg_err = 'Failed to add relation: ' . $e->getMessage();
            }
        } else {
            $msg_err = 'Please choose file, related file, and a valid relation.';
        }
    }

    if ($action === 'delete_relation') {
        $rel_id     = (int)($_POST['id'] ?? 0);
        $selected_id = (int)($_POST['selected_id'] ?? 0);
        if ($rel_id > 0) {
            $st = $pdo->prepare("DELETE FROM web_file_links WHERE id = ? LIMIT 1");
            $st->execute([$rel_id]);
            $msg_ok = 'Relation deleted.';
        }
    }

    if ($action === 'add_comment') {
        $file_id = (int)($_POST['file_id'] ?? 0);
        $user    = trim($_POST['user'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $selected_id = $file_id;
        if ($file_id > 0 && $comment !== '') {
            $st = $pdo->prepare("
                INSERT INTO web_file_comments (file_id, user, comment, create_date, update_date)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $st->execute([$file_id, $user !== '' ? $user : null, $comment]);
            $msg_ok = 'Comment added.';
        } else {
            $msg_err = 'Please select a file and write a comment.';
        }
    }

    if ($action === 'delete_comment') {
        $cid         = (int)($_POST['cid'] ?? 0);
        $selected_id = (int)($_POST['selected_id'] ?? 0);
        if ($cid > 0) {
            $st = $pdo->prepare("DELETE FROM web_file_comments WHERE id = ? LIMIT 1");
            $st->execute([$cid]);
            $msg_ok = 'Comment deleted.';
        }
    }

    // keep system filter when posting
    $system_filter = isset($_POST['system']) && $_POST['system'] !== '' ? trim($_POST['system']) : null;
}

/** ---- Load data for view ----------------------------------------------- */

$files = getAllFiles($pdo, $system_filter);
$current = $selected_id ? getFileById($pdo, $selected_id) : null;
$outgoing = $current ? getRelationsForFile($pdo, $current['id']) : [];
$inbound  = $current ? getInboundRelations($pdo, $current['id']) : [];
$comments = $current ? getCommentsForFile($pdo, $current['id']) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>File relations & notes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
 integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
 crossorigin="anonymous">
<style>
  .mono { font-family: monospace; }
  .file-pill { font-family: monospace; font-size: 90%; }
</style>
</head>
<body class="p-3">
<div class="container-fluid">

  <h1 class="mb-3">File relations & notes</h1>

  <?php if ($msg_ok): ?><div class="alert alert-success"><?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="alert alert-danger"><?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

  <form class="form-inline mb-3" method="get">
    <label class="mr-2">System filter</label>
    <input type="text" name="system" value="<?= htmlspecialchars($system_filter ?? '') ?>" class="form-control mr-3" placeholder="e.g. review">
    <label class="mr-2">Select file</label>
    <select name="id" class="form-control mr-2">
      <option value="">-- choose --</option>
      <?php foreach ($files as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= $selected_id === (int)$f['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($f['filename']) ?><?= $f['system'] ? ' ['.htmlspecialchars($f['system']).']' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Open</button>
  </form>

  <?php if ($current): ?>
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title mono mb-1"><?= htmlspecialchars($current['filename']) ?></h5>
        <p class="mb-1"><strong>System:</strong> <?= htmlspecialchars($current['system'] ?? '') ?></p>
        <p class="mb-0"><strong>Description:</strong> <?= htmlspecialchars($current['description'] ?? '') ?></p>
      </div>
    </div>

    <!-- Add relation -->
    <div class="card mb-4">
      <div class="card-header">Add / update relation from <span class="mono"><?= htmlspecialchars($current['filename']) ?></span></div>
      <div class="card-body">
        <form method="post" class="form">
          <input type="hidden" name="action" value="add_relation">
          <input type="hidden" name="file_id" value="<?= (int)$current['id'] ?>">
          <input type="hidden" name="system" value="<?= htmlspecialchars($system_filter ?? '') ?>">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Relation</label>
              <select name="relation" class="form-control" required>
                <?php
                  $rels = ['calls','called_by','includes','redirects_to','used_for','comes_from','duplicates','supersedes'];
                  foreach ($rels as $r) echo '<option value="'.$r.'">'.$r.'</option>';
                ?>
              </select>
            </div>
            <div class="form-group col-md-5">
              <label>Related file</label>
              <select name="related_id" class="form-control" required>
                <option value="">-- choose --</option>
                <?php foreach ($files as $f): if ($f['id'] == $current['id']) continue; ?>
                  <option value="<?= (int)$f['id'] ?>">
                    <?= htmlspecialchars($f['filename']) ?><?= $f['system'] ? ' ['.htmlspecialchars($f['system']).']' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>Note (optional)</label>
              <input type="text" name="note" class="form-control" maxlength="500" placeholder="e.g. POST redirect">
            </div>
          </div>
          <button class="btn btn-success">Save relation</button>
        </form>
      </div>
    </div>

    <!-- Outgoing relations -->
    <div class="card mb-4">
      <div class="card-header">Outgoing relations</div>
      <div class="card-body">
        <?php if (!$outgoing): ?>
          <p class="text-muted mb-0">No relations yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead><tr>
                <th>Relation</th><th>Related file</th><th>System</th><th>Description</th><th>Note</th><th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($outgoing as $r): ?>
                <tr>
                  <td class="mono"><?= htmlspecialchars($r['relation']) ?></td>
                  <td class="mono"><?= htmlspecialchars($r['related_filename']) ?></td>
                  <td><?= htmlspecialchars($r['related_system'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['related_description'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
                  <td class="text-right">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="delete_relation">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="selected_id" value="<?= (int)$current['id'] ?>">
                      <input type="hidden" name="system" value="<?= htmlspecialchars($system_filter ?? '') ?>">
                      <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this relation?')">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Inbound relations -->
    <div class="card mb-4">
      <div class="card-header">Inbound relations (other files pointing to this one)</div>
      <div class="card-body">
        <?php if (!$inbound): ?>
          <p class="text-muted mb-0">No inbound relations.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead><tr>
                <th>Relation</th><th>Source file</th><th>System</th><th>Description</th><th>Note</th>
              </tr></thead>
              <tbody>
              <?php foreach ($inbound as $r): ?>
                <tr>
                  <td class="mono"><?= htmlspecialchars($r['relation']) ?></td>
                  <td class="mono"><?= htmlspecialchars($r['src_filename']) ?></td>
                  <td><?= htmlspecialchars($r['src_system'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['src_description'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Comments -->
    <div class="card mb-4">
      <div class="card-header">Comments / notes for <span class="mono"><?= htmlspecialchars($current['filename']) ?></span></div>
      <div class="card-body">
        <form method="post" class="mb-3">
          <input type="hidden" name="action" value="add_comment">
          <input type="hidden" name="file_id" value="<?= (int)$current['id'] ?>">
          <input type="hidden" name="system" value="<?= htmlspecialchars($system_filter ?? '') ?>">
          <div class="form-row">
            <div class="form-group col-md-2">
              <label>User (optional)</label>
              <input type="text" name="user" class="form-control" maxlength="100" placeholder="Your name">
            </div>
            <div class="form-group col-md-8">
              <label>Comment</label>
              <input type="text" name="comment" class="form-control" maxlength="2000" required>
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
              <button class="btn btn-primary btn-block">Add</button>
            </div>
          </div>
        </form>

        <?php if (!$comments): ?>
          <p class="text-muted mb-0">No comments yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead><tr><th>Date</th><th>User</th><th>Comment</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($comments as $c): ?>
                <tr>
                  <td class="mono"><?= htmlspecialchars($c['comment_date']) ?></td>
                  <td><?= htmlspecialchars($c['user'] ?? '') ?></td>
                  <td><?= htmlspecialchars($c['comment']) ?></td>
                  <td class="text-right">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="delete_comment">
                      <input type="hidden" name="cid" value="<?= (int)$c['id'] ?>">
                      <input type="hidden" name="selected_id" value="<?= (int)$current['id'] ?>">
                      <input type="hidden" name="system" value="<?= htmlspecialchars($system_filter ?? '') ?>">
                      <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this comment?')">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>

</div>
</body>
</html>
