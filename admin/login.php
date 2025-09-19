<?php
require_once '../common/config.php';
$error = '';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, password FROM admin WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($admin = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid credentials.';
            }
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Admin Login - AdeptArena</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
<div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-sm">
    <div class="text-center mb-6">
        <h1 class="text-3xl font-bold text-purple-400">Admin Login</h1>
    </div>
    <?php if ($error): ?>
        <div class="bg-red-500/20 text-red-300 p-3 rounded-md mb-4 text-center text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <div class="space-y-4">
            <div>
                <label for="username" class="block mb-2 text-sm font-medium text-gray-300">Username</label>
                <input type="text" name="username" class="bg-gray-700 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-2.5" required>
            </div>
            <div>
                <label for="password" class="block mb-2 text-sm font-medium text-gray-300">Password</label>
                <input type="password" name="password" class="bg-gray-700 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-2.5" required>
            </div>
        </div>
        <button type="submit" class="w-full mt-6 text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Login</button>
    </form>
</div>
<script>document.addEventListener('contextmenu', e => e.preventDefault());</script>
</body>
</html>