<?php
session_start();
include 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = md5($_POST['password']);

        $sql = "SELECT * FROM admins WHERE username='$username' AND password='$password'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $_SESSION['admin'] = $username;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "गलत यूज़रनेम या पासवर्ड!";
        }
    }

    if (isset($_POST['change_password'])) {
        $old = md5($_POST['old_password']);
        $new = md5($_POST['new_password']);
        $confirm = md5($_POST['confirm_password']);

        if ($new !== $confirm) {
            $error = "नया पासवर्ड मेल नहीं खाता!";
        } else {
            $sql = "UPDATE admins SET password='$new' WHERE password='$old'";
            if ($conn->query($sql) === TRUE && $conn->affected_rows > 0) {
                $success = "पासवर्ड सफलतापूर्वक बदला गया!";
            } else {
                $error = "पुराना पासवर्ड गलत!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>लॉगिन | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .logo {
            font-size: 32px;
            font-weight: 700;
            color: #764ba2;
            margin-bottom: 10px;
        }
        .tagline {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .tab-buttons {
            display: flex;
            margin-bottom: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: #f1f1f1;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .tab-btn.active {
            background: #764ba2;
            color: white;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #444;
            font-weight: 500;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.2);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #764ba2;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn:hover {
            background: #5a3780;
            transform: translateY(-2px);
        }
        .error, .success {
            padding: 10px;
            margin: 15px 0;
            border-radius: 8px;
            font-size: 14px;
        }
        .error { background: #ffe6e6; color: #d00; border: 1px solid #fcc; }
        .success { background: #e6f7e6; color: #0a0; border: 1px solid #b8e6b8; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">The-Reading-Zone</div>
    <p class="tagline">आपकी अपनी डिजिटल लाइब्रेरी</p>

    <div class="tab-buttons">
        <button class="tab-btn active" onclick="openTab('login')">लॉगिन</button>
        <button class="tab-btn" onclick="openTab('change')">पासवर्ड बदलें</button>
    </div>

    <?php if($error) echo "<div class='error'>$error</div>"; ?>
    <?php if($success) echo "<div class='success'>$success</div>"; ?>

    <!-- Login Tab -->
    <div id="login" class="tab-content active">
        <form method="post">
            <div class="form-group">
                <label>यूज़रनेम</label>
                <input type="text" name="username" required placeholder="admin">
            </div>
            <div class="form-group">
                <label>पासवर्ड</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" name="login" class="btn">लॉगिन करें</button>
        </form>
    </div>

    <!-- Change Password Tab -->
    <div id="change" class="tab-content">
        <form method="post">
            <div class="form-group">
                <label>पुराना पासवर्ड</label>
                <input type="password" name="old_password" required>
            </div>
            <div class="form-group">
                <label>नया पासवर्ड</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>पासवर्ड दोबारा डालें</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn">पासवर्ड बदलें</button>
        </form>
    </div>
</div>

<script>
function openTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    document.querySelector(`button[onclick="openTab('${tabName}')"]`).classList.add('active');
}
</script>

</body>
</html>