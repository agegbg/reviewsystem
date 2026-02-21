<?php
session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'User selects name, role decides login path');

$pdo = getDatabaseConnection();
$error = '';
$nameInput = '';

// ... keep includes as they are

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = trim($_POST['email'] ?? '');

    if (!$emailInput) {
        $error = "Please enter your email.";
    } else {
        // Look up user by email instead of name
        $stmt = $pdo->prepare("SELECT * FROM review_user WHERE email = ?");
        $stmt->execute([$emailInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

         if ($user) {
            // Store pending user info in session
            $_SESSION['pending_user_id']    = (int)$user['id'];
            $_SESSION['pending_user_email'] = $user['email'];
            $_SESSION['pending_user_name']  = $user['name'] ?? null;

            // Always continue to token/magic-link step
            header("Location: 00_login_token.php");
            exit;
        } else {
            $error = "No user found with that email.";
        }
    }
}


// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select User</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .form-container {
            max-width: 500px;
            margin: 100px auto 0;
            background: white;
            padding: 30px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .form-header h5 {
            margin: 0;
            font-weight: 500;
            color: #333;
        }
        .form-header img {
            width: 50px;
            height: auto;
        }
    </style>
</head>
<body>

   <div class="form-container">
        <!-- Header with text and logo side-by-side --> 
        <div class="form-header">
            <h5>Sign in with Email</h5>
            <img src="image/zebraz.jpg" alt="Zebraz Logo">
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($emailInput) ?>"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary btn-block">Continue</button>
        </form>
    </div>
</body>
</html>