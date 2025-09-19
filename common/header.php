<?php
// Step 1: Include the configuration file to connect to the DB and start the session
require_once 'config.php';

// Step 2: Check if the user is logged in.
// If not logged in, redirect them to the login page,
// unless they are already on a page that doesn't require login.
if (!isset($_SESSION['user_id']) 
    && basename($_SERVER['PHP_SELF']) != 'login.php' 
    && basename($_SERVER['PHP_SELF']) != 'install.php' 
    && basename($_SERVER['PHP_SELF']) != 'upgrade.php'
    && basename($_SERVER['PHP_SELF']) != 'upgrade_v2.php'
    && basename($_SERVER['PHP_SELF']) != 'forgot_password.php'
    && basename($_SERVER['PHP_SELF']) != 'reset_password.php'
) {
    header('Location: login.php');
    exit();
}

// Step 3: Fetch the logged-in user's wallet balance to display in the header.
$wallet_balance = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT wallet_balance FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $wallet_balance = $row['wallet_balance'];
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>AdeptArena</title>

    <script src="https://cdn.tailwindcss.com"></script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Base Styling for an App-like Feel */
        body {
            background-color: #111827; /* Dark background */
            color: #f3f4f6; /* Light text */
            font-family: 'Inter', sans-serif, system-ui; /* Modern font */
            -webkit-user-select: none; /* Disable text selection */
            -ms-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent; /* Disable tap highlight on mobile */
            font-family: 'Inter', sans-serif;
        }
        .main-content {
            padding-bottom: 80px; /* Space for bottom nav */
            padding-top: 70px; /* Space for top nav */
        }

        /* Styling for Toast Notifications (used by AJAX) */
        .toast {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .toast.success { background-color: #22c55e; } /* Green */
        .toast.error { background-color: #ef4444; } /* Red */

        .card-bg {
    background-color: #1f2937; /* bg-gray-800 */
    opacity: 1;
    background-image: radial-gradient(#4b5563 0.5px, #1f2937 0.5px);
    background-size: 10px 10px;
}
        
    </style>
</head>
<body class="overflow-x-hidden">

<header class="bg-gray-800/80 backdrop-blur-sm fixed top-0 left-0 right-0 z-50 border-b border-gray-700">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <a href="index.php" class="text-2xl font-bold text-purple-400">AdeptArena</a>
        
        <?php // Show wallet balance only if user is logged in
        if (isset($_SESSION['user_id'])): ?>
        <a href="wallet.php" class="bg-gray-700 text-white px-4 py-2 rounded-full text-sm font-semibold flex items-center gap-2">
            <i class="ph-fill ph-wallet"></i>
            <span id="wallet-balance">₹<?php echo number_format($wallet_balance, 2); ?></span>
        </a>
        <?php endif; ?>
    </div>
</header>

<main class="main-content container mx-auto px-4">