<?php
// index.php - Gateway: redirect semua ke halaman login terpadu
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Auth.php';

// Jika petugas sudah login
if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'admin') redirect(BASE_URL . '/admin/index.php');
    if ($role === 'guru') redirect(BASE_URL . '/guru/index.php');
    if ($role === 'operator') redirect(BASE_URL . '/operator/index.php');
}

// Jika siswa sudah login
if (Auth::checkStudent()) {
    redirect(BASE_URL . '/ujian/konfirmasi.php');
}

// Semua belum login: tampilkan landing page dengan link ke login terpadu
$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appTitle) ?> - <?= e($schoolName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white min-h-screen flex flex-col">

    <!-- Header -->
    <header class="max-w-6xl mx-auto w-full px-4 sm:px-6 flex items-center justify-between py-5 border-b border-white/10">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-400 text-2xl shadow-lg">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h1 class="font-extrabold text-xl text-white tracking-tight"><?= e($appTitle) ?></h1>
                <p class="text-xs text-indigo-300"><?= e($schoolName) ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <a href="<?= BASE_URL ?>/login.php" class="inline-flex items-center px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-sm transition">
                <i class="fa-solid fa-right-to-bracket mr-2"></i> Login
            </a>
            <a href="<?= BASE_URL ?>/database/install.php" class="inline-flex items-center px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-indigo-300 transition">
                <i class="fa-solid fa-wrench mr-1"></i> Wizard
            </a>
        </div>
    </header>

    <!-- Hero -->
    <main class="max-w-5xl mx-auto w-full px-4 sm:px-6 my-auto py-16">
        <div class="text-center mb-14">
            <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-400/20 text-indigo-300 text-xs font-semibold mb-5">
                <i class="fa-solid fa-graduation-cap mr-2 text-amber-400"></i>
                Platform CBT Multi-Jenjang: SD/MI &bull; SMP/MTs &bull; SMA/SMK/MA
            </div>
            <h2 class="text-4xl sm:text-5xl font-black text-white tracking-tight leading-tight max-w-3xl mx-auto">
                Sistem Ujian Berbasis Komputer <span class="text-indigo-400">&amp; Asesmen Pintar</span>
            </h2>
            <p class="text-indigo-200/70 text-sm sm:text-base mt-5 max-w-2xl mx-auto leading-relaxed">
                Mendukung <strong>6 Jenis Soal</strong>: Pilihan Ganda, PG Kompleks, Menjodohkan, Benar/Salah, Isian Singkat &amp; Uraian. Dilengkapi live monitoring, generator token, analisis butir soal, dan import soal dari Word.
            </p>

            <!-- Single Login CTA -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="<?= BASE_URL ?>/login.php" class="inline-flex items-center px-10 py-4 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-extrabold text-lg shadow-2xl shadow-indigo-500/30 transition hover:-translate-y-0.5">
                    <i class="fa-solid fa-right-to-bracket mr-3"></i> Masuk ke Sistem CBT
                </a>
            </div>
            <p class="text-xs text-indigo-400/70 mt-3">Satu halaman login untuk Siswa, Guru, Operator, dan Administrator</p>
        </div>

        <!-- Role Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <a href="<?= BASE_URL ?>/login.php#siswa" onclick="return setHashAndGo('siswa')" class="group bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 hover:border-emerald-500/50 rounded-2xl p-5 transition-all hover:-translate-y-1">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl mb-3"><i class="fa-solid fa-laptop-code"></i></div>
                <div class="text-xs font-bold text-emerald-300 uppercase tracking-wider mb-1">Siswa Peserta</div>
                <p class="text-slate-400 text-xs leading-relaxed">Login dengan NISN/No. Peserta untuk mengerjakan soal ujian.</p>
            </a>
            <a href="<?= BASE_URL ?>/login.php#guru" onclick="return setHashAndGo('guru')" class="group bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/20 hover:border-blue-500/50 rounded-2xl p-5 transition-all hover:-translate-y-1">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center text-blue-400 text-xl mb-3"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="text-xs font-bold text-blue-300 uppercase tracking-wider mb-1">Guru Pengajar</div>
                <p class="text-slate-400 text-xs leading-relaxed">Bank soal 6 jenis, import Word, analisis butir, rekap nilai.</p>
            </a>
            <a href="<?= BASE_URL ?>/login.php#operator" onclick="return setHashAndGo('operator')" class="group bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 hover:border-amber-500/50 rounded-2xl p-5 transition-all hover:-translate-y-1">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-400 text-xl mb-3"><i class="fa-solid fa-tv"></i></div>
                <div class="text-xs font-bold text-amber-300 uppercase tracking-wider mb-1">Operator / Proktor</div>
                <p class="text-slate-400 text-xs leading-relaxed">Monitor live, generate token, cek & cetak kartu peserta.</p>
            </a>
            <a href="<?= BASE_URL ?>/login.php#admin" onclick="return setHashAndGo('admin')" class="group bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 hover:border-purple-500/50 rounded-2xl p-5 transition-all hover:-translate-y-1">
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center text-purple-400 text-xl mb-3"><i class="fa-solid fa-crown"></i></div>
                <div class="text-xs font-bold text-purple-300 uppercase tracking-wider mb-1">Administrator</div>
                <p class="text-slate-400 text-xs leading-relaxed">Full management: pengguna, kelas, jadwal ujian, pengaturan sistem.</p>
            </a>
        </div>
    </main>

    <footer class="text-center py-5 text-xs text-slate-600 border-t border-white/5">
        &copy; <?= date('Y') ?> <?= e($schoolName) ?> &bull; <?= APP_NAME ?> v<?= APP_VERSION ?>
    </footer>

<script>
function setHashAndGo(role) {
    window.location.href = '<?= BASE_URL ?>/login.php#' + role;
    return false;
}
</script>
</body>
</html>
