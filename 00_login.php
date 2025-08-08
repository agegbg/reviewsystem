<?php
// 00_login.php
// Static message + LOGIN button
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Required</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body {
            padding-top: 100px;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        .btn-login {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<h3>Login Required</h3>
<p>You must be logged in to use the system.</p>

<a href="01_select_user.php" class="btn btn-primary btn-login">LOGIN</a>

</body>
</html>
