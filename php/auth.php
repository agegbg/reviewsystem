<?php
// php/auth.php – inloggnings/roll-hjälp
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: 001_login.php');
        exit;
    }
}

/**
 * Uppdaterar sessionsinfo från DB (namn/roll), eller loggar ut om användaren inte finns/avaktiverad.
 */
function refresh_user_session(): void {
    if (!isset($_SESSION['user_id'])) return;

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT id, name, role FROM user_account WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        // ogiltig användare -> nollställ
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: 001_login.php');
        exit;
    }

    $_SESSION['name'] = $u['name'];
    $_SESSION['role'] = $u['role'];
}

function is_admin(): bool {
    return (($_SESSION['role'] ?? 'user') === 'administrator');
}
