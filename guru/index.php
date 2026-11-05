<?php
// guru/index.php - Guru Dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole(['guru', 'admin']);
$pdo = Database::getConnection();
$teacherId = Auth::user()['id'];
$isAdmin = Auth::role() === 'admin';

// Statistik Guru
$whereTeacher = $isAdmin ? "" : "WHERE teacher_id = $teacherId";
$totalMyBanks = $pdo->query("SELECT COUNT(*) FROM question_banks $whereTeacher")->fetchColumn() ?: 0;
$totalMyQuestions = $pdo->query("SELECT COUNT(*) FROM questions q JOIN question_banks qb ON q.bank_id = qb.id " . ($isAdmin ? "" : "WHERE qb.teacher_id = $teacherId"))->fetchColumn() ?: 0;

// Cari jumlah soal uraian yang butuh koreksi
$ungradedQuery = "SELECT COUNT(*) 
                  FROM student_answers sa 
                  JOIN questions q ON sa.question_id = q.id 
                  JOIN question_banks qb ON q.bank_id = qb.id 
                  WHERE q.type = 'uraian' AND sa.is_correct IS NULL " . ($isAdmin ? "" : "AND qb.teacher_id = $teacherId");
$totalUngradedEssay = $pdo->query($ungradedQuery)->fetchColumn() ?: 0;

// Daftar Bank Soal Guru
$banks = $pdo->query("SELECT qb.*, s.name as subject_name, 
                             (SELECT COUNT(*) FROM questions q WHERE q.bank_id = qb.id) as question_count 
                      FROM question_banks qb 
                      JOIN subjects s ON qb.subject_id = s.id 
                      $whereTeacher 
                      ORDER BY qb.id DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard Guru';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8">
    <!-- Welcome Header -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-indigo-800 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 text-blue-200 text-xs font-semibold uppercase tracking-wider mb-3">
                <i class="fa-solid fa-chalkboard-user mr-1.5 text-amber-300"></i> Portal Guru Pengajar
            </span>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Selamat Datang, <?= e($currentUser['full_name']) ?>!</h1>
            <p class="text-blue-100/80 text-sm mt-2 leading-relaxed">
                Kelola bank soal 6 jenis (Pilgan, Kompleks, Menjodohkan, Benar/Salah, Isian, Uraian), lakukan analisis butir soal, dan periksa hasil ujian siswa.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="inline-flex items-center px-4 py-2 bg-white text-indigo-900 font-bold rounded-xl text-xs hover:bg-blue-50 transition shadow">
                    <i class="fa-solid fa-layer-group mr-2"></i> Bank Soal Saya
                </a>
                <a href="<?= BASE_URL ?>/guru/analisis_soal.php" class="inline-flex items-center px-4 py-2 bg-blue-600/60 hover:bg-blue-600 border border-white/20 text-white font-semibold rounded-xl text-xs transition">
                    <i class="fa-solid fa-chart-line mr-2"></i> Analisis Butir Soal
                </a>
                <a href="<?= BASE_URL ?>/guru/nilai.php" class="inline-flex items-center px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl text-xs transition shadow">
                    <i class="fa-solid fa-square-poll-vertical mr-2"></i> Rekap Nilai Siswa
                </a>
            </div>
        </div>
        <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none transform translate-x-8 translate-y-8">
            <i class="fa-solid fa-file-pen text-[220px]"></i>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-800"><?= $totalMyBanks ?></div>
                <div class="text-xs text-slate-500 font-medium">Bank Soal Terdaftar</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                <i class="fa-solid fa-circle-question"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-800"><?= $totalMyQuestions ?></div>
                <div class="text-xs text-slate-500 font-medium">Total Butir Soal</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-14 h-14 rounded-2xl <?= $totalUngradedEssay > 0 ? 'bg-amber-100 text-amber-600 animate-pulse' : 'bg-slate-50 text-slate-400' ?> flex items-center justify-center text-2xl">
                <i class="fa-solid fa-pen-nib"></i>
            </div>
            <div>
                <div class="text-2xl font-black <?= $totalUngradedEssay > 0 ? 'text-amber-600' : 'text-slate-800' ?>"><?= $totalUngradedEssay ?></div>
                <div class="text-xs text-slate-500 font-medium">Uraian Butuh Koreksi</div>
            </div>
        </div>
    </div>

    <!-- Bank Soal List -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-folder-open text-indigo-600 mr-2"></i> Bank Soal Terbaru
            </h2>
            <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Semua Bank Soal &rarr;</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-y border-slate-100">
                        <th class="py-3 px-4">Kode & Judul Bank Soal</th>
                        <th class="py-3 px-4">Mata Pelajaran</th>
                        <th class="py-3 px-4">Jumlah Soal</th>
                        <th class="py-3 px-4">KKM</th>
                        <th class="py-3 px-4 text-center">Aksi Cepat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($banks)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">Belum ada bank soal dibuat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($banks as $b): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= e($b['title']) ?></div>
                                    <span class="text-[11px] font-mono text-slate-400"><?= e($b['code']) ?></span>
                                </td>
                                <td class="py-3 px-4 font-semibold text-indigo-700"><?= e($b['subject_name']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-slate-800"><?= $b['question_count'] ?></span> Butir
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-700"><?= $b['kkm'] ?></td>
                                <td class="py-3 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/guru/soal_editor.php?bank_id=<?= $b['id'] ?>" class="inline-flex items-center px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-lg text-xs transition">
                                        <i class="fa-solid fa-pen-to-square mr-1.5"></i> Kelola Soal
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
