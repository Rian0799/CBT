<?php
// database/install.php
require_once __DIR__ . '/../config/config.php';

$message = '';
$status = '';
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    $host = $_POST['db_host'] ?? DB_HOST;
    $port = $_POST['db_port'] ?? DB_PORT;
    $user = $_POST['db_user'] ?? DB_USER;
    $pass = $_POST['db_pass'] ?? '';
    $dbname = $_POST['db_name'] ?? DB_NAME;

    try {
        $logs[] = "Menghubungkan ke MySQL Server di $host:$port...";
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $logs[] = "Berhasil terhubung ke server database.";

        $logs[] = "Membuat database `$dbname` jika belum ada...";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `$dbname`;");
        $logs[] = "Database `$dbname` siap digunakan.";

        // Jalankan schema.sql
        $schemaFile = __DIR__ . '/schema.sql';
        if (file_exists($schemaFile)) {
            $logs[] = "Mengeksekusi struktur tabel dari schema.sql...";
            $schemaSql = file_get_contents($schemaFile);
            $pdo->exec($schemaSql);
            $logs[] = "Struktur tabel berhasil dibuat (12 tabel siap).";
        }

        // Jalankan seed.sql
        $seedFile = __DIR__ . '/seed.sql';
        if (file_exists($seedFile)) {
            $logs[] = "Mengisi data awal & 6 jenis contoh soal dari seed.sql...";
            $seedSql = file_get_contents($seedFile);
            $pdo->exec($seedSql);
            $logs[] = "Data pengguna (Admin, Guru, Operator, Siswa), bank soal, dan paket ujian berhasil ditanam.";
        }

        $status = 'success';
        $message = "Instalasi dan inisialisasi database CBT berhasil 100%!";
    } catch (Exception $e) {
        $status = 'error';
        $message = "Terjadi kegagalan: " . $e->getMessage();
        $logs[] = "ERROR: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer Database - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-tr from-indigo-600 to-violet-500 text-white rounded-2xl shadow-lg mb-4 text-2xl">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900"><?= APP_NAME ?></h1>
            <p class="text-slate-500 mt-1">Smart Computer-Based Test System &bull; Automatic Database Setup</p>
        </div>

        <?php if ($status === 'success'): ?>
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-emerald-200 mb-8">
                <div class="flex items-center space-x-4 mb-6">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center text-2xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-emerald-800">Instalasi Berhasil!</h2>
                        <p class="text-sm text-emerald-600"><?= $message ?></p>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-xl p-6 border border-slate-200 mb-6">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fa-solid fa-key text-indigo-600 mr-2"></i> Akun Bawaan (Default Login):
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="p-3 bg-white rounded-lg border border-slate-200 shadow-sm">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-700">Administrator</span>
                            <div class="mt-2 font-mono text-xs">
                                <div>User: <strong class="text-slate-900">admin</strong></div>
                                <div>Pass: <strong class="text-slate-900">admin123</strong></div>
                            </div>
                        </div>
                        <div class="p-3 bg-white rounded-lg border border-slate-200 shadow-sm">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-700">Guru</span>
                            <div class="mt-2 font-mono text-xs">
                                <div>User: <strong class="text-slate-900">guru</strong></div>
                                <div>Pass: <strong class="text-slate-900">guru123</strong></div>
                            </div>
                        </div>
                        <div class="p-3 bg-white rounded-lg border border-slate-200 shadow-sm">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-700">Operator</span>
                            <div class="mt-2 font-mono text-xs">
                                <div>User: <strong class="text-slate-900">operator</strong></div>
                                <div>Pass: <strong class="text-slate-900">operator123</strong></div>
                            </div>
                        </div>
                        <div class="p-3 bg-white rounded-lg border border-slate-200 shadow-sm">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-700">Siswa (Peserta Tes)</span>
                            <div class="mt-2 font-mono text-xs">
                                <div>NISN: <strong class="text-slate-900">0061234501</strong></div>
                                <div>Pass: <strong class="text-slate-900">siswa123</strong></div>
                                <div>Token: <strong class="text-rose-600">SMART1</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="<?= BASE_URL ?>/login.php" class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition shadow-lg shadow-indigo-200 text-sm">
                        <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i> Buka Portal Login Petugas (Admin/Guru/Operator)
                    </a>
                    <a href="<?= BASE_URL ?>/ujian/login.php" class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition shadow-lg shadow-emerald-200 text-sm">
                        <i class="fa-solid fa-laptop mr-2"></i> Buka Portal Ujian Siswa
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($status === 'error'): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 mb-8 text-rose-800">
                <div class="flex items-center space-x-3 mb-2">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    <h3 class="font-bold">Gagal Menyiapkan Database</h3>
                </div>
                <p class="text-sm"><?= $message ?></p>
            </div>
        <?php endif; ?>

        <!-- Form Instalasi -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-slate-200">
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center">
                <i class="fa-solid fa-sliders text-indigo-600 mr-2.5"></i> Konfigurasi Database XAMPP
            </h2>
            <p class="text-slate-500 text-sm mb-6">
                Pastikan modul <strong>Apache</strong> dan <strong>MySQL</strong> di XAMPP Control Panel dalam keadaan <strong>Running (hijau)</strong>, lalu klik tombol di bawah untuk membuat database beserta tabel otomatis.
            </p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="install">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Host Database</label>
                        <input type="text" name="db_host" value="<?= DB_HOST ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Port</label>
                        <input type="text" name="db_port" value="<?= DB_PORT ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Username MySQL</label>
                        <input type="text" name="db_user" value="<?= DB_USER ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Password MySQL</label>
                        <input type="password" name="db_pass" value="<?= DB_PASS ?>" placeholder="Kosongkan jika default XAMPP" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Nama Database Target</label>
                        <input type="text" name="db_name" value="<?= DB_NAME ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                </div>

                <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100 text-xs text-indigo-700 mb-6">
                    <p class="font-semibold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Apa yang akan dilakukan oleh installer ini?</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Membuat database <code>cbt_db</code> secara otomatis.</li>
                        <li>Membuat 12 tabel relasional (users, students, question_banks, questions 6 jenis, exams, dll).</li>
                        <li>Mengisi bank soal contoh dengan 6 jenis tipe soal: <em>Pilgan, Kompleks, Menjodohkan, Benar/Salah, Isian Singkat, Uraian</em>.</li>
                        <li>Menyiapkan 1 jadwal ujian aktif dan token yang langsung siap dites.</li>
                    </ul>
                </div>

                <button type="submit" class="w-full py-3.5 px-6 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold rounded-xl transition duration-200 shadow-lg shadow-indigo-300 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-rocket mr-2"></i> Mulai Inisialisasi Database Sekarang
                </button>
            </form>

            <?php if (!empty($logs)): ?>
                <div class="mt-8 border-t border-slate-200 pt-6">
                    <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Log Proses Instalasi:</h3>
                    <div class="bg-slate-900 text-slate-200 font-mono text-xs rounded-xl p-4 max-h-48 overflow-y-auto space-y-1">
                        <?php foreach ($logs as $log): ?>
                            <div>&gt; <?= htmlspecialchars($log) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
