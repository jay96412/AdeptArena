<?php
include 'common/header.php';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Handle Avatar Upload
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    $target_dir = "uploads/avatars/";
    // Create a unique filename
    $imageFileType = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
    $new_filename = $user_id . '_' . time() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;

    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            // Update user's avatar in DB
            $stmt = mysqli_prepare($conn, "UPDATE users SET avatar = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_filename, $user_id);
            mysqli_stmt_execute($stmt);
            $message = "Profile picture updated!"; $message_type = 'success';
        } else {
            $message = "Sorry, there was an error uploading your file."; $message_type = 'error';
        }
    } else {
        $message = "File is not an image."; $message_type = 'error';
    }
}

// Fetch user data including avatar and referral code
$stmt_user = mysqli_prepare($conn, "SELECT username, email, avatar, referral_code FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));

// Fetch User Stats
$matches_played = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as count FROM participants WHERE user_id = $user_id"))['count'];
$tournaments_won = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as count FROM winners WHERE user_id = $user_id"))['count'];
$total_winnings_query = "SELECT SUM(prize_amount) as total FROM winners WHERE user_id = $user_id";
$total_winnings = mysqli_fetch_assoc(mysqli_query($conn, $total_winnings_query))['total'] ?? 0;
?>

<div class="py-6">
    <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 text-center shadow-lg">
                <div class="relative w-28 h-28 mx-auto mb-4">
                    <img src="uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="w-full h-full rounded-full object-cover border-4 border-purple-500">
                    <form id="avatarForm" method="POST" enctype="multipart/form-data" class="absolute bottom-0 right-0">
                        <label for="avatarUpload" class="bg-gray-900/80 text-white w-9 h-9 rounded-full flex items-center justify-center cursor-pointer hover:bg-purple-600 transition-all duration-300">
                            <i class="ph-fill ph-pencil-simple"></i>
                        </label>
                        <input type="file" name="avatar" id="avatarUpload" class="hidden" onchange="document.getElementById('avatarForm').submit();">
                    </form>
                </div>
                <h1 class="text-2xl font-bold text-white"><?= htmlspecialchars($user['username']) ?></h1>
                <p class="text-gray-400"><?= htmlspecialchars($user['email']) ?></p>
                <a href="logout.php" class="w-full block mt-6 text-red-400 bg-red-500/10 hover:bg-red-500/20 font-medium rounded-lg text-sm px-5 py-2.5 transition-all duration-300">
                    Logout
                </a>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700"><p class="text-2xl font-bold"><?= $matches_played ?></p><p class="text-xs text-gray-400">Played</p></div>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700"><p class="text-2xl font-bold"><?= $tournaments_won ?></p><p class="text-xs text-gray-400">Wins</p></div>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700"><p class="text-2xl font-bold text-green-400">₹<?= number_format($total_winnings) ?></p><p class="text-xs text-gray-400">Winnings</p></div>
            </div>
             <div class="mb-2">
                <a href="stats.php" class="block w-full text-center bg-blue-500/20 text-blue-300 font-semibold py-3 rounded-lg hover:bg-blue-500/30 transition-colors flex items-center justify-center gap-2">
                    <i class="ph-fill ph-chart-bar"></i> View Detailed Stats & Graphs
                </a>
            </div>
            
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 p-6 rounded-xl text-center">
                <h2 class="text-lg font-bold">Refer & Earn!</h2>
                <p class="text-purple-200 text-sm mb-3">Share your code with friends and get a bonus!</p>
                <div class="bg-black/20 p-3 rounded-lg inline-flex items-center gap-4">
                    <span id="refCode" class="font-bold tracking-widest text-lg"><?= htmlspecialchars($user['referral_code']) ?></span>
                    <button onclick="copyRef()" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1 rounded-md text-xs">COPY</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyRef() {
    const refCode = document.getElementById('refCode').innerText;
    navigator.clipboard.writeText(refCode).then(() => {
        Swal.fire({
            title: 'Copied!',
            text: 'Your referral code has been copied to the clipboard.',
            icon: 'success',
            background: '#1f2937',
            color: '#f9fafb'
        });
    });
}
</script>

<?php include 'common/bottom.php'; ?>