<?php
// config/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Deteksi Base URL secara otomatis
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$dir_name = str_replace('\\', '/', dirname($script_name));

// Path ke folder CBT utama
$base_folder = '/CBT';
if (strpos($dir_name, '/CBT') !== false) {
    $sub_pos = strpos($dir_name, '/CBT') + 4;
    $base_path = substr($dir_name, 0, $sub_pos);
} else {
    $base_path = rtrim($dir_name, '/');
}
define('BASE_URL', $protocol . $host . $base_path);
define('BASE_PATH', dirname(__DIR__));

// Timezone Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// Database Default Config (XAMPP Standard)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_db');
define('DB_CHARSET', 'utf8mb4');

// Default App Settings
define('APP_NAME', 'CBT SMART EXAM');
define('APP_VERSION', '2.5.0');
define('APP_INSTITUTION', 'Lembaga Pendidikan Unggulan Nusantara');
