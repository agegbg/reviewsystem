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

   

              // --- Generate secure one-time token for magic link ---
        $rawToken  = bin2hex(random_bytes(32));               // send to user
        $tokenHash = hash('sha256', $rawToken);               // store securely
        $expiry    = date('Y-m-d H:i:s', time() + 15 * 60);

        // Store both 6-digit code and link token
        $stmt = $pdo->prepare("UPDATE review_user 
                               SET login_token = ?, token_expiry = ?, login_link_hash = ? 
                               WHERE id = ?");
        $stmt->execute([$token, $expiry, $tokenHash, $user_id]);

        // --- Helper: detect client IP behind proxies ---
        function clientIp(): string {
            $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
            foreach ($keys as $k) {
                if (!empty($_SERVER[$k])) {
                    $ip = $_SERVER[$k];
                    if ($k === 'HTTP_X_FORWARDED_FOR' && strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    return $ip;
                }
            }
            return 'unknown';
        }

        // --- Build magic link ---
        $baseUrl = rtrim(sprintf('%s://%s%s',
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
        ), '/');
        $loginUrl = $baseUrl . '/00_verify.php?link=' . urlencode($rawToken);

        // --- Collect client info ---
        $ip = clientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // --- Build HTML email ---
        $subject = 'Your Zebras login code';
        $html = '
        <!doctype html>
        <html>
          <body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;padding:24px;background:#f6f7f9;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e8eaee;">
              <tr>
                <td style="padding:24px 24px 8px 24px;">
                  <h2 style="margin:0 0 4px 0;font-size:20px;color:#111;">Hi ' . htmlspecialchars($name) . ',</h2>
                  <p style="margin:0;color:#333;">Your login code is:</p>
                  <p style="font-size:28px;font-weight:700;letter-spacing:2px;margin:8px 0 16px 0;color:#111;">' . htmlspecialchars($token) . '</p>
                  <p style="margin:0 0 20px 0;color:#333;">It is valid for <strong>15 minutes</strong>.</p>

                  <a href="' . htmlspecialchars($loginUrl) . '"
                     style="display:inline-block;padding:12px 18px;text-decoration:none;background:#111;
                            color:#fff;border-radius:8px;font-weight:600;">
                     Log in here
                  </a>
                  <p style="margin:12px 0 0 0;color:#666;font-size:13px;">
                    If you are on the same computer, you can click the button to log in directly.
                  </p>
                </td>
              </tr>

              <tr>
  <td style="padding:8px 24px 20px 24px;">
    <p style="margin:0;color:#888;font-size:12px;">
      This login request started at: <strong>' . date('Y-m-d H:i:s') . '</strong> (server)<br><br>
      Technical info (for your security):<br>
      IP: ' . htmlspecialchars($ip) . '<br>
      Browser: ' . htmlspecialchars($ua) . '<br>
      Mail sent from: Zebras System
    </p>
  </td>
</tr>

            </table>
          </body>
        </html>';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Zebras System <no-reply@zebras.se>\r\n";

        @mail($user['email'], $subject, $html, $headers);

        $_SESSION['login_method'] = 'token_or_link';
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
