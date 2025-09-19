<?php
require_once 'common/config.php';
$error = '';
$success = '';

// If already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- SIGN UP LOGIC ---
    if (isset($_POST['signup'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $referral_code_input = trim($_POST['referral_code']);

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
            mysqli_stmt_bind_param($stmt_check, "ss", $username, $email);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($result_check) > 0) {
                $error = 'Username or email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_insert, "sss", $username, $email, $hashed_password);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $new_user_id = mysqli_insert_id($conn);
                    
                    mysqli_begin_transaction($conn);
                    try {
                        $new_ref_code = 'AD' . strtoupper(substr(md5($new_user_id . time()), 0, 6));
                        mysqli_query($conn, "UPDATE users SET referral_code = '$new_ref_code' WHERE id = $new_user_id");

                        if (!empty($referral_code_input)) {
                            $stmt_ref = mysqli_prepare($conn, "SELECT id FROM users WHERE referral_code = ?");
                            mysqli_stmt_bind_param($stmt_ref, "s", $referral_code_input);
                            mysqli_stmt_execute($stmt_ref);
                            $result_ref = mysqli_stmt_get_result($stmt_ref);
                            if ($referrer = mysqli_fetch_assoc($result_ref)) {
                                $referrer_id = $referrer['id'];
                                $bonus_amount = 50;
                                
                                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $bonus_amount WHERE id = $referrer_id");
                                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $bonus_amount WHERE id = $new_user_id");
                                mysqli_query($conn, "UPDATE users SET referred_by = $referrer_id WHERE id = $new_user_id");
                            }
                        }
                        mysqli_commit($conn);
                        $success = 'Registration successful! Please login.';
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = "A database error occurred during signup.";
                    }
                } else {
                    $error = 'Could not create user.';
                }
            }
        }
    }

    // --- LOGIN LOGIC ---
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id, password, status FROM users WHERE username = ? OR email = ?");
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] == 'blocked') {
                        $error = 'Your account has been blocked. Please contact support.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        header('Location: index.php');
                        exit();
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Welcome - AdeptArena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { -webkit-user-select: none; user-select: none; }
        .form-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); }
        .form-input { padding-left: 3rem !important; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-purple-900/50 text-white flex items-center justify-center min-h-screen p-4">

<div class="bg-gray-800/60 backdrop-blur-sm border border-gray-700 p-8 rounded-2xl shadow-2xl w-full max-w-md">
    <div class="text-center mb-8">
        <i class="ph-fill ph-sword text-5xl text-purple-400"></i>
        <h1 class="text-3xl font-bold text-white mt-2">AdeptArena</h1>
        <p class="text-gray-400">Your Ultimate Gaming Hub</p>
    </div>

    <div class="mb-4 border-b border-gray-700">
        <ul class="flex -mb-px text-sm font-medium text-center">
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="login-tab-btn" type="button">Login</button></li>
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="signup-tab-btn" type="button">Sign Up</button></li>
        </ul>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-500/20 text-red-300 p-3 rounded-lg mb-4 text-center text-sm flex items-center gap-2"><i class="ph-fill ph-warning-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-500/20 text-green-300 p-3 rounded-lg mb-4 text-center text-sm flex items-center gap-2"><i class="ph-fill ph-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div id="login-form">
        <form action="login.php" method="POST" class="space-y-4">
            <input type="hidden" name="login">
            <div>
                <label for="username" class="sr-only">Username or Email</label>
                <div class="relative"><i class="ph ph-user form-icon text-gray-400"></i><input type="text" name="username" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" placeholder="Username or Email" required></div>
            </div>
            <div>
                <label for="password" class="sr-only">Password</label>
                <div class="relative"><i class="ph ph-lock-key form-icon text-gray-400"></i><input type="password" name="password" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" placeholder="Password" required></div>
            </div>
            <div class="text-right mt-2">
                <a href="forgot_password.php" class="text-sm font-medium text-purple-400 hover:underline">Forgot Password?</a>
            </div>
            <button type="submit" class="w-full text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-5 py-3 text-center transition-transform hover:scale-105">Sign In</button>
        </form>
    </div>

    <div id="signup-form" class="hidden">
        <form action="login.php" method="POST" class="space-y-4">
            <input type="hidden" name="signup">
            <div><div class="relative"><i class="ph ph-user-circle form-icon text-gray-400"></i><input type="text" name="username" placeholder="Username" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" required></div></div>
            <div><div class="relative"><i class="ph ph-envelope-simple form-icon text-gray-400"></i><input type="email" name="email" placeholder="Email" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" required></div></div>
            <div><div class="relative"><i class="ph ph-lock-key form-icon text-gray-400"></i><input type="password" name="password" placeholder="Password" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3" required></div></div>
            <div><div class="relative"><i class="ph ph-ticket form-icon text-gray-400"></i><input type="text" name="referral_code" placeholder="Referral Code (Optional)" class="form-input bg-gray-700/50 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-3"></div></div>
            <button type="submit" class="w-full mt-2 text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-5 py-3 text-center transition-transform hover:scale-105">Create Account</button>
        </form>
    </div>
</div>

<script>
    const loginTabBtn = document.getElementById('login-tab-btn');
    const signupTabBtn = document.getElementById('signup-tab-btn');
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    const activeClasses = ['border-purple-500', 'text-purple-500'];
    const inactiveClasses = ['border-transparent', 'text-gray-400', 'hover:text-gray-300', 'hover:border-gray-500'];

    function showLogin() {
        loginForm.classList.remove('hidden');
        signupForm.classList.add('hidden');
        loginTabBtn.classList.add(...activeClasses);
        loginTabBtn.classList.remove(...inactiveClasses);
        signupTabBtn.classList.add(...inactiveClasses);
        signupTabBtn.classList.remove(...activeClasses);
    }

    function showSignup() {
        signupForm.classList.remove('hidden');
        loginForm.classList.add('hidden');
        signupTabBtn.classList.add(...activeClasses);
        signupTabBtn.classList.remove(...inactiveClasses);
        loginTabBtn.classList.add(...inactiveClasses);
        loginTabBtn.classList.remove(...activeClasses);
    }

    loginTabBtn.addEventListener('click', showLogin);
    signupTabBtn.addEventListener('click', showSignup);
    
    showLogin();
    document.addEventListener('contextmenu', event => event.preventDefault());
</script>

</body>
</html>