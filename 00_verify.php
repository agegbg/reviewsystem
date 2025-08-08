<?php
session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Verifies login code and sets up session');

$pdo = getDatabaseConnection();
$error = '';
$verified = false;
$user_id = $_SESSION['pending_user_id'] ?? 0;
$name = $_SESSION['pending_user_name'] ?? '';
$loginMethod = $_SESSION['login_method'] ?? 'token_only';

if (!$user_id) {
    header("Location: 01_select_user.php");
    exit;
}

// STEP: Handle code input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeInput = trim($_POST['code'] ?? '');

    if (!$codeInput) {
        $error = "Please enter the code.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM review_user WHERE id = ? AND login_token = ? AND token_expiry > NOW()");
        $stmt->execute([$user_id, $codeInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Clear token
            $stmt = $pdo->prepare("UPDATE review_user SET login_token = NULL, token_expiry = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Set session values
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['login_email'] = $user['email'];

            // Load roles
            $stmt = $pdo->prepare("
                SELECT r.code 
                FROM review_user_roles ur 
                JOIN review_role r ON ur.role_id = r.id 
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$user['id']]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $_SESSION['user_roles'] = $roles;

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
</head>
<body class="container py-4">
    <h3>Enter Your Code</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$verified): ?>
        <form method="post">
            <div class="form-group">
                <label for="code">Enter the 6-digit code sent to your email:</label>
                <input type="text" name="code" class="form-control" required pattern="\d{6}">
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>
    <?php else: ?>
        <div class="alert alert-success">✅ Code accepted. You are now logged in.</div>

        <?php
        // Redirect based on role count
        if (count($_SESSION['user_roles']) > 1) {
            echo '<a href="01_select_role.php" class="btn btn-success mt-3">Continue</a>';
        } else {
            $_SESSION['active_role'] = $_SESSION['user_roles'][0] ?? 'referee';
            echo '<a href="01_mypage.php" class="btn btn-success mt-3">Continue</a>';
        }
        ?>
    <?php endif; ?>
</body>
</html>
