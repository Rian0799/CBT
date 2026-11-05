<?php
// ujian/selesai.php - Halaman Ringkasan & Selesai Ujian Siswa
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

$pdo = Database::getConnection();
$participationId = (int)($_GET['participation_id'] ?? 0);

// Ambil info partisipasi
$stmt = $pdo->prepare("SELECT ep.*, s.full_name, s.nisn, s.exam_number, c.name as class_name, 
                              e.title as exam_title, e.show_results_immediately, qb.kkm, qb.title as bank_title, s2.name as subject_name,
                              (SELECT COUNT(*) FROM questions q WHERE q.bank_id = e.bank_id) as total_questions,
                              (SELECT COUNT(*) FROM student_answers sa WHERE sa.participation_id = ep.id AND sa.student_answer IS NOT NULL AND sa.student_answer != '') as answered_count 
                       FROM exam_participations ep 
                       JOIN students s ON ep.student_id = s.id 
                       JOIN classes c ON s.class_id = c.id 
                       JOIN exams e ON ep.exam_id = e.id 
                       JOIN question_banks qb ON e.bank_id = qb.id 
                       JOIN subjects s2 ON qb.subject_id = s2.id 
                       WHERE ep.id = ?");
$stmt->execute([$participationId]);
$part = $stmt->fetch();

if (!$part) {
    redirect(BASE_URL . '/ujian/login.php');
}

$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);
$isPassed = $part['score_total'] >= $part['kkm'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian Selesai - <?= e($appTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-10 px-4 flex items-center justify-center">
    <div class="max-w-2xl w-full">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden text-center">
            
            <!-- Header Celebration -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-8 text-white relative">
                <div class="w-20 h-20 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center mx-auto mb-4 text-4xl shadow-inner border border-white/30">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black">Ujian Berhasil Diselesaikan!</h1>
                <p class="text-emerald-100 text-xs sm:text-sm mt-1">Seluruh lembar jawaban Anda telah tersimpan dengan aman di server.</p>
            </div>

            <!-- Content Details -->
            <div class="p-6 sm:p-8 space-y-6 text-left text-xs">
                
                <!-- Identitas & Mapel -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <span class="text-slate-400 font-bold uppercase text-[10px] block">Identitas Siswa:</span>
                        <div class="font-bold text-slate-800 text-sm mt-0.5"><?= e($part['full_name']) ?></div>
                        <div class="text-slate-500 font-mono text-[11px]"><?= e($part['nisn']) ?> &bull; <?= e($part['class_name']) ?></div>
                    </div>
                    <div>
                        <span class="text-slate-400 font-bold uppercase text-[10px] block">Mata Pelajaran & Ujian:</span>
                        <div class="font-bold text-indigo-700 text-sm mt-0.5"><?= e($part['subject_name']) ?></div>
                        <div class="text-slate-500 text-[11px]"><?= e($part['exam_title']) ?></div>
                    </div>
                </div>

                <!-- Rekap Pengerjaan -->
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-100">
                        <div class="text-xs text-indigo-600 font-bold uppercase">Total Soal</div>
                        <div class="text-2xl font-black text-indigo-900 mt-1"><?= $part['total_questions'] ?></div>
                    </div>
                    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
                        <div class="text-xs text-emerald-600 font-bold uppercase">Terjawab</div>
                        <div class="text-2xl font-black text-emerald-900 mt-1"><?= $part['answered_count'] ?></div>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-xs text-slate-500 font-bold uppercase">Waktu Selesai</div>
                        <div class="text-sm font-black text-slate-800 mt-2 font-mono"><?= date('H:i:s', strtotime($part['end_time'] ?? 'now')) ?></div>
                    </div>
                </div>

                <!-- Tampilkan Hasil Nilai Langsung Jika Diizinkan -->
                <?php if ($part['show_results_immediately'] == 1): ?>
                    <div class="p-6 rounded-3xl bg-slate-900 text-white text-center space-y-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">Nilai Akhir Ujian Anda</span>
                        <div class="text-5xl font-black <?= $isPassed ? 'text-emerald-400' : 'text-rose-400' ?> drop-shadow">
                            <?= $part['score_total'] ?>
                        </div>
                        <div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?= $isPassed ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
                                <?= $isPassed ? 'Tuntas Melampaui KKM' : 'Perlu Remedial' ?> (KKM: <?= $part['kkm'] ?>)
                            </span>
                        </div>

                        <!-- Rincian Skor per Jenis Soal -->
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 pt-4 border-t border-white/10 text-[11px] font-mono">
                            <div class="p-2 rounded-xl bg-white/5">
                                <div class="text-slate-400">Pilgan</div>
                                <div class="font-bold text-white"><?= $part['score_pilgan'] ?></div>
                            </div>
                            <div class="p-2 rounded-xl bg-white/5">
                                <div class="text-slate-400">Kompleks</div>
                                <div class="font-bold text-white"><?= $part['score_kompleks'] ?></div>
                            </div>
                            <div class="p-2 rounded-xl bg-white/5">
                                <div class="text-slate-400">Jodohkan</div>
                                <div class="font-bold text-white"><?= $part['score_jodohkan'] ?></div>
                            </div>
                            <div class="p-2 rounded-xl bg-white/5">
                                <div class="text-slate-400">B / S</div>
                                <div class="font-bold text-white"><?= $part['score_benarsalah'] ?></div>
                            </div>
                            <div class="p-2 rounded-xl bg-white/5">
                                <div class="text-slate-400">Isian</div>
                                <div class="font-bold text-white"><?= $part['score_isian'] ?></div>
                            </div>
                            <div class="p-2 rounded-xl bg-white/5">
                                <div class="text-slate-400">Uraian</div>
                                <div class="font-bold text-white"><?= $part['score_uraian'] ?></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-800 text-center text-xs">
                        <i class="fa-solid fa-circle-info mr-1.5 text-blue-600"></i>
                        Hasil penilaian akan diumumkan dan direkapitulasi secara resmi oleh Guru Pengajar setelah seluruh butir soal selesai dikoreksi.
                    </div>
                <?php endif; ?>

                <!-- Tombol Keluar -->
                <div class="pt-4 text-center">
                    <a href="<?= BASE_URL ?>/logout.php" class="inline-flex items-center justify-center px-8 py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-2xl transition duration-200 shadow-xl text-xs">
                        <i class="fa-solid fa-power-off mr-2 text-rose-400"></i> Keluar dari Ujian (Logout)
                    </a>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="bg-slate-50 border-t border-slate-100 p-4 text-center text-[11px] text-slate-400">
                Terima kasih telah mengikuti ujian dengan tertib dan jujur &bull; <?= e($schoolName) ?>
            </div>
        </div>
    </div>
</body>
</html>
