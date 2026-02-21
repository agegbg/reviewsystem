<?php
// 00_verify.php
// Verifies login via magic link OR 6-digit code, sets session, loads roles, and redirects accordingly.

session_start();
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Verifies login via magic link or code and sets up session');

$pdo        = getDatabaseConnection();
$error      = '';
$verified   = false;

// ---------------------------------------------------------------------
// STEP A: MAGIC LINK (works even without pending_user_id in session)
// URL format: 00_verify.php?link=<rawToken>
// We hash the token and match against review_user.login_link_hash, and check expiry.
// ---------------------------------------------------------------------
if (isset($_GET['link']) && $_GET['link'] !== '') {
    $raw  = $_GET['link'];
    $hash = hash('sha256', $raw);

    $st = $pdo->prepare("
        SELECT id, name, email, token_expiry
        FROM review_user
        WHERE login_link_hash = ?
        LIMIT 1
    ");
    $st->execute([$hash]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $validExpiry = $u && !empty($u['token_expiry']) && (new DateTime($u['token_expiry'])) >= (new DateTime());

    if ($u && $validExpiry) {
        // Invalidate tokens (one-time use)
        $pdo->prepare("
            UPDATE review_user
               SET login_link_hash = NULL,
                   login_token     = NULL,
                   token_expiry    = NULL,
                   update_date     = CURRENT_TIMESTAMP
             WHERE id = ? LIMIT 1
        ")->execute([$u['id']]);

        // Log the user in
        $_SESSION['user_id']     = (int)$u['id'];
        $_SESSION['user_name']   = $u['name'] ?? '';
        $_SESSION['login_email'] = $u['email'] ?? '';

        // Load roles
        $st2 = $pdo->prepare("
            SELECT r.code
            FROM review_user_roles ur
            JOIN review_role r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $st2->execute([$_SESSION['user_id']]);
        $_SESSION['user_roles'] = $st2->fetchAll(PDO::FETCH_COLUMN);

        // Redirect as in original flow
        if (!empty($_SESSION['user_roles']) && count($_SESSION['user_roles']) > 1) {
            header('Location: 01_select_role.php');
            exit;
        } else {
            $_SESSION['active_role'] = $_SESSION['user_roles'][0] ?? 'referee';
            header('Location: 01_mypage.php');
            exit;
        }
    } else {
        // Invalid/expired link — go back to select user and let them request a new code
        header('Location: 01_select_user.php?msg=link_invalid_or_expired');
        exit;
    }
}

// ---------------------------------------------------------------------
// STEP B: CODE-BASED LOGIN (requires pending_user_id in session)
// ---------------------------------------------------------------------
$user_id     = $_SESSION['pending_user_id']   ?? 0;
$name        = $_SESSION['pending_user_name'] ?? '';

if (!$user_id) {
    // No user context (not coming from the normal flow)
    header("Location: 01_select_user.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeInput = trim($_POST['code'] ?? '');

    if ($codeInput === '') {
        $error = "Please enter the code.";
    } else {
        // Validate 6-digit code and expiry
        $st = $pdo->prepare("
            SELECT *
            FROM review_user
            WHERE id = ?
              AND login_token = ?
              AND token_expiry > NOW()
            LIMIT 1
        ");
        $st->execute([$user_id, $codeInput]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Invalidate tokens
            $pdo->prepare("
                UPDATE review_user
                   SET login_token  = NULL,
                       login_link_hash = NULL,
                       token_expiry = NULL
                 WHERE id = ? LIMIT 1
            ")->execute([$user['id']]);

            // Set session
            $_SESSION['user_id']     = (int)$user['id'];
            $_SESSION['user_name']   = $user['name'] ?? '';
            $_SESSION['login_email'] = $user['email'] ?? '';

            // Load roles
            $st2 = $pdo->prepare("
                SELECT r.code
                FROM review_user_roles ur
                JOIN review_role r ON ur.role_id = r.id
                WHERE ur.user_id = ?
            ");
            $st2->execute([$user['id']]);
            $_SESSION['user_roles'] = $st2->fetchAll(PDO::FETCH_COLUMN);

            $verified = true;
        } else {
            $error = "Invalid or expired code.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Code</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { max-width: 600px; margin: 60px auto; }
    </style>
</head>
<body class="container py-4">
    <h3>Enter Your Code</h3>
    <p>Enter the 6-digit code sent to your email:</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$verified): ?>
        <form method="post" autocomplete="one-time-code">
            <div class="form-group">
                <label for="code">6-digit code</label>
                <input type="text" name="code" id="code" class="form-control" required pattern="\d{6}" inputmode="numeric" autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>
    <?php else: ?>
        <div class="alert alert-success">✅ Code accepted. You are now logged in.</div>
        <?php
        if (!empty($_SESSION['user_roles']) && count($_SESSION['user_roles']) > 1) {
            echo '<a href="01_select_role.php" class="btn btn-success mt-3">Continue</a>';
        } else {
            $_SESSION['active_role'] = $_SESSION['user_roles'][0] ?? 'referee';
            echo '<a href="01_mypage.php" class="btn btn-success mt-3">Continue</a>';
        }
        ?>
    <?php endif; ?>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
?>
</body>
</html>
