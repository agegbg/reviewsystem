<?php
session_start();
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'User selects which role to use during the session');

$roles = $_SESSION['user_roles'] ?? [];
$name = $_SESSION['user_name'] ?? '';
$selected = $_POST['role'] ?? '';

if (!$roles || count($roles) <= 1) {
    // No choice needed — default to first
    $_SESSION['active_role'] = $roles[0] ?? 'referee';
    header("Location: 01_mypage.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($selected, $roles)) {
    $_SESSION['active_role'] = $selected;
    $_SESSION['message'] = "You are now logged in as " . ucfirst($selected) . ".";
    header("Location: 01_mypage.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Your Role</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h3>Welcome <?= htmlspecialchars($name) ?></h3>
    <p>You have multiple roles. Please select which one you want to use for this session:</p>

    <form method="post">
        <?php foreach ($roles as $role): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="role" value="<?= htmlspecialchars($role) ?>" id="role_<?= $role ?>" required>
                <label class="form-check-label" for="role_<?= $role ?>">
                    <?= ucfirst($role) ?>
                </label>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary mt-3">Continue</button>
    </form>
</body>
</html>
