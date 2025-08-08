<?php
// 02_reset_password.php – Two-step password reset using PIN code and email

session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Resets password using 6-digit PIN. Sends password after correct code.');

$pdo = getDatabaseConnection();
$user_id = $_SESSION['pending_user_id'] ?? 0;
$name = $_SESSION['pending_user_name'] ?? '';
$error = '';
$step = 1;

// --- Log function ---
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

// --- Step 1: Generate PIN and send to email ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['step'] == '1') {
    $_SESSION['password_code'] = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['password_expiry'] = date('Y-m-d H:i:s', time() + 15 * 60); // 15 minutes valid

    // Fetch user email
    $stmt = $pdo->prepare("SELECT email FROM review_user WHERE id = ?");
    $stmt->execute([$user_id]);
    $email = $stmt->fetchColumn();

    // Send PIN code
    $subject = "Zebraz Password Reset – Your PIN Code";
    $message = "Hello $name,\n\n" .
               "To reset your password, please enter the following 6-digit PIN code:\n\n" .
               $_SESSION['password_code'] . "\n\n" .
               "This code is valid for 15 minutes.\n\n" .
               "- Zebraz Review System";
    $headers = "From: no-reply@zebras.se\r\n";

    @mail($email, $subject, $message, $headers);

    log_action($pdo, $user_id, 'reset_request', 'PIN code sent to email');

    $step = 2;
}

// --- Step 2: Confirm code, then generate & send password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['step'] == '2') {
    $code = trim($_POST['code'] ?? '');
    $expected = $_SESSION['password_code'] ?? '';
    $expiry = $_SESSION['password_expiry'] ?? '2000-01-01';

    if ($code === $expected && strtotime($expiry) > time()) {
        // Generate password and hash
        $generatedPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#'), 0, 8);
        $hash = password_hash($generatedPassword, PASSWORD_DEFAULT);

        // Save to database
        $stmt = $pdo->prepare("UPDATE review_user SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);

        // Get email
        $stmt = $pdo->prepare("SELECT email FROM review_user WHERE id = ?");
        $stmt->execute([$user_id]);
        $email = $stmt->fetchColumn();

        // Send password by email
        $subject = "Zebraz – Your New Password";
        $message = "Hello $name,\n\n" .
                   "Your new system-generated password is:\n\n" .
                   "$generatedPassword\n\n" .
                   "You can now log in again with this password.\n\n" .
                   "- Zebraz Review System";
        $headers = "From: no-reply@zebras.se\r\n";

        @mail($email, $subject, $message, $headers);

        log_action($pdo, $user_id, 'reset_confirmed', 'Password generated and sent to email');

        // Clear session and redirect to index
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name']);
        unset($_SESSION['password_code'], $_SESSION['password_expiry']);

        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid or expired PIN code.";
        $step = 2;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <!-- Filename: 02_reset_password.php -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .box {
            max-width: 500px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h4 { text-align: center; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="box">
        <h4>Reset Password</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post">
                <input type="hidden" name="step" value="1">
                <p>A 6-digit PIN code will be sent to your email address.</p>
                <button type="submit" class="btn btn-warning btn-block">Send PIN Code</button>
            </form>

        <?php elseif ($step === 2): ?>
            <form method="post">
                <input type="hidden" name="step" value="2">
                <div class="form-group">
                    <label for="code">Enter the 6-digit PIN code:</label>
                    <input type="text" name="code" class="form-control" required pattern="\d{6}">
                </div>
                <button type="submit" class="btn btn-success btn-block">Confirm PIN</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
