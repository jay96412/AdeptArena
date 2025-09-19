<?php
// AdeptArena Installer
session_start();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];

    // 1. Create config.php file
    $config_content = "<?php
// Database Configuration
define('DB_HOST', '$db_host');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('DB_NAME', '$db_name');

// Establish Connection
\$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!\$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>";

    if (!file_put_contents('common/config.php', $config_content)) {
        $error = 'Failed to write config file. Please check folder permissions.';
    } else {
        // 2. Connect to MySQL and create database
        $conn_check = @mysqli_connect($db_host, $db_user, $db_pass);
        if (!$conn_check) {
            $error = 'Could not connect to MySQL server. Please check your credentials.';
        } else {
            $sql_create_db = "CREATE DATABASE IF NOT EXISTS `$db_name`";
            if (!mysqli_query($conn_check, $sql_create_db)) {
                $error = 'Error creating database: ' . mysqli_error($conn_check);
            } else {
                mysqli_select_db($conn_check, $db_name);

                // 3. Create Tables
                $sql_queries = [
                    "CREATE TABLE `users` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `username` varchar(50) NOT NULL UNIQUE,
                      `email` varchar(100) NOT NULL UNIQUE,
                      `password` varchar(255) NOT NULL,
                      `wallet_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                    "CREATE TABLE `admin` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `username` varchar(50) NOT NULL,
                      `password` varchar(255) NOT NULL,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                    "CREATE TABLE `tournaments` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `title` varchar(100) NOT NULL,
                      `game_name` varchar(50) NOT NULL,
                      `entry_fee` decimal(10,2) NOT NULL,
                      `prize_pool` decimal(10,2) NOT NULL,
                      `match_time` datetime NOT NULL,
                      `room_id` varchar(50) DEFAULT NULL,
                      `room_password` varchar(50) DEFAULT NULL,
                      `status` enum('upcoming','live','completed','cancelled') NOT NULL DEFAULT 'upcoming',
                      `winner_id` int(11) DEFAULT NULL,
                      `commission_percentage` int(3) NOT NULL DEFAULT 20,
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                    "CREATE TABLE `participants` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `user_id` int(11) NOT NULL,
                      `tournament_id` int(11) NOT NULL,
                      PRIMARY KEY (`id`),
                      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
                      FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                    "CREATE TABLE `transactions` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `user_id` int(11) NOT NULL,
                      `amount` decimal(10,2) NOT NULL,
                      `type` enum('credit','debit') NOT NULL,
                      `description` varchar(255) NOT NULL,
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
                ];

                $all_ok = true;
                foreach ($sql_queries as $query) {
                    if (!mysqli_query($conn_check, $query)) {
                        $error .= 'Error creating table: ' . mysqli_error($conn_check) . '<br>';
                        $all_ok = false;
                        break;
                    }
                }

                if ($all_ok) {
                    // 4. Insert default admin
                    $admin_user = 'admin';
                    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
                    $sql_insert_admin = "INSERT INTO `admin` (`username`, `password`) VALUES ('$admin_user', '$admin_pass')";
                    if (mysqli_query($conn_check, $sql_insert_admin)) {
                        $success = 'Installation successful! The config file has been created. You will be redirected to the login page.';
                        header('Refresh: 3; url=login.php');
                    } else {
                        $error = 'Error inserting default admin: ' . mysqli_error($conn_check);
                    }
                }
            }
            mysqli_close($conn_check);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>AdeptArena - Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            -webkit-user-select: none; /* Safari */
            -ms-user-select: none; /* IE 10 and IE 11 */
            user-select: none; /* Standard syntax */
        }
    </style>
</head>
<body class="bg-gray-900 text-white font-sans flex items-center justify-center min-h-screen">

<div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-md">
    <div class="text-center mb-6">
        <h1 class="text-3xl font-bold text-purple-400">AdeptArena Installer</h1>
        <p class="text-gray-400">Database Configuration</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-500/20 text-red-300 p-3 rounded-md mb-4 text-center"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-500/20 text-green-300 p-3 rounded-md mb-4 text-center"><?php echo $success; ?></div>
    <?php else: ?>
        <form action="install.php" method="POST">
            <div class="space-y-4">
                <div>
                    <label for="db_host" class="block mb-2 text-sm font-medium text-gray-300">Database Host</label>
                    <input type="text" name="db_host" id="db_host" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" value="localhost" required>
                </div>
                <div>
                    <label for="db_name" class="block mb-2 text-sm font-medium text-gray-300">Database Name</label>
                    <input type="text" name="db_name" id="db_name" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" value="adeptarena_db" required>
                </div>
                <div>
                    <label for="db_user" class="block mb-2 text-sm font-medium text-gray-300">Database Username</label>
                    <input type="text" name="db_user" id="db_user" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" value="root" required>
                </div>
                <div>
                    <label for="db_pass" class="block mb-2 text-sm font-medium text-gray-300">Database Password</label>
                    <input type="password" name="db_pass" id="db_pass" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5">
                </div>
            </div>
            <button type="submit" class="w-full mt-6 text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Install Now</button>
        </form>
    <?php endif; ?>
</div>

<script>
    // Disable right-click
    document.addEventListener('contextmenu', event => event.preventDefault());
</script>

</body>
</html>