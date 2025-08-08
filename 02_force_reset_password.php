<?php
session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'TEMP: Reset password via name + code confirmation');

$pdo = getDatabaseConnection();
$step = 1;
$error = '';
$maskedEmail = '';
$nameInput = '';
$user = null;

// Step 1: Enter name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['step'] == '1') {
    $nameInput = trim($_POST['name']);
    $stmt = $pdo->prepare("SELECT * FROM review_user WHERE name = ?");
    $stmt->execute([$nameInput]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_name'] = $user['name'];
        $_SESSION['reset_email'] = $user['email'];

        $maskedEmail = maskEmail($user['email']);
        $_SESSION['masked_email'] = $maskedEmail;
        $step = 2;
    } else {
        $error = "User not found.";
    }
}

// Step 2: Confirm email, send code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['step'] == '2') {
    $fullEmail = trim($_POST['email'] ?? '');
    $storedEmail = $_SESSION['reset_email'] ?? '';
    $user_id = $_SESSION['reset_user_id'] ?? 0;

    if (strtolower($fullEmail) === strtolower($storedEmail)) {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reset_code'] = $code;
        $_SESSION['reset_code_expiry'] = time() + 900; // 15 min

        // Send code
        $subject = "Confirmation Code for Password Reset";
        $message = "Your confirmation code is: $code\nValid for 15 minutes.";
        $headers = "From: no-reply@zebras.se\r\n";
        @mail($storedEmail, $subject, $message, $headers);

        $step = 3;
    } else {
        $error = "Email does not match.";
        $step = 2;
    }
}

// Step 3: Enter code, set + send password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['step'] == '3') {
    $enteredCode = trim($_POST['code'] ?? '');
    $validCode = $_SESSION['reset_code'] ?? '';
    $expiry = $_SESSION['reset_code_expiry'] ?? 0;
    $user_id = $_SESSION['reset_user_id'] ?? 0;
    $email = $_SESSION['reset_email'] ?? '';
    $name = $_SESSION['reset_name'] ?? '';

    if ($enteredCode === $validCode && time() < $expiry) {
        // Generate new password
        $newPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#'), 0, 10);
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Save to database
        $stmt = $pdo->prepare("UPDATE review_user SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);

        // Send password to email
        $subject = "Your New Password";
        $message = "Hi $name,\n\nYour new password is:\n\n$newPassword\n\nPlease save it securely.";
        $headers = "From: no-reply@zebras.se\r\n";
        @mail($email, $subject, $message, $headers);

        // Clean up
        unset($_SESSION['reset_code'], $_SESSION['reset_code_expiry']);
        unset($_SESSION['reset_user_id'], $_SESSION['reset_name'], $_SESSION['reset_email']);

        $step = 4;
    } else {
        $error = "Invalid or expired code.";
        $step = 3;
    }
}

// Masking function
function maskEmail($email) {
    $parts = explode("@", $email);
    if (count($parts) !== 2) return $email;

    $local = $parts[0];
    $domain = $parts[1];

    $maskedLocal = substr($local, 0, 1) . str_repeat("*", max(0, strlen($local) - 2)) . substr($local, -1);
    $domainParts = explode(".", $domain);
    $maskedDomain = substr($domainParts[0], 0, 1) . str_repeat("*", max(0, strlen($domainParts[0]) - 2)) . substr($domainParts[0], -1);

    return $maskedLocal . '@' . $maskedDomain . '.' . $domainParts[1];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset My Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h3>Temporary Password Reset</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <form method="post">
            <input type="hidden" name="step" value="1">
            <div class="form-group">
                <label for="name">Enter your name:</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Continue</button>
        </form>

    <?php elseif ($step === 2): ?>
        <form method="post">
            <input type="hidden" name="step" value="2">
            <p>We found your account. Confirm your email:</p>
            <p><strong><?= htmlspecialchars($_SESSION['masked_email'] ?? '') ?></strong></p>
            <div class="form-group">
                <label for="email">Enter full email address:</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Send Confirmation Code</button>
        </form>

    <?php elseif ($step === 3): ?>
        <form method="post">
            <input type="hidden" name="step" value="3">
            <div class="form-group">
                <label for="code">Enter the 6-digit code sent to your email:</label>
                <input type="text" name="code" class="form-control" required pattern="\d{6}">
            </div>
            <button type="submit" class="btn btn-success">Confirm & Reset Password</button>
        </form>

    <?php elseif ($step === 4): ?>
        <div class="alert alert-success">
            ✅ Your password has been reset and sent to your email.
        </div>
        <a href="index.php" class="btn btn-primary">Return to Login</a>
    <?php endif; ?>
</body>
</html>
