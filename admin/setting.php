<?php
include 'common/header.php';
$message = '';
$message_type = '';
$admin_id = $_SESSION['admin_id'];

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];

    $stmt_pass = mysqli_prepare($conn, "SELECT password FROM admin WHERE id = ?");
    mysqli_stmt_bind_param($stmt_pass, "i", $admin_id);
    mysqli_stmt_execute($stmt_pass);
    $admin_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pass));

    if (password_verify($current_pass, $admin_data['password'])) {
        $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt_update = mysqli_prepare($conn, "UPDATE admin SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update, "si", $hashed_new_pass, $admin_id);
        if(mysqli_stmt_execute($stmt_update)){
            $message = 'Password changed successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to change password.';
            $message_type = 'error';
        }
    } else {
        $message = 'Incorrect current password.';
        $message_type = 'error';
    }
}
?>

<h1 class="text-3xl font-bold text-white mb-6">Admin Settings</h1>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-md text-center max-w-md mx-auto <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="bg-gray-800 p-6 rounded-lg shadow-md max-w-md mx-auto">
    <h2 class="text-xl font-semibold mb-4">Change Admin Password</h2>
    <form method="POST">
        <input type="hidden" name="change_password">
        <div class="space-y-4">
            <div>
                <label class="block mb-2 text-sm">Current Password</label>
                <input type="password" name="current_password" class="bg-gray-700 w-full p-2.5 rounded-lg" required>
            </div>
            <div>
                <label class="block mb-2 text-sm">New Password</label>
                <input type="password" name="new_password" class="bg-gray-700 w-full p-2.5 rounded-lg" required>
            </div>
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 px-4 rounded-lg">Update Password</button>
        </div>
    </form>
</div>

<?php include 'common/bottom.php'; ?>