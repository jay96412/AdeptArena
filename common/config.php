<?php
// Database Configuration
define('DB_HOST', 'sql110.infinityfree.com');
define('DB_USER', 'if0_39967676');
define('DB_PASS', 'Aarsh5001');
define('DB_NAME', 'if0_39967676_adeptdb');;

define('APP_NAME', 'AdeptArena');
define('REFERRAL_BONUS', 50); // Referral bonus amount (in ₹)
define('COMMISSION_RATE', 2); // Commission percentage (e.g., 2 for 2%)

// Establish Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config_secrets.php';
?>