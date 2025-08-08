<?php
session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Token-based login for regular users');

$pdo = getDatabaseConnection();
$error = '';
$maskedEmail = '';
$emailInput = '';
$user_id = $_SESSION['pending_user_id'] ?? 0;
$name = $_SESSION['pending_user_name'] ?? '';

if (!$user_id) {
    header("Location: 01_select_user.php");
    exit;
}

// Get user from DB
$stmt = $pdo->prepare("SELECT * FROM review_user WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = "User not found.";
} else {
    $maskedEmail = maskEmail($user['email']);
}

// Step: Confirm email & send code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = trim($_POST['email'] ?? '');

    if (strtolower($emailInput) !== strtolower($user['email'])) {
        $error = "Email does not match our records.";
    } else {
        // Generate 6-digit numeric code
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', time() + 15 * 60);

        $stmt = $pdo->prepare("UPDATE review_user SET login_token = ?, token_expiry = ? WHERE id = ?");
        $stmt->execute([$token, $expiry, $user_id]);

        // Send email
        $subject = "Your login code";
        $message = "Hi $name,\n\nYour login code is: $token\n\nIt is valid for 15 minutes.\n\nRegards,\nZebras System";
        $headers = "From: no-reply@zebras.se\r\n";

        @mail($user['email'], $subject, $message, $headers);

        $_SESSION['login_method'] = 'token_only';
        header("Location: 00_verify.php");
        exit;
    }
}

// Mask email for display
function maskEmail($email) {
    $parts = explode("@", $email);
    $local = $parts[0];
    $domain = $parts[1];

    $maskedLocal = substr($local, 0, 1) . str_repeat("*", max(0, strlen($local) - 2)) . substr($local, -1);
    $domainParts = explode(".", $domain);
    $domainBase = $domainParts[0];
    $domainExt = $domainParts[1] ?? '';

    $maskedDomain = substr($domainBase, 0, 1) . str_repeat("*", max(0, strlen($domainBase) - 2)) . substr($domainBase, -1);

    return $maskedLocal . '@' . $maskedDomain . '.' . $domainExt;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login via Email Code</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h3>Hello <?= htmlspecialchars($name) ?></h3>
    <p>We found your account. Please confirm your email to receive a login code:</p>

    <?php if ($maskedEmail): ?>
        <p><strong><?= htmlspecialchars($maskedEmail) ?></strong></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="email">Enter full email address:</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success">Send Login Code</button>
    </form>
</body>
</html>
