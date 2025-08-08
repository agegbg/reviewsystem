<?php
// 00_login_admin.php – Admin login with password and logging

session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Admin login with password check or fallback to password reset');

$pdo = getDatabaseConnection();
$error = '';
$name = $_SESSION['pending_user_name'] ?? '';
$user_id = $_SESSION['pending_user_id'] ?? 0;

// --- Log function to review_logg table ---
function log_action($pdo, $user_id, $type, $desc = '') {
    $stmt = $pdo->prepare("
        INSERT INTO review_logg (user_id, action_type, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $type,
        $desc,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// --- Step 1: Load user from DB ---
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM review_user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // --- Step 2: No password yet? Redirect to reset ---
        if (empty($user['password_hash'])) {
            $_SESSION['pending_user_id'] = $user['id'];
            $_SESSION['pending_user_name'] = $user['name'];
            header("Location: 02_reset_password.php");
            exit;
        }

        // --- Step 3: Handle login form ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            $inputPassword = $_POST['password'];

            if (password_verify($inputPassword, $user['password_hash'])) {
                // Success – set full session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name']);

                log_action($pdo, $user['id'], 'admin_login_success', 'Successful login');
                header("Location: 01_mypage.php");
                exit;
            } else {
                $error = "Incorrect password.";
                log_action($pdo, $user['id'], 'admin_login_failed', 'Incorrect password attempt');
            }
        }
    } else {
        $error = "User not found.";
    }
} else {
    $error = "No user selected.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <!-- Filename: 00_login_admin.php -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .box {
            max-width: 500px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h4 {
            text-align: center;
            margin-bottom: 25px;
        }
        .forgot-btn {
            font-size: 0.9em;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="box">
        <h4>Admin Login for <?= htmlspecialchars($name) ?></h4>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="post">
            <div class="form-group">
                <label for="password">Enter your admin password:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <!-- Forgot password button -->
        <div class="forgot-btn">
            <form method="post" action="02_reset_password.php">
                <button type="submit" class="btn btn-link">Forgot password?</button>
            </form>
        </div>
    </div>
</body>
</html>
