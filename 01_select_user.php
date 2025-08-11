<?php
session_start();
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'User selects name, role decides login path');

$pdo = getDatabaseConnection();
$error = '';
$nameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameInput = trim($_POST['name'] ?? '');

    if (!$nameInput) {
        $error = "Please enter your name.";
    } else {
        // Look up user
        $stmt = $pdo->prepare("SELECT * FROM review_user WHERE name = ?");
        $stmt->execute([$nameInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['pending_user_id'] = $user['id'];
            $_SESSION['pending_user_name'] = $user['name'];

            // Look up roles
            $stmt = $pdo->prepare("
                SELECT r.code 
                FROM review_user_roles ur 
                JOIN review_role r ON ur.role_id = r.id 
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$user['id']]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $_SESSION['user_roles'] = $roles;

            if (in_array('admin', $roles)) {
                header("Location: 00_login_admin.php");
                exit;
            } else {
                header("Location: 00_login_token.php");
                exit;
            }
        } else {
            $error = "No user found with that name.";
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
            <h5>Select Your Name</h5>
            <img src="image/zebraz.jpg" alt="Zebraz Logo">
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($nameInput) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Continue</button>
        </form>
    </div>
</body>
</html>
