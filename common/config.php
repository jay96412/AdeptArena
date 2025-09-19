<?php
// Railway, server par in variables ko apne aap daal deta hai.
// getenv() function inki value nikal leta hai.
define('DB_HOST', getenv('mysql.railway.internal'));
define('DB_PORT', getenv('3306'));
define('DB_USER', getenv('root'));
define('DB_PASS', getenv('CnniWNfSXMjQfvrnTWojuUBRDkPKjrMV'));
define('DB_NAME', getenv('railway'));

// Application Settings
define('APP_NAME', 'AdeptArena');
define('REFERRAL_BONUS', 50);
define('COMMISSION_RATE', 2);

// Secret keys file (agar use kar rahe hain)
if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once 'config_secrets.php';
}

// MySQL Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>