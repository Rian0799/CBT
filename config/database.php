<?php
// config/database.php
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = DB_HOST;
            $port = DB_PORT;
            $db   = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASS;
            $charset = DB_CHARSET;

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
                // Self-healing migration untuk kolom question_settings
                try {
                    $checkCol = self::$instance->query("SHOW COLUMNS FROM `question_banks` LIKE 'question_settings'");
                    if ($checkCol && !$checkCol->fetch()) {
                        self::$instance->exec("ALTER TABLE `question_banks` ADD COLUMN `question_settings` LONGTEXT NULL AFTER `kkm`");
                    }
                } catch (Exception $e) {}
            } catch (PDOException $e) {
                // Jika database belum dibuat (Unknown database), coba hubungkan ke server saja dan buatkan otomatis
                if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        $tempPdo = new PDO("mysql:host=$host;port=$port;charset=$charset", $user, $pass, $options);
                        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                        self::$instance = new PDO($dsn, $user, $pass, $options);
                    } catch (Exception $ex) {
                        self::renderDbError("Database Belum Siap", "Database '$db' belum ada. Silakan jalankan installer otomatis.", true);
                    }
                } else {
                    self::renderDbError("Koneksi Database Gagal", $e->getMessage(), false);
                }
            }
        }

        return self::$instance;
    }

    private static function renderDbError(string $title, string $message, bool $showInstallBtn): void {
        $installUrl = BASE_URL . '/database/install.php';
        echo "<!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>$title - " . APP_NAME . "</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
        </head>
        <body class='bg-slate-100 min-h-screen flex items-center justify-center p-4 font-sans'>
            <div class='max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-200 text-center'>
                <div class='w-16 h-16 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl shadow-sm'>
                    <i class='fa-solid fa-database'></i>
                </div>
                <h1 class='text-2xl font-bold text-slate-800 mb-2'>$title</h1>
                <p class='text-slate-600 text-sm mb-6'>$message</p>
                <div class='bg-slate-50 rounded-xl p-4 text-left text-xs text-slate-500 font-mono mb-6 border border-slate-200'>
                    <div><strong>Host:</strong> " . DB_HOST . ":" . DB_PORT . "</div>
                    <div><strong>User:</strong> " . DB_USER . "</div>
                    <div><strong>Database:</strong> " . DB_NAME . "</div>
                    <div class='mt-2 text-indigo-600'>Pastikan Apache & MySQL di XAMPP Control Panel sudah aktif (Running).</div>
                </div>";
        if ($showInstallBtn) {
            echo "<a href='$installUrl' class='inline-flex items-center justify-center w-full px-5 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-200'>
                    <i class='fa-solid fa-wand-magic-sparkles mr-2'></i> Jalankan Instalasi Database Otomatis
                  </a>";
        } else {
            echo "<a href='$installUrl' class='inline-flex items-center justify-center w-full px-5 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-200'>
                    <i class='fa-solid fa-rotate mr-2'></i> Coba Lagi / Pasang Database
                  </a>";
        }
        echo "</div>
        </body>
        </html>";
        exit;
    }
}
