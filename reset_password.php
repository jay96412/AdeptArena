<?php
require_once 'common/config.php';
$message = '';
$message_type = '';
$token_valid = false;
$token = $_GET['token'] ?? '';
$email = '';

if (!empty($token)) {
    $current_time = date("Y-m-d H:i:s");
    $stmt = mysqli_prepare($conn, "SELECT email FROM password_resets WHERE token = ? AND expires_at > ?");
    mysqli_stmt_bind_param($stmt, "ss", $token, $current_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $email = $row['email'];
        $token_valid = true;
    } else {
        $message = "This password reset link is invalid or has expired.";
        $message_type = 'error';
    }
} else {
    $message = "No reset token provided.";
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token_from_form = $_POST['token'];
    $password = $_POST['password'];
    $email_from_form = $_POST['email']; 
    
    $current_time = date("Y-m-d H:i:s");
    $stmt_reverify = mysqli_prepare($conn, "SELECT email FROM password_resets WHERE token = ? AND email = ? AND expires_at > ?");
    mysqli_stmt_bind_param($stmt_reverify, "sss", $token_from_form, $email_from_form, $current_time);
    mysqli_stmt_execute($stmt_reverify);
    $result_reverify = mysqli_stmt_get_result($stmt_reverify);

    if (mysqli_num_rows($result_reverify) > 0) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt_update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
        mysqli_stmt_bind_param($stmt_update, "ss", $hashed_password, $email_from_form);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $stmt_delete = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email = ?");
            mysqli_stmt_bind_param($stmt_delete, "s", $email_from_form);
            mysqli_stmt_execute($stmt_delete);
            $message = "Your password has been reset successfully!";
            $message_type = 'success';
            $token_valid = false; 
        } else {
            $message = "Failed to reset password. Please try again.";
            $message_type = 'error';
        }
    } else {
        $message = "Invalid or expired token. Please request a new link.";
        $message_type = 'error';
        $token_valid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Reset Password - AdeptArena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .form-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); }
        .form-input { padding-left: 3rem !important; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-purple-900/50 text-white flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800/60 backdrop-blur-sm border border-gray-700 p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-6">
            <i class="ph-fill ph-shield-check text-5xl text-purple-400"></i>
            <h1 class="text-2xl font-bold mt-2">Set New Password</h1>
        </div>
        
        <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 text-center text-sm <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($token_valid): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="reset_password">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <div>
                <label for="password" class="sr-only">New Password</label>
                <div class="relative"><i class="ph ph-lock-key form-icon text-gray-400"></i><input type="password" name="password" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" placeholder="Enter new password" required></div>
            </div>
            <button type="submit" class="w-full text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg px-5 py-3 text-center transition-transform hover:scale-105">Reset Password</button>
        </form>
        <?php elseif ($message_type === 'success'): ?>
             <a href="login.php" class="block text-center w-full mt-4 text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg px-5 py-3">Proceed to Login</a>
        <?php else: ?>
            <a href="login.php" class="block text-center mt-4 text-sm text-gray-400 hover:text-white">Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>