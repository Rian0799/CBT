<?php
// admin/index.php - Admin Dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Ambil Statistik Keseluruhan
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn() ?: 0;
$totalOperators = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'operator'")->fetchColumn() ?: 0;
$totalBanks = $pdo->query("SELECT COUNT(*) FROM question_banks")->fetchColumn() ?: 0;
$activeExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'active'")->fetchColumn() ?: 0;
$activeSessions = $pdo->query("SELECT COUNT(*) FROM exam_participations WHERE status = 'in_progress'")->fetchColumn() ?: 0;

// Daftar Ujian Aktif Terkini
$recentExams = $pdo->query("SELECT e.*, qb.title as bank_title, s.name as subject_name 
                            FROM exams e 
                            JOIN question_banks qb ON e.bank_id = qb.id 
                            JOIN subjects s ON qb.subject_id = s.id 
                            ORDER BY e.id DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard Administrator';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-indigo-700 via-indigo-800 to-purple-800 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 text-indigo-200 text-xs font-semibold uppercase tracking-wider mb-3">
                <i class="fa-solid fa-crown mr-1.5 text-amber-300"></i> Hak Akses Penuh Administrator
            </span>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Selamat Datang, <?= e($currentUser['full_name']) ?>!</h1>
            <p class="text-indigo-100/80 text-sm mt-2 leading-relaxed">
                Anda memiliki akses kontrol penuh ke seluruh konfigurasi sistem CBT, manajemen data master siswa, guru, operator, bank soal, dan jadwal ujian.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="<?= BASE_URL ?>/admin/exams.php" class="inline-flex items-center px-4 py-2 bg-white text-indigo-900 font-bold rounded-xl text-xs hover:bg-indigo-50 transition shadow">
                    <i class="fa-solid fa-calendar-plus mr-2"></i> Jadwal Ujian Baru
                </a>
                <a href="<?= BASE_URL ?>/admin/students.php" class="inline-flex items-center px-4 py-2 bg-indigo-600/60 hover:bg-indigo-600 border border-white/20 text-white font-semibold rounded-xl text-xs transition">
                    <i class="fa-solid fa-user-plus mr-2"></i> Tambah Data Siswa
                </a>
                <a href="<?= BASE_URL ?>/operator/monitoring.php" class="inline-flex items-center px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs transition shadow">
                    <i class="fa-solid fa-tv mr-2"></i> Live Monitor Ujian
                </a>
            </div>
        </div>
        <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none transform translate-x-8 translate-y-8">
            <i class="fa-solid fa-shield-halved text-[220px]"></i>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg mb-3">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div class="text-2xl font-black text-slate-800"><?= $totalStudents ?></div>
            <div class="text-xs text-slate-500 font-medium">Total Siswa</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-lg mb-3">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <div class="text-2xl font-black text-slate-800"><?= $totalTeachers ?></div>
            <div class="text-xs text-slate-500 font-medium">Guru Pengajar</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg mb-3">
                <i class="fa-solid fa-headset"></i>
            </div>
            <div class="text-2xl font-black text-slate-800"><?= $totalOperators ?></div>
            <div class="text-xs text-slate-500 font-medium">Operator / Proktor</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg mb-3">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div class="text-2xl font-black text-slate-800"><?= $totalBanks ?></div>
            <div class="text-xs text-slate-500 font-medium">Bank Soal</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg mb-3">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="text-2xl font-black text-slate-800"><?= $activeExams ?></div>
            <div class="text-xs text-slate-500 font-medium">Ujian Aktif</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-emerald-500/20">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg mb-3">
                <i class="fa-solid fa-circle-play animate-pulse"></i>
            </div>
            <div class="text-2xl font-black text-emerald-600"><?= $activeSessions ?></div>
            <div class="text-xs text-slate-500 font-medium">Sedang Tes</div>
        </div>
    </div>

    <!-- Overview Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Exams Table -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-calendar-days text-indigo-600 mr-2"></i> Jadwal Ujian Terkini
                </h2>
                <a href="<?= BASE_URL ?>/admin/exams.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Lihat Semua &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-y border-slate-100">
                            <th class="py-3 px-4">Nama Ujian & Mapel</th>
                            <th class="py-3 px-4">Durasi</th>
                            <th class="py-3 px-4">Token</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($recentExams)): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400">Belum ada jadwal ujian yang dibuat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentExams as $ex): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800"><?= e($ex['title']) ?></div>
                                        <div class="text-slate-400"><?= e($ex['subject_name']) ?> &bull; <?= e($ex['session_name']) ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        <?= $ex['duration_minutes'] ?> Menit
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                            <?= e($ex['current_token']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($ex['status'] === 'active'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700">Aktif</span>
                                        <?php elseif ($ex['status'] === 'finished'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700">Selesai</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Management Shortcuts -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-base font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fa-solid fa-bolt text-amber-500 mr-2"></i> Pintasan Cepat
                </h2>
                <div class="space-y-2 text-sm">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium group">
                        <span class="flex items-center"><i class="fa-solid fa-user-shield w-6 text-slate-400 group-hover:text-indigo-600"></i> Kelola Petugas (Guru & Operator)</span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/students.php" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium group">
                        <span class="flex items-center"><i class="fa-solid fa-user-graduate w-6 text-slate-400 group-hover:text-indigo-600"></i> Kelola Siswa & Import Excel</span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/classes.php" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium group">
                        <span class="flex items-center"><i class="fa-solid fa-chalkboard w-6 text-slate-400 group-hover:text-indigo-600"></i> Kelola Kelas & Jurusan</span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/subjects.php" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium group">
                        <span class="flex items-center"><i class="fa-solid fa-book-bookmark w-6 text-slate-400 group-hover:text-indigo-600"></i> Kelola Mata Pelajaran</span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/settings.php" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium group">
                        <span class="flex items-center"><i class="fa-solid fa-sliders w-6 text-slate-400 group-hover:text-indigo-600"></i> Pengaturan & Logo CBT</span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                </div>
            </div>

            <!-- Server Info Card -->
            <div class="bg-slate-900 rounded-2xl p-6 text-white text-xs space-y-2">
                <div class="font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                    <span>Status Server CBT</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                </div>
                <div class="font-mono text-slate-300">
                    <div>PHP Engine: <?= phpversion() ?></div>
                    <div>Database: MySQL (cbt_db)</div>
                    <div>Waktu Server: <?= date('d M Y - H:i:s') ?> WIB</div>
                    <div>Base URL: <?= BASE_URL ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
