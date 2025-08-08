<?php
// Start session and check login
require_once 'php/session.php';
require_once 'php/db.php';

$pdo = getDatabaseConnection();

// Get current user ID from session
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: 00_login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $city = $_POST['city'] ?? '';
    $license = $_POST['license_number'] ?? '';
    $photo_filename = null;

    // Handle file upload if photo provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_filename = 'user_' . $user_id . '.' . strtolower($ext);
        move_uploaded_file($_FILES['photo']['tmp_name'], 'photo/' . $photo_filename);
    }

    // Check if user_info row exists
    $stmt = $pdo->prepare("SELECT id FROM review_user_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        // Update existing row
        $sql = "UPDATE review_user_info SET city = ?, license_number = ?";
        $params = [$city, $license];

        if ($photo_filename) {
            $sql .= ", photo = ?";
            $params[] = $photo_filename;
        }

        $sql .= " WHERE user_id = ?";
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Insert new row
        $stmt = $pdo->prepare("INSERT INTO review_user_info (user_id, city, license_number, photo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $city, $license, $photo_filename]);
    }

    header("Location: 01_mypage.php?updated=1");
    exit;
}

// Fetch existing info to pre-fill form
$stmt = $pdo->prepare("SELECT * FROM review_user_info WHERE user_id = ?");
$stmt->execute([$user_id]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Edit My Info</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h3>Edit My Info</h3>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($info['city'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>License Number</label>
            <input type="text" name="license_number" class="form-control" value="<?= htmlspecialchars($info['license_number'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Photo</label><br>
            <?php if (!empty($info['photo'])): ?>
                <img src="photo/<?= htmlspecialchars($info['photo']) ?>" alt="Current Photo" style="max-width:150px;" class="mb-2"><br>
            <?php endif; ?>
            <input type="file" name="photo" class="form-control-file">
        </div>

        <button type="submit" class="btn btn-success">💾 Save</button>
        <a href="01_mypage.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
