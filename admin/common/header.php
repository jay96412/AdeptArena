<?php
require_once '../common/config.php';

// Admin session check
if (!isset($_SESSION['admin_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: login.php');
    exit();
}

$admin_username = '';
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $query = "SELECT username FROM admin WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $admin_username = $row['username'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>Admin - AdeptArena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/phosphor-icons/1.4.2/css/phosphor.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #111827; color: #f3f4f6; -webkit-user-select: none; user-select: none; }
        .main-content { padding-bottom: 80px; }
        /* Simple transition for sidebar links */
        aside a { transition: background-color 0.2s, color 0.2s; }
    </style>
</head>
<body class="overflow-x-hidden">

<div class="flex">
    <aside class="w-64 bg-gray-800 min-h-screen p-4 flex-shrink-0">
        <div class="mb-6 text-center">
             <a href="index.php" class="text-2xl font-bold text-purple-400">AdeptArena</a>
             <p class="text-xs text-gray-500">Admin Panel</p>
        </div>
        
        <nav class="space-y-2">
            <?php
            // ############ YEH HAI AAPKA NAYA, UPDATED MENU ############
            $admin_pages = [
                'index.php' => ['icon' => 'ph-fill ph-gauge', 'label' => 'Dashboard'],
                'tournament.php' => ['icon' => 'ph-fill ph-trophy', 'label' => 'Tournaments'],
                'opinions.php' => ['icon' => 'ph-fill ph-chats-circle', 'label' => 'Opinion Events'],
                'contests.php' => ['icon' => 'ph-fill ph-chats-circle', 'label' => 'Contests'],
                'user.php' => ['icon' => 'ph-fill ph-users', 'label' => 'Users'],
                'payments.php' => ['icon' => 'ph-fill ph-bank', 'label' => 'Deposits'],
                'withdrawals.php' => ['icon' => 'ph-fill ph-arrow-fat-lines-up', 'label' => 'Withdrawals'],
                'promos.php' => ['icon' => 'ph-fill ph-ticket', 'label' => 'Promo Codes'],
                'announcements.php' => ['icon' => 'ph-fill ph-megaphone', 'label' => 'Announcements'],
                'setting.php' => ['icon' => 'ph-fill ph-gear', 'label' => 'Settings'],
            ];
            $current_admin_page = basename($_SERVER['PHP_SELF']);

            foreach ($admin_pages as $page => $details) {
                $active_class = ($current_admin_page == $page) 
                    ? 'bg-purple-600 text-white shadow-lg' 
                    : 'text-gray-300 hover:bg-gray-700 hover:text-white';
                
                echo "<a href='$page' class='flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors $active_class'>";
                echo "<i class='{$details['icon']} text-lg'></i>";
                echo "<span>{$details['label']}</span>";
                echo "</a>";
            }
            ?>
        </nav>
    </aside>

    <div class="flex-1">
        <header class="bg-gray-800/80 backdrop-blur-sm p-4 shadow-md flex justify-between items-center sticky top-0 z-10 border-b border-gray-700">
            <h1 class="text-xl font-semibold text-white">Welcome, <?= htmlspecialchars($admin_username) ?></h1>
            <a href="logout.php" class="text-red-400 text-sm hover:underline flex items-center gap-1">
                <i class="ph-fill ph-sign-out"></i>
                <span>Logout</span>
            </a>
        </header>

        <main class="p-6">