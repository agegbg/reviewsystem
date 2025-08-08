<?php
session_start();

// Ladda databas och registrera fil
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Inloggning med kod och manuell verifiering');

$pdo = getDatabaseConnection();

$error = '';
$step = 1;
$maskedEmail = '';
$nameInput = '';
$fullEmailInput = '';

// === STEP 1: Användaren skriver sitt namn ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == '1') {
    $nameInput = trim($_POST['name']);
    $stmt = $pdo->prepare("SELECT * FROM review_user WHERE name = ?");
    $stmt->execute([$nameInput]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $maskedEmail = maskEmail($user['email']);
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['masked_email'] = $maskedEmail;
        $step = 2;
    } else {
        $error = "Ingen användare hittades med det namnet.";
    }
}

// === STEP 2: Bekräfta e-post, skicka kod ===
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == '2') {
    $fullEmailInput = trim($_POST['full_email']);
    $pendingUserId = $_SESSION['pending_user_id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM review_user WHERE id = ?");
    $stmt->execute([$pendingUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && strtolower($user['email']) === strtolower($fullEmailInput)) {
        // Skapa kod
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT); // Ex: "034281"

        $expiry = date('Y-m-d H:i:s', time() + 15 * 60); // Giltig i 15 min

        // Spara kod
        $stmt = $pdo->prepare("UPDATE review_user SET login_token = ?, token_expiry = ? WHERE id = ?");
        $stmt->execute([$token, $expiry, $user['id']]);

        // Skicka e-post
        $subject = "Din inloggningskod";
        $message = "Hej " . htmlspecialchars($user['name']) . ",\n\nHär är din inloggningskod:\n\n$token\n\nKoden är giltig i 15 minuter.\n\nHälsningar,\nSystemet";
        $headers = "From: no-reply@zebras.se\r\n";

        @mail($user['email'], $subject, $message, $headers);

        // Hoppa till steg 3: Kodinmatning
        $_SESSION['masked_email'] = maskEmail($user['email']);
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['login_code_sent'] = true;
        $step = 3;
    } else {
        $error = "E-postadressen stämmer inte.";
        $step = 2;
        $maskedEmail = $_SESSION['masked_email'] ?? '';
    }
}

// === Funktion: Maskera e-postadress ===
function maskEmail($email) {
    $parts = explode("@", $email);
    if (count($parts) !== 2) return $email;

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
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Logga in med kod</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h3>🔐 Logga in</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <form method="post">
            <input type="hidden" name="step" value="1">
            <div class="form-group">
                <label for="name">Ditt namn:</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Fortsätt</button>
        </form>

    <?php elseif ($step === 2): ?>
        <form method="post">
            <input type="hidden" name="step" value="2">
            <p>Vi har hittat en användare. Bekräfta att detta är din e-postadress:</p>
            <p><strong><?= htmlspecialchars($_SESSION['masked_email'] ?? '') ?></strong></p>

            <div class="form-group">
                <label for="full_email">Fyll i hela e-postadressen:</label>
                <input type="email" name="full_email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success">Skicka kod</button>
        </form>

    <?php elseif ($step === 3): ?>
        <form action="00_verify.php" method="post">
            <div class="form-group">
                <label for="code">Fyll i den kod du fått via e-post:</label>
                <input type="text" name="code" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Verifiera kod</button>
        </form>
    <?php endif; ?>
</body>
</html>
