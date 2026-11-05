<?php
// guru/nilai.php - Rekap Nilai Ujian Siswa & Export
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

Auth::requireRole(['guru', 'admin', 'operator']);
$pdo = Database::getConnection();
$teacherId = Auth::user()['id'];
$isOperator = Auth::role() === 'operator';

// Ambil daftar ujian
$exams = $pdo->query("SELECT e.id, e.title, qb.title as bank_title, qb.kkm, s.name as subject_name 
                      FROM exams e 
                      JOIN question_banks qb ON e.bank_id = qb.id 
                      JOIN subjects s ON qb.subject_id = s.id 
                      ORDER BY e.id DESC")->fetchAll();

$selectedExamId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));

// Export CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStmt = $pdo->prepare("SELECT ep.*, s.nisn, s.exam_number, s.full_name, c.name as class_name 
                              FROM exam_participations ep 
                              JOIN students s ON ep.student_id = s.id 
                              JOIN classes c ON s.class_id = c.id 
                              WHERE ep.exam_id = ? AND ep.status = 'submitted' 
                              ORDER BY ep.score_total DESC");
    $expStmt->execute([$selectedExamId]);
    $rows = $expStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekap_nilai_ujian_' . $selectedExamId . '.csv"');
    $output = fopen('php://output', 'w');
    // BOM for Excel compatibility with UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['No', 'Nomor Ujian', 'NISN', 'Nama Siswa', 'Kelas', 'Skor Pilgan', 'Skor Kompleks', 'Skor Menjodohkan', 'Skor Benar-Salah', 'Skor Isian', 'Skor Uraian', 'Total Nilai', 'Status']);

    $no = 1;
    foreach ($rows as $r) {
        $status = $r['score_total'] >= 75 ? 'TUNTAS' : 'REMEDIAL';
        fputcsv($output, [
            $no++,
            $r['exam_number'],
            $r['nisn'],
            $r['full_name'],
            $r['class_name'],
            $r['score_pilgan'],
            $r['score_kompleks'],
            $r['score_jodohkan'],
            $r['score_benarsalah'],
            $r['score_isian'],
            $r['score_uraian'],
            $r['score_total'],
            $status
        ]);
    }
    fclose($output);
    exit;
}

// Ambil daftar nilai peserta
$partStmt = $pdo->prepare("SELECT ep.*, s.nisn, s.exam_number, s.full_name, c.name as class_name 
                           FROM exam_participations ep 
                           JOIN students s ON ep.student_id = s.id 
                           JOIN classes c ON s.class_id = c.id 
                           WHERE ep.exam_id = ? 
                           ORDER BY ep.score_total DESC, s.full_name ASC");
$partStmt->execute([$selectedExamId]);
$participations = $partStmt->fetchAll();

// Ambil info ujian terpilih
$currExam = null;
foreach ($exams as $ex) {
    if ($ex['id'] == $selectedExamId) {
        $currExam = $ex;
        break;
    }
}
$kkm = (float)($currExam['kkm'] ?? 75.00);

$pageTitle = 'Rekapitulasi Nilai Siswa';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Rekapitulasi Nilai Ujian</h1>
            <p class="text-sm text-slate-500">Hasil pengerjaan asesmen siswa dengan rincian nilai per jenis soal.</p>
        </div>

        <div class="flex items-center space-x-2">
            <form method="GET" action="" class="flex items-center space-x-2">
                <select name="exam_id" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $selectedExamId == $ex['id'] ? 'selected' : '' ?>>
                            <?= e($ex['title']) ?> (KKM: <?= $ex['kkm'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="?exam_id=<?= $selectedExamId ?>&export=csv" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-sm flex items-center">
                <i class="fa-solid fa-file-excel mr-1.5"></i> Export Excel (CSV)
            </a>
            <button onclick="window.print()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <!-- Table Nilai Siswa -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3.5 px-4 text-center">Rank</th>
                        <th class="py-3.5 px-4">Nama Siswa & NISN</th>
                        <th class="py-3.5 px-4">Kelas</th>
                        <th class="py-3.5 px-2 text-center" title="Pilihan Ganda">Pilgan</th>
                        <th class="py-3.5 px-2 text-center" title="PG Kompleks">Kompleks</th>
                        <th class="py-3.5 px-2 text-center" title="Menjodohkan">Jodohkan</th>
                        <th class="py-3.5 px-2 text-center" title="Benar/Salah">B / S</th>
                        <th class="py-3.5 px-2 text-center" title="Isian Singkat">Isian</th>
                        <th class="py-3.5 px-2 text-center" title="Uraian / Essay">Uraian</th>
                        <th class="py-3.5 px-4 text-center">Total Nilai</th>
                        <th class="py-3.5 px-4 text-center">Status KKM</th>
                        <th class="py-3.5 px-4 text-center">Koreksi / Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($participations)): ?>
                        <tr><td colspan="12" class="py-8 text-center text-slate-400">Belum ada peserta yang mengikuti ujian ini.</td></tr>
                    <?php else: ?>
                        <?php 
                        $rank = 1;
                        foreach ($participations as $p): 
                            $isPassed = $p['score_total'] >= $kkm;
                            $hasSubmitted = $p['status'] === 'submitted';
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 text-center font-bold text-slate-700">
                                    <?php if ($rank === 1 && $hasSubmitted): ?>
                                        <i class="fa-solid fa-medal text-amber-500 text-sm"></i>
                                    <?php elseif ($rank === 2 && $hasSubmitted): ?>
                                        <i class="fa-solid fa-medal text-slate-400 text-sm"></i>
                                    <?php elseif ($rank === 3 && $hasSubmitted): ?>
                                        <i class="fa-solid fa-medal text-amber-700 text-sm"></i>
                                    <?php else: ?>
                                        <?= $hasSubmitted ? $rank : '-' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= e($p['full_name']) ?></div>
                                    <div class="text-[11px] font-mono text-slate-400">NISN: <?= e($p['nisn']) ?> &bull; <?= e($p['exam_number']) ?></div>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-700"><?= e($p['class_name']) ?></td>
                                <td class="py-3 px-2 text-center font-mono"><?= $p['score_pilgan'] ?></td>
                                <td class="py-3 px-2 text-center font-mono"><?= $p['score_kompleks'] ?></td>
                                <td class="py-3 px-2 text-center font-mono"><?= $p['score_jodohkan'] ?></td>
                                <td class="py-3 px-2 text-center font-mono"><?= $p['score_benarsalah'] ?></td>
                                <td class="py-3 px-2 text-center font-mono"><?= $p['score_isian'] ?></td>
                                <td class="py-3 px-2 text-center font-mono font-bold text-indigo-700"><?= $p['score_uraian'] ?></td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-base font-black <?= $isPassed ? 'text-emerald-600' : 'text-rose-600' ?>">
                                        <?= $p['score_total'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if (!$hasSubmitted): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            <?= $p['status'] === 'in_progress' ? 'Sedang Tes' : 'Belum Mulai' ?>
                                        </span>
                                    <?php elseif ($isPassed): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-check mr-1"></i> TUNTAS
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                            REMEDIAL
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($hasSubmitted): ?>
                                        <a href="<?= BASE_URL ?>/guru/nilai_koreksi.php?participation_id=<?= $p['id'] ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[11px] transition">
                                            <i class="fa-solid fa-file-signature mr-1.5"></i> Koreksi / Rubrik
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            if ($hasSubmitted) $rank++;
                        endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
