<?php
/**
 * 101_create_meeting.php
 * Purpose:
 *   Create a new meeting with a clean, centered card UI (like the login box).
 *   Uses CSRF protection, server-side validation, and PDO prepared statements.
 *   After successful creation, redirects to 001_mypage.php with a flash notice.
 *
 * Requirements:
 *   - php/session.php   (provides session, $currentUser, requireAuth())
 *   - php/db.php        (defines getDatabaseConnection(): PDO)
 *   - php/header.php    (shared <head>, loads Bootstrap 4.3.1 via CDN + /css/main.css)
 *   - php/footer.php    (shared footer; include at the bottom)
 *
 * Notes:
 *   - This file gracefully checks for optional columns in `meeting`:
 *       location (VARCHAR), meeting_link (VARCHAR), notes (TEXT), meeting_type (VARCHAR/ENUM)
 *     If any of these columns do not exist, they are simply skipped on INSERT.
 *   - Required DB columns: club_id (nullable), title, meeting_date, meeting_time, created_by.
 */

declare(strict_types=1);

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';

// Enforce auth (does nothing in dev-mode if you enabled that in session.php)
requireAuth();

$pdo = getDatabaseConnection();

/** ------------------------------------------------------------------
 * CSRF token bootstrap
 * ------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/** ------------------------------------------------------------------
 * Helper: check if a column exists in current DB for a given table
 * Caches results during this request to avoid multiple INFORMATION_SCHEMA hits.
 * ------------------------------------------------------------------ */
function meetingHasColumn(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $sql = "
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'meeting'
          AND COLUMN_NAME = :col
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':col' => $column]);
    $cache[$column] = (bool)$stmt->fetchColumn();
    return $cache[$column];
}

$hasLocation     = meetingHasColumn($pdo, 'location');
$hasMeetingLink  = meetingHasColumn($pdo, 'meeting_link');
$hasNotes        = meetingHasColumn($pdo, 'notes');
$hasMeetingType  = meetingHasColumn($pdo, 'meeting_type');

/** ------------------------------------------------------------------
 * Load clubs for dropdown
 * ------------------------------------------------------------------ */
$clubs = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM club ORDER BY name ASC");
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // leave empty; UI will allow creating without club
}

/** ------------------------------------------------------------------
 * POST handling
 * ------------------------------------------------------------------ */
$errors    = [];
$posted    = [
    'title'        => $_POST['title']        ?? '',
    'meeting_date' => $_POST['meeting_date'] ?? '',
    'meeting_time' => $_POST['meeting_time'] ?? '',
    'club_id'      => $_POST['club_id']      ?? '',
    'location'     => $_POST['location']     ?? '',
    'meeting_link' => $_POST['meeting_link'] ?? '',
    'notes'        => $_POST['notes']        ?? '',
    'meeting_type' => $_POST['meeting_type'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Säkerhetsfel. Ladda om sidan och försök igen.';
    }

    // Required fields
    if (trim($posted['title']) === '')        $errors[] = 'Titel måste fyllas i.';
    if (trim($posted['meeting_date']) === '') $errors[] = 'Datum måste fyllas i.';
    if (trim($posted['meeting_time']) === '') $errors[] = 'Tid måste fyllas i.';

    // Basic format checks
    if ($posted['meeting_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted['meeting_date'])) {
        $errors[] = 'Felaktigt datumformat (ÅÅÅÅ-MM-DD).';
    }
    if ($posted['meeting_time'] !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $posted['meeting_time'])) {
        $errors[] = 'Felaktigt tidsformat (HH:MM).';
    }

    // Normalize/validate link (if present and column exists)
    if ($hasMeetingLink && $posted['meeting_link'] !== '') {
        if (!preg_match('#^https?://#i', $posted['meeting_link'])) {
            $posted['meeting_link'] = 'https://' . $posted['meeting_link'];
        }
        if (!filter_var($posted['meeting_link'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Möteslänken är inte en giltig URL.';
        }
    }

    // club (optional; allow NULL)
    $clubId = null;
    if ($posted['club_id'] !== '') {
        if (!ctype_digit((string)$posted['club_id'])) {
            $errors[] = 'Ogiltigt klubbval.';
        } else {
            $clubId = (int)$posted['club_id'];
        }
    }

    // Insert if ok
    if (!$errors) {
        try {
            $columns = ['club_id', 'title', 'meeting_date', 'meeting_time', 'created_by'];
            $values  = [':club_id', ':title', ':meeting_date', ':meeting_time', ':created_by'];
            $params  = [
                ':club_id'      => $clubId ?: null,
                ':title'        => $posted['title'],
                ':meeting_date' => $posted['meeting_date'],
                ':meeting_time' => $posted['meeting_time'],
                ':created_by'   => (int)$currentUser['id'],
            ];

            if ($hasLocation) {
                $columns[] = 'location';
                $values[]  = ':location';
                $params[':location'] = ($posted['location'] !== '' ? $posted['location'] : null);
            }
            if ($hasMeetingLink) {
                $columns[] = 'meeting_link';
                $values[]  = ':meeting_link';
                $params[':meeting_link'] = ($posted['meeting_link'] !== '' ? $posted['meeting_link'] : null);
            }
            if ($hasNotes) {
                $columns[] = 'notes';
                $values[]  = ':notes';
                $params[':notes'] = ($posted['notes'] !== '' ? $posted['notes'] : null);
            }
            if ($hasMeetingType) {
                $columns[] = 'meeting_type';
                $values[]  = ':meeting_type';
                $params[':meeting_type'] = ($posted['meeting_type'] !== '' ? $posted['meeting_type'] : null);
            }

            $sql = 'INSERT INTO meeting (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Redirect to my page with a flash notice
            header('Location: 001_mypage.php?notice=meeting_created');
            exit;

        } catch (Throwable $e) {
            $errors[] = 'Databasfel: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

/** ------------------------------------------------------------------
 * Page render
 * ------------------------------------------------------------------ */
// Page title & extra head before loading shared header
$PAGE_TITLE = 'Skapa möte';
$EXTRA_HEAD = <<<HTML
<style>
  /* Centered card like the login box */
  .auth-wrap {
    min-height: calc(100vh - 140px);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .auth-card { max-width: 520px; width: 100%; }
  .brand-title { font-weight: 700; }
  .required::after { content: " *"; color: #dc3545; }
</style>
HTML;

require_once __DIR__ . '/php/header.php';
?>

<div class="auth-wrap">
  <div class="card shadow-sm border-0 auth-card">
    <div class="card-body p-4 p-md-5">
      <h1 class="h3 text-center mb-4 brand-title"><?php echo htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8'); ?></h1>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <strong>Åtgärda följande:</strong>
          <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
          <label class="required" for="title">Titel</label>
          <input type="text" class="form-control" id="title" name="title"
                 value="<?php echo htmlspecialchars($posted['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label class="required" for="meeting_date">Datum</label>
            <input type="date" class="form-control" id="meeting_date" name="meeting_date"
                   value="<?php echo htmlspecialchars($posted['meeting_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label class="required" for="meeting_time">Tid</label>
            <input type="time" class="form-control" id="meeting_time" name="meeting_time"
                   value="<?php echo htmlspecialchars($posted['meeting_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="club_id">Förening (valfritt)</label>
          <select class="form-control" id="club_id" name="club_id">
            <option value="">— Ingen —</option>
            <?php foreach ($clubs as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"
                <?php echo ($posted['club_id'] !== '' && (int)$posted['club_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($hasLocation): ?>
          <div class="form-group">
            <label for="location">Plats (valfritt)</label>
            <input type="text" class="form-control" id="location" name="location"
                   placeholder="Adress eller 'Online'"
                   value="<?php echo htmlspecialchars($posted['location'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        <?php endif; ?>

        <?php if ($hasMeetingLink): ?>
          <div class="form-group">
            <label for="meeting_link">Länk till digitalt möte (valfritt)</label>
            <input type="url" class="form-control" id="meeting_link" name="meeting_link"
                   placeholder="https://…"
                   value="<?php echo htmlspecialchars($posted['meeting_link'], ENT_QUOTES, 'UTF-8'); ?>">
            <small class="form-text text-muted">Zoom/Teams/Meet osv. Saknas https:// läggs det till automatiskt.</small>
          </div>
        <?php endif; ?>

        <?php if ($hasMeetingType): ?>
          <div class="form-group">
            <label for="meeting_type">Mötestyp (valfritt)</label>
            <select class="form-control" id="meeting_type" name="meeting_type">
              <option value="">— Ingen —</option>
              <?php
                // English keys saved to DB, Swedish labels in UI
                $types = [
                  'board'     => 'Styrelsemöte',
                  'annual'    => 'Årsmöte',
                  'study'     => 'Studiecirkel',
                  'players'   => 'Spelarmöte',
                  'committee' => 'Kommittémöte',
                  'other'     => 'Övrigt',
                ];
                foreach ($types as $k => $label):
              ?>
                <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                  <?php echo ($posted['meeting_type'] === $k) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($hasNotes): ?>
          <div class="form-group mb-4">
            <label for="notes">Anteckningar (valfritt)</label>
            <textarea class="form-control" id="notes" name="notes" rows="4"
                      placeholder="Interna anteckningar eller extra info"><?php
                echo htmlspecialchars($posted['notes'], ENT_QUOTES, 'UTF-8');
            ?></textarea>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block">Skapa möte</button>
        <a href="001_mypage.php" class="btn btn-link btn-block mt-2">Avbryt</a>
      </form>
    </div>
  </div>
</div>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
