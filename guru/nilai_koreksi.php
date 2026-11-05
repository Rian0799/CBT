<?php
// guru/nilai_koreksi.php - Lembar Koreksi Uraian & Penilaian Manual Guru
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

Auth::requireRole(['guru', 'admin']);
$pdo = Database::getConnection();
$participationId = (int)($_GET['participation_id'] ?? 0);

// Ambil info partisipasi dan siswa
$pStmt = $pdo->prepare("SELECT ep.*, s.full_name, s.nisn, s.exam_number, c.name as class_name, 
                               e.title as exam_title, e.id as exam_id, qb.kkm, qb.title as bank_title 
                        FROM exam_participations ep 
                        JOIN students s ON ep.student_id = s.id 
                        JOIN classes c ON s.class_id = c.id 
                        JOIN exams e ON ep.exam_id = e.id 
                        JOIN question_banks qb ON e.bank_id = qb.id 
                        WHERE ep.id = ?");
$pStmt->execute([$participationId]);
$part = $pStmt->fetch();

if (!$part) {
    set_flash('error', 'Data lembar ujian siswa tidak ditemukan.');
    redirect(BASE_URL . '/guru/nilai.php');
}

// Handle Simpan Koreksi Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grading') {
    $scores = $_POST['manual_score'] ?? [];
    $feedbacks = $_POST['feedback'] ?? [];

    $updAns = $pdo->prepare("UPDATE student_answers 
                             SET awarded_score = :score, is_correct = :is_corr, teacher_feedback = :fb 
                             WHERE id = :ans_id");

    foreach ($scores as $ansId => $scoreVal) {
        $score = (float)$scoreVal;
        $fb = trim($feedbacks[$ansId] ?? '');
        $isCorr = $score > 0 ? 1 : 0;
        $updAns->execute([
            'score' => $score,
            'is_corr' => $isCorr,
            'fb' => $fb,
            'ans_id' => $ansId
        ]);
    }

    // Hitung ulang total nilai partisipasi
    QuestionService::recalculateParticipationScore($participationId);

    set_flash('success', 'Penilaian manual dan nilai akhir siswa berhasil diperbarui!');
    redirect(BASE_URL . "/guru/nilai_koreksi.php?participation_id=$participationId");
}

// Ambil semua butir soal dan jawaban siswa
$qStmt = $pdo->prepare("SELECT q.*, sa.id as answer_id, sa.student_answer, sa.is_marked_doubtful, sa.is_correct, sa.awarded_score, sa.teacher_feedback 
                        FROM questions q 
                        LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.participation_id = ? 
                        WHERE q.bank_id = (SELECT bank_id FROM exams WHERE id = ?) 
                        ORDER BY q.question_number ASC");
$qStmt->execute([$participationId, $part['exam_id']]);
$answers = $qStmt->fetchAll();

$pageTitle = 'Lembar Koreksi: ' . e($part['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="<?= BASE_URL ?>/guru/nilai.php?exam_id=<?= $part['exam_id'] ?>" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-800">
            <i class="fa-solid fa-arrow-left mr-1.5"></i> Kembali ke Rekap Nilai
        </a>
        <span class="text-xs font-bold text-slate-500">ID Sesi: #<?= $part['id'] ?></span>
    </div>

    <!-- Student & Exam Header Info -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600"><?= e($part['exam_title']) ?></span>
            <h1 class="text-xl font-black text-slate-900 mt-1"><?= e($part['full_name']) ?></h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Kelas: <strong><?= e($part['class_name']) ?></strong> &bull; NISN: <?= e($part['nisn']) ?> &bull; No: <?= e($part['exam_number']) ?>
            </p>
        </div>
        <div class="text-right sm:border-l sm:pl-6 border-slate-200">
            <span class="text-xs text-slate-400 font-medium">Total Skor Saat Ini:</span>
            <div class="text-3xl font-black text-indigo-700 leading-tight"><?= $part['score_total'] ?></div>
            <span class="text-[11px] font-bold <?= $part['score_total'] >= $part['kkm'] ? 'text-emerald-600' : 'text-rose-600' ?>">
                <?= $part['score_total'] >= $part['kkm'] ? 'TUNTAS KKM' : 'BELUM TUNTAS' ?> (KKM: <?= $part['kkm'] ?>)
            </span>
        </div>
    </div>

    <!-- Form Koreksi Guru -->
    <form method="POST" action="" class="space-y-6">
        <input type="hidden" name="action" value="save_grading">

        <?php foreach ($answers as $a): 
            $qType = $a['type'];
            $studentAns = json_decode($a['student_answer'] ?? '""', true) ?: $a['student_answer'];
            $optsData = json_decode($a['options_data'] ?? '[]', true) ?: [];
            $correctAns = json_decode($a['correct_answer'] ?? '""', true);
            $isEssay = $qType === QuestionService::TYPE_URAIAN;
        ?>
            <div class="bg-white rounded-2xl border <?= $isEssay ? 'border-amber-300 ring-2 ring-amber-100' : 'border-slate-200' ?> p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="w-6 h-6 rounded bg-slate-900 text-white font-bold flex items-center justify-center">
                            <?= $a['question_number'] ?>
                        </span>
                        <span class="px-2 py-0.5 rounded font-bold uppercase text-[10px] bg-slate-100 text-slate-700">
                            <?= $qType ?>
                        </span>
                        <span class="text-slate-400">Maksimal Bobot: <?= $a['score_weight'] ?></span>
                    </div>

                    <?php if (!$isEssay): ?>
                        <div>
                            <?php if ($a['is_correct'] == 1): ?>
                                <span class="px-2 py-0.5 rounded-full font-bold text-emerald-700 bg-emerald-100 text-[11px]">
                                    <i class="fa-solid fa-check mr-1"></i> Benar (+<?= $a['awarded_score'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full font-bold text-rose-700 bg-rose-100 text-[11px]">
                                    <i class="fa-solid fa-xmark mr-1"></i> Salah / Parsial (+<?= $a['awarded_score'] ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full font-bold text-amber-800 bg-amber-100 text-[11px]">
                            <i class="fa-solid fa-pen-fancy mr-1"></i> Uraian Butuh Penilaian Guru
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Soal Text -->
                <div class="text-sm text-slate-800 mb-4 prose max-w-none">
                    <?= $a['question_text'] ?>
                </div>

                <!-- Student Answer Display -->
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 mb-4 text-xs">
                    <span class="font-bold text-slate-500 uppercase block text-[10px] mb-1">Jawaban Siswa:</span>
                    <?php if ($isEssay): ?>
                        <div class="text-sm font-medium text-slate-900 bg-white p-3 rounded-lg border border-slate-200 whitespace-pre-wrap">
                            <?= !empty($studentAns) ? e($studentAns) : '<em class="text-slate-400">Tidak ada jawaban siswa (kosong).</em>' ?>
                        </div>
                    <?php else: ?>
                        <div class="font-mono text-slate-800 font-bold">
                            <?= is_array($studentAns) ? json_encode($studentAns, JSON_UNESCAPED_UNICODE) : e((string)$studentAns) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rubrik / Koreksi Box for Essay -->
                <?php if ($isEssay): ?>
                    <div class="bg-amber-50/50 rounded-xl p-4 border border-amber-200 text-xs space-y-3">
                        <div class="text-amber-900">
                            <strong>Rubrik Penilaian:</strong> <?= e($optsData['rubric'] ?? 'Beri skor proporsional sesuai kelengkapan jawaban.') ?>
                        </div>
                        <?php if (!empty($correctAns)): ?>
                            <div class="text-slate-600">
                                <strong>Kunci Jawaban Ideal Guru:</strong> <?= e($correctAns) ?>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-amber-200/60">
                            <div>
                                <label class="block font-bold text-slate-700 uppercase mb-1">Beri Skor Siswa (Maks: <?= $a['score_weight'] ?>) *</label>
                                <input type="number" step="0.5" min="0" max="<?= $a['score_weight'] ?>" name="manual_score[<?= $a['answer_id'] ?>]" value="<?= $a['awarded_score'] ?? 0 ?>" required class="w-full px-3 py-2 rounded-lg border border-slate-300 font-bold text-sm text-indigo-700 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 uppercase mb-1">Catatan / Umpan Balik Guru (Feedback)</label>
                                <input type="text" name="feedback[<?= $a['answer_id'] ?>]" value="<?= e($a['teacher_feedback'] ?? '') ?>" placeholder="Ulasan atau perbaikan untuk siswa..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="sticky bottom-4 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200 shadow-xl flex items-center justify-between">
            <div class="text-xs text-slate-500">
                Klik tombol di samping untuk menyimpan nilai koreksi dan memperbarui rekap nilai.
            </div>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-indigo-200 flex items-center">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Simpan Penilaian Koreksi
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
