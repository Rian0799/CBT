<?php
// guru/soal_editor.php - Visual Editor 6 Jenis Tipe Soal Asesmen
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

Auth::requireRole(['guru', 'admin']);
$pdo = Database::getConnection();
$bankId = (int)($_GET['bank_id'] ?? 0);

// Ambil info bank soal
$bStmt = $pdo->prepare("SELECT qb.*, s.name as subject_name FROM question_banks qb JOIN subjects s ON qb.subject_id = s.id WHERE qb.id = ?");
$bStmt->execute([$bankId]);
$bank = $bStmt->fetch();

if (!$bank) {
    set_flash('error', 'Bank soal tidak ditemukan!');
    redirect(BASE_URL . '/guru/bank_soal.php');
}

// Handle Form Submission (Simpan Soal Baru / Update Soal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_question') {
        $type = $_POST['type'] ?? QuestionService::TYPE_PILGAN;
        $questionText = trim($_POST['question_text'] ?? '');
        $weight = (float)($_POST['score_weight'] ?? 1.0);
        $explanation = trim($_POST['explanation'] ?? '');
        $optionsData = null;
        $correctAnswer = null;

        // 1. Tipe Pilgan
        if ($type === QuestionService::TYPE_PILGAN) {
            $options = [
                'A' => trim($_POST['pilgan_opt_A'] ?? ''),
                'B' => trim($_POST['pilgan_opt_B'] ?? ''),
                'C' => trim($_POST['pilgan_opt_C'] ?? ''),
                'D' => trim($_POST['pilgan_opt_D'] ?? ''),
                'E' => trim($_POST['pilgan_opt_E'] ?? ''),
            ];
            // Filter opsi kosong jika hanya 4 pilihan
            $filteredOpts = array_filter($options, fn($val) => $val !== '');
            $optionsData = json_encode($filteredOpts, JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode(strtoupper(trim($_POST['pilgan_correct'] ?? 'A')));
        }
        // 2. Tipe Kompleks
        elseif ($type === QuestionService::TYPE_KOMPLEKS) {
            $kompleksTexts = $_POST['kompleks_texts'] ?? [];
            $kompleksCorrect = $_POST['kompleks_correct'] ?? []; // array of keys like ['A', 'C']
            $opts = [];
            foreach ($kompleksTexts as $k => $text) {
                if (trim($text) !== '') {
                    $opts[] = ['key' => $k, 'text' => trim($text)];
                }
            }
            $optionsData = json_encode($opts, JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode($kompleksCorrect, JSON_UNESCAPED_UNICODE);
        }
        // 3. Tipe Menjodohkan
        elseif ($type === QuestionService::TYPE_JODOHKAN) {
            $premiseTexts = $_POST['jodohkan_premis'] ?? [];
            $targetTexts = $_POST['jodohkan_target'] ?? [];
            $matchedPairs = $_POST['jodohkan_match'] ?? []; // ['p1' => 't2', 'p2' => 't1']

            $premises = [];
            foreach ($premiseTexts as $pid => $txt) {
                if (trim($txt) !== '') $premises[] = ['id' => $pid, 'text' => trim($txt)];
            }

            $targets = [];
            foreach ($targetTexts as $tid => $txt) {
                if (trim($txt) !== '') $targets[] = ['id' => $tid, 'text' => trim($txt)];
            }

            $optionsData = json_encode(['premises' => $premises, 'targets' => $targets], JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode($matchedPairs, JSON_UNESCAPED_UNICODE);
        }
        // 4. Tipe Benar / Salah
        elseif ($type === QuestionService::TYPE_BENARSALAH) {
            $statements = $_POST['bs_statements'] ?? [];
            $answers = $_POST['bs_correct'] ?? []; // ['s1' => 'B', 's2' => 'S']

            $stList = [];
            foreach ($statements as $sid => $txt) {
                if (trim($txt) !== '') $stList[] = ['id' => $sid, 'text' => trim($txt)];
            }

            $optionsData = json_encode($stList, JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode($answers, JSON_UNESCAPED_UNICODE);
        }
        // 5. Tipe Isian Singkat
        elseif ($type === QuestionService::TYPE_ISIAN) {
            $keysRaw = trim($_POST['isian_keys'] ?? '');
            $isCase = isset($_POST['isian_case_sensitive']) ? true : false;
            $keysList = array_map('trim', explode(',', $keysRaw));

            $optionsData = json_encode(['case_sensitive' => $isCase, 'placeholder' => 'Ketik jawaban Anda disini...']);
            $correctAnswer = json_encode($keysList, JSON_UNESCAPED_UNICODE);
        }
        // 6. Tipe Uraian / Essay
        elseif ($type === QuestionService::TYPE_URAIAN) {
            $rubric = trim($_POST['uraian_rubric'] ?? '');
            $optionsData = json_encode(['rubric' => $rubric, 'max_score' => $weight]);
            $correctAnswer = json_encode(trim($_POST['uraian_sample_answer'] ?? ''));
        }

        // Tentukan nomor soal berikutnya
        $numStmt = $pdo->prepare("SELECT COALESCE(MAX(question_number), 0) + 1 FROM questions WHERE bank_id = ?");
        $numStmt->execute([$bankId]);
        $nextNum = $numStmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO questions (bank_id, question_number, type, question_text, options_data, correct_answer, score_weight, explanation) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$bankId, $nextNum, $type, $questionText, $optionsData, $correctAnswer, $weight, $explanation]);

        // Update total questions count in bank
        $upd = $pdo->prepare("UPDATE question_banks SET total_questions = (SELECT COUNT(*) FROM questions WHERE bank_id = ?) WHERE id = ?");
        $upd->execute([$bankId, $bankId]);

        set_flash('success', "Soal nomor <strong>#$nextNum</strong> berhasil ditambahkan!");
        redirect(BASE_URL . "/guru/soal_editor.php?bank_id=$bankId");
    }

    if ($action === 'delete_question') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ? AND bank_id = ?");
        $stmt->execute([$qid, $bankId]);

        // Re-sequence question numbers
        $reNum = $pdo->prepare("SELECT id FROM questions WHERE bank_id = ? ORDER BY question_number ASC");
        $reNum->execute([$bankId]);
        $allQ = $reNum->fetchAll();
        $idx = 1;
        foreach ($allQ as $item) {
            $updQ = $pdo->prepare("UPDATE questions SET question_number = ? WHERE id = ?");
            $updQ->execute([$idx++, $item['id']]);
        }

        // Update total questions in bank
        $upd = $pdo->prepare("UPDATE question_banks SET total_questions = ? WHERE id = ?");
        $upd->execute([count($allQ), $bankId]);

        set_flash('success', "Butir soal berhasil dihapus.");
        redirect(BASE_URL . "/guru/soal_editor.php?bank_id=$bankId");
    }
}

// Ambil semua butir soal di bank ini
$qStmt = $pdo->prepare("SELECT * FROM questions WHERE bank_id = ? ORDER BY question_number ASC");
$qStmt->execute([$bankId]);
$questions = $qStmt->fetchAll();

$typeBadges = [
    QuestionService::TYPE_PILGAN => ['name' => 'Pilihan Ganda', 'color' => 'bg-blue-100 text-blue-700 border-blue-200'],
    QuestionService::TYPE_KOMPLEKS => ['name' => 'PG Kompleks', 'color' => 'bg-purple-100 text-purple-700 border-purple-200'],
    QuestionService::TYPE_JODOHKAN => ['name' => 'Menjodohkan', 'color' => 'bg-teal-100 text-teal-700 border-teal-200'],
    QuestionService::TYPE_BENARSALAH => ['name' => 'Benar / Salah', 'color' => 'bg-amber-100 text-amber-700 border-amber-200'],
    QuestionService::TYPE_ISIAN => ['name' => 'Isian Singkat', 'color' => 'bg-indigo-100 text-indigo-700 border-indigo-200'],
    QuestionService::TYPE_URAIAN => ['name' => 'Uraian / Essay', 'color' => 'bg-rose-100 text-rose-700 border-rose-200'],
];

$pageTitle = 'Studio Pembuat Soal: ' . e($bank['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header Back & Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-800 mb-1">
                <i class="fa-solid fa-arrow-left mr-1.5"></i> Kembali ke Bank Soal
            </a>
            <h1 class="text-2xl font-black text-slate-800"><?= e($bank['title']) ?></h1>
            <p class="text-xs text-slate-500">Mata Pelajaran: <strong><?= e($bank['subject_name']) ?></strong> &bull; Total: <strong><?= count($questions) ?> Butir Soal</strong> &bull; KKM: <?= $bank['kkm'] ?></p>
        </div>

        <div class="flex items-center space-x-2">
            <a href="<?= BASE_URL ?>/guru/analisis_soal.php?bank_id=<?= $bankId ?>" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition flex items-center">
                <i class="fa-solid fa-chart-line mr-1.5 text-blue-600"></i> Analisis Soal
            </a>
            <a href="<?= BASE_URL ?>/guru/soal_import.php?bank_id=<?= $bankId ?>" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition flex items-center">
                <i class="fa-solid fa-file-import mr-1.5 text-indigo-600"></i> Import Soal
            </a>
            <button onclick="openQuestionModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition flex items-center">
                <i class="fa-solid fa-circle-plus mr-1.5"></i> Tambah Soal Baru
            </button>
        </div>
    </div>

    <!-- Questions List Accordion / Feed -->
    <div class="space-y-4">
        <?php if (empty($questions)): ?>
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200">
                <div class="w-16 h-16 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fa-solid fa-pen-nib"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-700">Bank Soal Masih Kosong</h3>
                <p class="text-sm text-slate-400 mt-1 mb-6">Mulai tambahkan butir soal dengan memilih salah satu dari 6 jenis soal yang didukung.</p>
                <button onclick="openQuestionModal()" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200">
                    <i class="fa-solid fa-circle-plus mr-1.5"></i> Buat Butir Soal #1
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($questions as $q): 
                $qType = $q['type'];
                $badge = $typeBadges[$qType] ?? ['name' => ucfirst($qType), 'color' => 'bg-slate-100 text-slate-700'];
                $optsData = json_decode($q['options_data'] ?? '[]', true) ?: [];
                $correctAns = json_decode($q['correct_answer'] ?? '""', true);
            ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div class="flex items-center space-x-2">
                            <span class="w-7 h-7 rounded-lg bg-slate-900 text-white font-extrabold text-xs flex items-center justify-center">
                                <?= $q['question_number'] ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $badge['color'] ?>">
                                <?= $badge['name'] ?>
                            </span>
                            <span class="text-xs font-semibold text-slate-500">
                                Bobot: <strong class="text-slate-800"><?= $q['score_weight'] ?></strong>
                            </span>
                        </div>

                        <form method="POST" action="" onsubmit="return confirm('Hapus butir soal #<?= $q['question_number'] ?>?');" class="inline">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus Butir Soal">
                                <i class="fa-solid fa-trash-can text-sm"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Question Content Preview -->
                    <div class="prose max-w-none text-sm text-slate-800 mb-4 font-normal leading-relaxed">
                        <?= $q['question_text'] ?>
                    </div>

                    <!-- Type-Specific Answer & Key Presentation -->
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 text-xs">
                        <?php if ($qType === QuestionService::TYPE_PILGAN): ?>
                            <div class="space-y-1.5">
                                <?php foreach ($optsData as $optKey => $optVal): ?>
                                    <div class="flex items-center space-x-2 <?= $optKey === $correctAns ? 'font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded' : 'text-slate-600' ?>">
                                        <span class="w-5 h-5 rounded-full <?= $optKey === $correctAns ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600' ?> flex items-center justify-center text-[10px] font-bold">
                                            <?= $optKey ?>
                                        </span>
                                        <span><?= e($optVal) ?></span>
                                        <?php if ($optKey === $correctAns): ?>
                                            <i class="fa-solid fa-circle-check text-emerald-600 ml-auto"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($qType === QuestionService::TYPE_KOMPLEKS): ?>
                            <div class="space-y-1.5">
                                <span class="font-bold text-slate-500 block mb-1">Pilihan & Kunci Jawaban:</span>
                                <?php foreach ($optsData as $item): 
                                    $isKey = is_array($correctAns) && in_array($item['key'], $correctAns);
                                ?>
                                    <div class="flex items-center space-x-2 <?= $isKey ? 'font-bold text-purple-700 bg-purple-50 px-2 py-1 rounded' : 'text-slate-600' ?>">
                                        <span class="w-5 h-5 rounded <?= $isKey ? 'bg-purple-600 text-white' : 'bg-slate-200 text-slate-600' ?> flex items-center justify-center text-[10px] font-bold">
                                            <?= $item['key'] ?>
                                        </span>
                                        <span><?= e($item['text']) ?></span>
                                        <?php if ($isKey): ?>
                                            <i class="fa-solid fa-check text-purple-600 ml-auto"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($qType === QuestionService::TYPE_JODOHKAN): ?>
                            <div>
                                <span class="font-bold text-slate-500 block mb-2">Pasangan Menjodohkan:</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <?php 
                                    $premMap = []; foreach ($optsData['premises'] ?? [] as $pr) $premMap[$pr['id']] = $pr['text'];
                                    $tarMap = []; foreach ($optsData['targets'] ?? [] as $tr) $tarMap[$tr['id']] = $tr['text'];
                                    foreach ($correctAns as $pKey => $tKey): ?>
                                        <div class="p-2 bg-white rounded-lg border border-slate-200 flex items-center justify-between text-[11px]">
                                            <span class="font-bold text-slate-800"><?= e($premMap[$pKey] ?? $pKey) ?></span>
                                            <i class="fa-solid fa-arrow-right text-teal-500 mx-2"></i>
                                            <span class="font-semibold text-teal-700"><?= e($tarMap[$tKey] ?? $tKey) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php elseif ($qType === QuestionService::TYPE_BENARSALAH): ?>
                            <div>
                                <span class="font-bold text-slate-500 block mb-2">Pernyataan & Kunci:</span>
                                <div class="space-y-1">
                                    <?php foreach ($optsData as $st): 
                                        $val = $correctAns[$st['id']] ?? 'B';
                                    ?>
                                        <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200">
                                            <span class="text-slate-700"><?= e($st['text']) ?></span>
                                            <span class="px-2 py-0.5 rounded font-bold text-[10px] <?= $val === 'B' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                                <?= $val === 'B' ? 'BENAR' : 'SALAH' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php elseif ($qType === QuestionService::TYPE_ISIAN): ?>
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-500">Kunci Jawaban Singkat:</span>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $keys = is_array($correctAns) ? $correctAns : [(string)$correctAns];
                                    foreach ($keys as $k): ?>
                                        <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-mono font-bold"><?= e($k) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php elseif ($qType === QuestionService::TYPE_URAIAN): ?>
                            <div>
                                <span class="font-bold text-slate-500 block mb-1">Panduan Rubrik Penilaian:</span>
                                <p class="text-slate-700 italic"><?= e($optsData['rubric'] ?? 'Dinilai manual oleh guru.') ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($q['explanation'])): ?>
                            <div class="mt-3 pt-3 border-t border-slate-200 text-slate-500">
                                <strong class="text-slate-700">Pembahasan:</strong> <?= e($q['explanation']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Butir Soal Baru (Full 6 Question Types Visual Builder) -->
<div id="modalAddQuestion" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full p-6 sm:p-8 border border-slate-200 max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-pen-nib text-indigo-600 mr-2.5"></i> Buat Butir Soal Baru
            </h3>
            <button onclick="closeQuestionModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-5 text-xs">
            <input type="hidden" name="action" value="save_question">

            <!-- Pilih Tipe Soal (6 Tabs / Selector) -->
            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-2">Pilih Tipe Soal *</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <?php foreach (QuestionService::getTypesList() as $typeKey => $typeName): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="<?= $typeKey ?>" <?= $typeKey === QuestionService::TYPE_PILGAN ? 'checked' : '' ?> onchange="switchQuestionType(this.value)" class="peer sr-only">
                            <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-900 text-slate-600 font-bold text-center transition">
                                <?= $typeName ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Teks Pertanyaan -->
            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Teks Pertanyaan / Stimulus Soal *</label>
                <textarea name="question_text" rows="3" required placeholder="Tuliskan pertanyaan atau stimulus soal disini (mendukung tag HTML sederhana)..." class="w-full px-3 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs"></textarea>
            </div>

            <!-- Panel Dinamis Sesuai Tipe Soal -->
            
            <!-- 1. Tipe PILGAN -->
            <div id="panel_pilgan" class="type-panel space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <span class="font-bold text-slate-800 uppercase tracking-wider block text-[11px]">Pilihan Jawaban & Tentukan Kunci:</span>
                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $opt): ?>
                    <div class="flex items-center space-x-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="pilgan_correct" value="<?= $opt ?>" <?= $opt === 'A' ? 'checked' : '' ?> class="text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-1.5 font-bold text-slate-700"><?= $opt ?></span>
                        </label>
                        <input type="text" name="pilgan_opt_<?= $opt ?>" placeholder="Teks Pilihan <?= $opt ?>" class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 text-xs">
                    </div>
                <?php endforeach; ?>
                <span class="text-[11px] text-slate-400 italic block">Pilih radio button di samping huruf untuk menetapkan kunci jawaban benar.</span>
            </div>

            <!-- 2. Tipe KOMPLEKS -->
            <div id="panel_kompleks" class="type-panel space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 hidden">
                <span class="font-bold text-slate-800 uppercase tracking-wider block text-[11px]">Pilihan Ganda Kompleks (Centang Semua yang Benar):</span>
                <?php foreach (['A', 'B', 'C', 'D'] as $k): ?>
                    <div class="flex items-center space-x-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="kompleks_correct[]" value="<?= $k ?>" class="rounded text-purple-600 focus:ring-purple-500">
                            <span class="ml-1.5 font-bold text-slate-700"><?= $k ?></span>
                        </label>
                        <input type="text" name="kompleks_texts[<?= $k ?>]" placeholder="Pernyataan pilihan <?= $k ?>" class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 text-xs">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 3. Tipe MENJODOHKAN -->
            <div id="panel_jodohkan" class="type-panel space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 hidden">
                <span class="font-bold text-slate-800 uppercase tracking-wider block text-[11px]">Pasangan Menjodohkan (Premis Kiri -> Target Kanan):</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <strong class="text-slate-600 block">Kolom Premis (Kiri):</strong>
                        <input type="text" name="jodohkan_premis[p1]" placeholder="Premis 1 (contoh: Ibukota RI)" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                        <input type="text" name="jodohkan_premis[p2]" placeholder="Premis 2 (contoh: Ibukota Jepang)" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                        <input type="text" name="jodohkan_premis[p3]" placeholder="Premis 3 (contoh: Ibukota Perancis)" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                    </div>
                    <div class="space-y-2">
                        <strong class="text-slate-600 block">Kolom Target (Kanan):</strong>
                        <input type="text" name="jodohkan_target[t1]" placeholder="Target A (contoh: Nusantara)" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                        <input type="text" name="jodohkan_target[t2]" placeholder="Target B (contoh: Tokyo)" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                        <input type="text" name="jodohkan_target[t3]" placeholder="Target C (contoh: Paris)" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                    </div>
                </div>
                <div class="pt-2">
                    <strong class="text-slate-600 block mb-1">Kunci Pasangan:</strong>
                    <div class="grid grid-cols-3 gap-2">
                        <select name="jodohkan_match[p1]" class="p-1 rounded border text-xs">
                            <option value="t1">Premis 1 &rarr; Target A</option>
                            <option value="t2">Premis 1 &rarr; Target B</option>
                            <option value="t3">Premis 1 &rarr; Target C</option>
                        </select>
                        <select name="jodohkan_match[p2]" class="p-1 rounded border text-xs">
                            <option value="t2" selected>Premis 2 &rarr; Target B</option>
                            <option value="t1">Premis 2 &rarr; Target A</option>
                            <option value="t3">Premis 2 &rarr; Target C</option>
                        </select>
                        <select name="jodohkan_match[p3]" class="p-1 rounded border text-xs">
                            <option value="t3" selected>Premis 3 &rarr; Target C</option>
                            <option value="t1">Premis 3 &rarr; Target A</option>
                            <option value="t2">Premis 3 &rarr; Target B</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 4. Tipe BENAR / SALAH -->
            <div id="panel_benarsalah" class="type-panel space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 hidden">
                <span class="font-bold text-slate-800 uppercase tracking-wider block text-[11px]">Tabel Pernyataan Benar / Salah:</span>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="flex items-center space-x-2">
                        <input type="text" name="bs_statements[s<?= $i ?>]" placeholder="Pernyataan <?= $i ?>" class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 text-xs">
                        <select name="bs_correct[s<?= $i ?>]" class="p-1.5 rounded-lg border font-bold text-xs">
                            <option value="B">Benar</option>
                            <option value="S">Salah</option>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- 5. Tipe ISIAN SINGKAT -->
            <div id="panel_isian" class="type-panel space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 hidden">
                <span class="font-bold text-slate-800 uppercase tracking-wider block text-[11px]">Kunci Jawaban Isian Singkat:</span>
                <div>
                    <input type="text" name="isian_keys" placeholder="Contoh: jakarta, dki jakarta, ibu kota nusantara (pisahkan dengan koma jika ada variasi)" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs">
                    <span class="text-[11px] text-slate-400 block mt-1">Gunakan tanda koma (,) untuk memisahkan alternatif penulisan jawaban yang dianggap benar.</span>
                </div>
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="isian_case_sensitive" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span class="text-slate-700">Huruf Besar & Kecil Harus Sama Persis (Case Sensitive)</span>
                </label>
            </div>

            <!-- 6. Tipe URAIAN -->
            <div id="panel_uraian" class="type-panel space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 hidden">
                <span class="font-bold text-slate-800 uppercase tracking-wider block text-[11px]">Rubrik & Contoh Jawaban Ideal:</span>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Rubrik / Pedoman Penskoran:</label>
                    <textarea name="uraian_rubric" rows="2" placeholder="Contoh: Poin 5 jika menjelaskan konsep dasar, Poin 5 jika memberi contoh..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs"></textarea>
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Contoh Jawaban Ideal:</label>
                    <textarea name="uraian_sample_answer" rows="2" placeholder="Jawaban yang diharapkan dari siswa..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs"></textarea>
                </div>
            </div>

            <!-- Bobot & Pembahasan -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Bobot Nilai *</label>
                    <input type="number" name="score_weight" value="10.00" step="0.5" min="0.5" max="100" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-700 uppercase mb-1">Pembahasan Soal (Opsional)</label>
                    <input type="text" name="explanation" placeholder="Catatan pembahasan atau penjelasan kunci" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100">
                <button type="button" onclick="closeQuestionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-lg shadow-indigo-200">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Simpan Butir Soal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openQuestionModal() {
    document.getElementById('modalAddQuestion').classList.remove('hidden');
}
function closeQuestionModal() {
    document.getElementById('modalAddQuestion').classList.add('hidden');
}

function switchQuestionType(type) {
    document.querySelectorAll('.type-panel').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById('panel_' + type);
    if (target) {
        target.classList.remove('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
