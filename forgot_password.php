<?php
require_once 'common/config.php';
// PHPMailer library ki files ko include karein
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$message = '';
$message_type = '';
$form_hidden = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    
    // Check if user exists with this email
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Generate a secure token and expiry time
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600); // Token expires in 1 hour

        // Store the token in the database
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "sss", $email, $token, $expires);
        mysqli_stmt_execute($stmt_insert);

        // Email Sending Logic using SendGrid
        $mail = new PHPMailer(true);
       


        try {
            //Server settings for SendGrid
            $mail->isSMTP();
            $mail->Host       = 'smtp.sendgrid.net';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'apikey'; // Yeh 'apikey' hi rahega
            $mail->Password = SENDGRID_API_KEY; // Ab key secret file se aa rahi hai
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            //Recipients
            // IMPORTANT: Yahan apna VERIFIED sender email daalein
            $mail->setFrom('gbolt029@gmail.com', 'AdeptArena Support'); 
            $mail->addAddress($email);

            //Content
            $base_url = "http://adeptarena1.rf.gd"; // Apna live domain yahan daalein
            $reset_link = $base_url . "/reset_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your AdeptArena Password';
            $mail->Body    = "Hello,<br><br>Click the link below to reset your password:<br><br><a href='$reset_link' style='padding:10px 15px; background-color:#7c3aed; color:white; text-decoration:none; border-radius:5px;'>Reset Password</a><br><br>If you did not request this, please ignore this email.<br><br>Thanks,<br>Team AdeptArena";
            
            $mail->send();
            $message = 'A password reset link has been sent to your email address.';
            $message_type = 'success';
            $form_hidden = true;
        } catch (Exception $e) {
            $message = "Message could not be sent. Please contact support.";
            // For debugging: $message = "Mailer Error: {$mail->ErrorInfo}";
            $message_type = 'error';
        }

    } else {
        $message = "No user found with that email address.";
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Forgot Password - AdeptArena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .form-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); }
        .form-input { padding-left: 3rem !important; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-purple-900/50 text-white flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800/60 backdrop-blur-sm border border-gray-700 p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <i class="ph-fill ph-key text-5xl text-purple-400"></i>
            <h1 class="text-3xl font-bold mt-2">Forgot Password</h1>
            <p class="text-gray-400">Don't worry, we'll help you out.</p>
        </div>
        
        <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 text-center text-sm <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if (!$form_hidden): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="forgot_password">
            <div>
                <label for="email" class="sr-only">Your Email</label>
                <div class="relative"><i class="ph ph-envelope-simple form-icon text-gray-400"></i><input type="email" name="email" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" placeholder="Your registered email" required></div>
            </div>
            <button type="submit" class="w-full mt-2 text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg px-5 py-3 text-center transition-transform hover:scale-105">Get Reset Link</button>
        </form>
        <?php endif; ?>
        <a href="login.php" class="block text-center mt-6 text-sm text-gray-400 hover:text-white">Back to Login</a>
    </div>
</body>
</html>