<?php
// includes/sidebar.php
require_once __DIR__ . '/../app/Auth.php';

$currentRole = Auth::role();
$currentScript = $_SERVER['PHP_SELF'] ?? '';

function isActiveNav(string $path, string $currentScript): string {
    return (strpos($currentScript, $path) !== false) 
        ? 'bg-indigo-50 text-indigo-700 font-bold border-r-4 border-indigo-600' 
        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium';
}
?>
<aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col justify-between transition-all duration-300 z-20">
    <div class="py-6 px-4 space-y-6 overflow-y-auto">

        <!-- ==================== ROLE: ADMIN (FULL MANAGEMENT) ==================== -->
        <?php if ($currentRole === 'admin'): ?>
            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Modul Utama</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/admin/index.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/index.php', $currentScript) ?>">
                        <i class="fa-solid fa-gauge-high w-6 text-indigo-600"></i>
                        <span>Dashboard Admin</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Manajemen Pengguna</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/users.php', $currentScript) ?>">
                        <i class="fa-solid fa-users-gear w-6 text-slate-500"></i>
                        <span>Data Pengguna (Petugas)</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/students.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/students.php', $currentScript) ?>">
                        <i class="fa-solid fa-user-graduate w-6 text-slate-500"></i>
                        <span>Data Siswa Peserta</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/student_import.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/student_import.php', $currentScript) ?>">
                        <i class="fa-solid fa-file-excel w-6 text-slate-500"></i>
                        <span>Import Siswa (Excel)</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Data Akademik Master</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/admin/classes.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/classes.php', $currentScript) ?>">
                        <i class="fa-solid fa-chalkboard w-6 text-slate-500"></i>
                        <span>Kelas & Rombel</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/subjects.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/subjects.php', $currentScript) ?>">
                        <i class="fa-solid fa-book-bookmark w-6 text-slate-500"></i>
                        <span>Mata Pelajaran</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Ujian & Soal</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/admin/exams.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/exams.php', $currentScript) ?>">
                        <i class="fa-solid fa-calendar-check w-6 text-slate-500"></i>
                        <span>Jadwal & Sesi Ujian</span>
                    </a>
                    <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/bank_soal.php', $currentScript) ?>">
                        <i class="fa-solid fa-layer-group w-6 text-slate-500"></i>
                        <span>Seluruh Bank Soal</span>
                    </a>
                    <a href="<?= BASE_URL ?>/guru/nilai.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/nilai.php', $currentScript) ?>">
                        <i class="fa-solid fa-award w-6 text-slate-500"></i>
                        <span>Rekapitulasi Nilai</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Sistem</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/admin/settings.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('admin/settings.php', $currentScript) ?>">
                        <i class="fa-solid fa-sliders w-6 text-slate-500"></i>
                        <span>Pengaturan Aplikasi</span>
                    </a>
                    <a href="<?= BASE_URL ?>/database/install.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm text-slate-600 hover:bg-slate-50">
                        <i class="fa-solid fa-database w-6 text-slate-500"></i>
                        <span>Database Wizard</span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <!-- ==================== ROLE: GURU (SOAL, ANALISIS & NILAI) ==================== -->
        <?php if ($currentRole === 'guru'): ?>
            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Menu Guru</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/guru/index.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/index.php', $currentScript) ?>">
                        <i class="fa-solid fa-gauge-high w-6 text-blue-600"></i>
                        <span>Dashboard Guru</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Bank Soal & Pembuatan</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/bank_soal.php', $currentScript) ?>">
                        <i class="fa-solid fa-boxes-stacked w-6 text-slate-500"></i>
                        <span>Bank Soal Saya</span>
                    </a>
                    <a href="<?= BASE_URL ?>/guru/soal_import.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/soal_import.php', $currentScript) ?>">
                        <i class="fa-solid fa-file-arrow-up w-6 text-slate-500"></i>
                        <span>Upload / Import Soal</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Hasil & Evaluasi</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/guru/analisis_soal.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/analisis_soal.php', $currentScript) ?>">
                        <i class="fa-solid fa-chart-line w-6 text-slate-500"></i>
                        <span>Analisis Butir Soal</span>
                    </a>
                    <a href="<?= BASE_URL ?>/guru/nilai.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('guru/nilai.php', $currentScript) ?>">
                        <i class="fa-solid fa-square-poll-vertical w-6 text-slate-500"></i>
                        <span>Rekap Nilai Siswa</span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <!-- ==================== ROLE: OPERATOR (CEK SISWA, MANTAU TES & TOKEN) ==================== -->
        <?php if ($currentRole === 'operator'): ?>
            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Menu Operator / Proktor</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/operator/index.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('operator/index.php', $currentScript) ?>">
                        <i class="fa-solid fa-desktop w-6 text-amber-600"></i>
                        <span>Dashboard Operator</span>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-3 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Operasional Ujian</div>
                <nav class="space-y-1">
                    <a href="<?= BASE_URL ?>/operator/token.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('operator/token.php', $currentScript) ?>">
                        <i class="fa-solid fa-key w-6 text-rose-500"></i>
                        <span>Generate & Rilis Token</span>
                    </a>
                    <a href="<?= BASE_URL ?>/operator/monitoring.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('operator/monitoring.php', $currentScript) ?>">
                        <i class="fa-solid fa-tv w-6 text-indigo-600"></i>
                        <span class="flex-1">Live Monitoring Ujian</span>
                        <span class="flex h-2 w-2 relative">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/operator/siswa_tes.php" class="flex items-center px-3 py-2.5 rounded-xl transition text-sm <?= isActiveNav('operator/siswa_tes.php', $currentScript) ?>">
                        <i class="fa-solid fa-id-card w-6 text-blue-500"></i>
                        <span>Cek Data Siswa Tes</span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

    </div>

    <!-- Quick Link to Student Test Portal -->
    <div class="p-4 border-t border-slate-200">
        <a href="<?= BASE_URL ?>/ujian/login.php" target="_blank" class="flex items-center justify-center space-x-2 w-full px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
            <i class="fa-solid fa-external-link text-slate-400"></i>
            <span>Buka Portal Ujian Siswa</span>
        </a>
    </div>
</aside>
