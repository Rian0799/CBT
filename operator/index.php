<?php
// operator/index.php - Operator & Proktor Dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole(['operator', 'admin']);
$pdo = Database::getConnection();

// Ambil Ujian yang Sedang Aktif
$activeExam = $pdo->query("SELECT e.*, qb.title as bank_title, s.name as subject_name 
                           FROM exams e 
                           JOIN question_banks qb ON e.bank_id = qb.id 
                           JOIN subjects s ON qb.subject_id = s.id 
                           WHERE e.status = 'active' 
                           ORDER BY e.id DESC LIMIT 1")->fetch();

// Statistik Monitoring
$totalReady = 0;
$totalWorking = 0;
$totalSubmitted = 0;
$totalBlocked = 0;

if ($activeExam) {
    $statsStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM exam_participations WHERE exam_id = ? GROUP BY status");
    $statsStmt->execute([$activeExam['id']]);
    $stats = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $totalWorking = $stats['in_progress'] ?? 0;
    $totalSubmitted = $stats['submitted'] ?? 0;
    $totalBlocked = $stats['blocked'] ?? 0;
}

$pageTitle = 'Dashboard Operator & Proktor';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8">
    <!-- Operator Hero -->
    <div class="bg-gradient-to-r from-amber-600 via-orange-600 to-amber-700 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 text-amber-100 text-xs font-semibold uppercase tracking-wider mb-3">
                <i class="fa-solid fa-desktop mr-1.5 text-amber-200"></i> Ruang Operasional Proktor CBT
            </span>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Selamat Bertugas, <?= e($currentUser['full_name']) ?>!</h1>
            <p class="text-amber-100/90 text-sm mt-2 leading-relaxed">
                Operator bertanggung jawab memverifikasi <strong>data siswa peserta tes</strong>, mengawasi <strong>live monitoring pengerjaan ujian</strong> secara real-time, dan <strong>men-generate token ujian</strong>.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="<?= BASE_URL ?>/operator/token.php" class="inline-flex items-center px-4 py-2 bg-white text-amber-900 font-bold rounded-xl text-xs hover:bg-amber-50 transition shadow">
                    <i class="fa-solid fa-key mr-2 text-rose-500"></i> Generate & Rilis Token
                </a>
                <a href="<?= BASE_URL ?>/operator/monitoring.php" class="inline-flex items-center px-4 py-2 bg-amber-800/60 hover:bg-amber-800 border border-white/20 text-white font-semibold rounded-xl text-xs transition">
                    <i class="fa-solid fa-tv mr-2"></i> Live Monitoring Ujian
                </a>
                <a href="<?= BASE_URL ?>/operator/siswa_tes.php" class="inline-flex items-center px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs transition shadow">
                    <i class="fa-solid fa-id-card mr-2"></i> Cek Data Siswa Tes
                </a>
            </div>
        </div>
        <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none transform translate-x-8 translate-y-8">
            <i class="fa-solid fa-headset text-[220px]"></i>
        </div>
    </div>

    <!-- Active Token Banner Card -->
    <?php if ($activeExam): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center space-x-2 text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Ujian Aktif Saat Ini: <?= e($activeExam['session_name']) ?> &bull; <?= e($activeExam['room']) ?></span>
                </div>
                <h2 class="text-2xl font-black text-slate-900"><?= e($activeExam['title']) ?></h2>
                <p class="text-xs text-slate-500 mt-1">Mata Pelajaran: <strong><?= e($activeExam['subject_name']) ?></strong> &bull; Durasi: <strong><?= $activeExam['duration_minutes'] ?> Menit</strong></p>
            </div>

            <!-- Big Token Display Box -->
            <div class="flex items-center space-x-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div class="text-right">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Token Ujian Siswa</div>
                    <div id="currentTokenDisplay" class="font-mono text-3xl font-black tracking-widest text-indigo-700">
                        <?= e($activeExam['current_token']) ?>
                    </div>
                </div>
                <button onclick="copyToken('<?= e($activeExam['current_token']) ?>')" class="p-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-md shadow-indigo-200 transition" title="Salin Token">
                    <i class="fa-solid fa-copy text-lg"></i>
                </button>
                <a href="<?= BASE_URL ?>/operator/token.php?exam_id=<?= $activeExam['id'] ?>" class="p-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl shadow-md shadow-amber-200 transition" title="Generate Token Baru">
                    <i class="fa-solid fa-arrows-rotate text-lg"></i>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-amber-50 border border-amber-200 rounded-3xl p-6 text-center text-amber-800">
            <i class="fa-solid fa-circle-info text-2xl mb-2 text-amber-600"></i>
            <h3 class="font-bold text-base">Tidak Ada Jadwal Ujian yang Sedang Aktif</h3>
            <p class="text-xs text-amber-700 mt-1">Hubungi Administrator untuk mengaktifkan jadwal sesi ujian hari ini.</p>
        </div>
    <?php endif; ?>

    <!-- Live Proctor Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-blue-500/10">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg mb-2">
                <i class="fa-solid fa-circle-play animate-pulse"></i>
            </div>
            <div class="text-3xl font-black text-slate-800"><?= $totalWorking ?></div>
            <div class="text-xs text-slate-500 font-medium">Sedang Mengerjakan</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-emerald-500/10">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg mb-2">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="text-3xl font-black text-slate-800"><?= $totalSubmitted ?></div>
            <div class="text-xs text-slate-500 font-medium">Sudah Submit / Selesai</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-rose-500/10">
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg mb-2">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="text-3xl font-black text-rose-600"><?= $totalBlocked ?></div>
            <div class="text-xs text-slate-500 font-medium">Terkunci / Pelanggaran</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center">
            <a href="<?= BASE_URL ?>/operator/monitoring.php" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-center text-xs transition shadow-md shadow-indigo-200">
                <i class="fa-solid fa-tv mr-1.5"></i> Buka Live Monitoring &rarr;
            </a>
        </div>
    </div>
</div>

<script>
function copyToken(tok) {
    navigator.clipboard.writeText(tok);
    Swal.fire({
        icon: 'success',
        title: 'Token Disalin!',
        text: 'Token ' + tok + ' berhasil disalin ke clipboard.',
        timer: 1500,
        showConfirmButton: false
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
