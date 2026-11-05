<?php
// guru/soal_import.php - Upload & Import Soal dari Microsoft Word (.docx / .doc)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';
require_once __DIR__ . '/../app/WordQuestionService.php';

Auth::requireRole(['guru', 'admin']);
$pdo = Database::getConnection();
$teacherId = Auth::user()['id'];
$isAdmin = Auth::role() === 'admin';
$selectedBankId = (int)($_GET['bank_id'] ?? 0);

// ================= DOWNLOAD TEMPLATE MICROSOFT WORD =================
if (isset($_GET['download_template'])) {
    $format = strtolower($_GET['download_template']);
    WordQuestionService::downloadTemplate($format);
    exit;
}

// ================= HANDLE SIMPAN SETTING KOMPOSISI & BOBOT =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $bankId = (int)($_POST['bank_id'] ?? 0);
    $types = ['pilgan', 'kompleks', 'jodohkan', 'benarsalah', 'isian', 'uraian'];

    $settings = [
        'is_configured' => 1,
        'total_questions' => 0,
        'total_weight' => 0.0
    ];

    foreach ($types as $t) {
        $active = isset($_POST[$t . '_active']) ? 1 : 0;
        $count = (int)($_POST[$t . '_count'] ?? 0);
        $weight = (float)($_POST[$t . '_weight'] ?? 0.0);
        $subtotal = $count * $weight;

        $settings[$t] = [
            'active' => $active,
            'count' => $count,
            'weight' => $weight,
            'subtotal' => $subtotal
        ];

        if ($active) {
            $settings['total_questions'] += $count;
            $settings['total_weight'] += $subtotal;
        }
    }

    $stmt = $pdo->prepare("UPDATE question_banks SET question_settings = ? WHERE id = ?");
    $stmt->execute([json_encode($settings, JSON_UNESCAPED_UNICODE), $bankId]);

    set_flash('success', "Pengaturan komposisi & bobot nilai berhasil disimpan! Target: {$settings['total_questions']} butir soal (Total Bobot: {$settings['total_weight']}). Anda sekarang dapat mengunggah berkas Word.");
    redirect(BASE_URL . "/guru/soal_import.php?bank_id=$bankId");
}

// ================= HANDLE IMPORT BERKAS WORD (.DOCX / .DOC) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_word') {
    $bankId = (int)($_POST['bank_id'] ?? 0);

    if (!$bankId) {
        set_flash('error', 'Silakan pilih Bank Soal tujuan terlebih dahulu.');
        redirect(BASE_URL . '/guru/soal_import.php');
    }

    // Cek apakah bank soal sudah disetting bobotnya
    $chkStmt = $pdo->prepare("SELECT * FROM question_banks WHERE id = ?");
    $chkStmt->execute([$bankId]);
    $targetBank = $chkStmt->fetch();

    $settings = json_decode($targetBank['question_settings'] ?? 'null', true);
    if (empty($settings['is_configured'])) {
        set_flash('error', 'Akses ditolak! Anda belum dapat mengunggah berkas soal sebelum mengatur jenis soal apa saja yang akan ditampilkan dan bobot nilainya.');
        redirect(BASE_URL . "/guru/soal_import.php?bank_id=$bankId");
    }

    if (isset($_FILES['file_soal']) && $_FILES['file_soal']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['file_soal']['tmp_name'];
        $fileName = $_FILES['file_soal']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['docx', 'doc', 'txt'])) {
            set_flash('error', 'Format berkas tidak didukung! Harap unggah berkas Microsoft Word (.docx atau .doc).');
            redirect(BASE_URL . "/guru/soal_import.php?bank_id=$bankId");
        }

        // Ekstraksi teks dari dokumen Word
        $rawText = WordQuestionService::extractTextFromWord($fileTmp, $ext);

        if (empty(trim($rawText))) {
            set_flash('error', 'Tidak dapat membaca teks dari dokumen Word. Pastikan berkas tidak kosong dan tidak diproteksi password.');
            redirect(BASE_URL . "/guru/soal_import.php?bank_id=$bankId");
        }

        // Siapkan bobot per tipe dari konfigurasi bank soal
        $configuredWeights = [
            'pilgan' => (float)($settings['pilgan']['weight'] ?? 10.0),
            'kompleks' => (float)($settings['kompleks']['weight'] ?? 10.0),
            'jodohkan' => (float)($settings['jodohkan']['weight'] ?? 10.0),
            'benarsalah' => (float)($settings['benarsalah']['weight'] ?? 10.0),
            'isian' => (float)($settings['isian']['weight'] ?? 10.0),
            'uraian' => (float)($settings['uraian']['weight'] ?? 10.0),
        ];

        // Parsing soal
        $parsedQuestions = WordQuestionService::parseQuestionsFromText($rawText, $configuredWeights);

        if (empty($parsedQuestions)) {
            set_flash('error', 'Gagal memproses soal! Pastikan penulisan soal dalam dokumen Word mengikuti template dengan penanda seperti [SOAL], [A], [KUNCI], dll.');
            redirect(BASE_URL . "/guru/soal_import.php?bank_id=$bankId");
        }

        // Simpan ke database
        $numStmt = $pdo->prepare("SELECT COALESCE(MAX(question_number), 0) FROM questions WHERE bank_id = ?");
        $numStmt->execute([$bankId]);
        $currentNum = (int)$numStmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO questions (bank_id, question_number, type, question_text, options_data, correct_answer, score_weight, explanation) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $typeCounts = ['pilgan' => 0, 'kompleks' => 0, 'jodohkan' => 0, 'benarsalah' => 0, 'isian' => 0, 'uraian' => 0];

        foreach ($parsedQuestions as $q) {
            $currentNum++;
            $t = $q['type'];
            if (isset($typeCounts[$t])) $typeCounts[$t]++;

            $ins->execute([
                $bankId,
                $currentNum,
                $t,
                $q['question_text'],
                $q['options_data'],
                $q['correct_answer'],
                $q['score_weight'],
                $q['explanation']
            ]);
        }

        // Update total soal di bank
        $upd = $pdo->prepare("UPDATE question_banks SET total_questions = (SELECT COUNT(*) FROM questions WHERE bank_id = ?) WHERE id = ?");
        $upd->execute([$bankId, $bankId]);

        $summaryText = [];
        if ($typeCounts['pilgan'] > 0) $summaryText[] = "{$typeCounts['pilgan']} Pilgan";
        if ($typeCounts['kompleks'] > 0) $summaryText[] = "{$typeCounts['kompleks']} Kompleks";
        if ($typeCounts['jodohkan'] > 0) $summaryText[] = "{$typeCounts['jodohkan']} Menjodohkan";
        if ($typeCounts['benarsalah'] > 0) $summaryText[] = "{$typeCounts['benarsalah']} Benar/Salah";
        if ($typeCounts['isian'] > 0) $summaryText[] = "{$typeCounts['isian']} Isian";
        if ($typeCounts['uraian'] > 0) $summaryText[] = "{$typeCounts['uraian']} Uraian";

        set_flash('success', "Alhamdulillah! Berhasil mengimpor <strong>" . count($parsedQuestions) . " butir soal dari Word</strong> (" . implode(', ', $summaryText) . "). Bobot nilai otomatis disesuaikan dengan konfigurasi bank soal.");
        redirect(BASE_URL . "/guru/soal_editor.php?bank_id=$bankId");
    } else {
        set_flash('error', 'Silakan pilih berkas dokumen Word yang akan diunggah.');
        redirect(BASE_URL . "/guru/soal_import.php?bank_id=$bankId");
    }
}

// Ambil daftar bank soal milik guru
$where = $isAdmin ? "" : "WHERE qb.teacher_id = $teacherId";
$banks = $pdo->query("SELECT qb.*, s.name as subject_name FROM question_banks qb JOIN subjects s ON qb.subject_id = s.id $where ORDER BY qb.title ASC")->fetchAll();

// Cek status bank soal yang dipilih saat ini
$selectedBank = null;
$bankSettings = null;
$isConfigured = false;

if ($selectedBankId > 0) {
    foreach ($banks as $b) {
        if ($b['id'] == $selectedBankId) {
            $selectedBank = $b;
            break;
        }
    }
    if ($selectedBank) {
        $bankSettings = json_decode($selectedBank['question_settings'] ?? 'null', true);
        $isConfigured = !empty($bankSettings['is_configured']);
    }
}

$pageTitle = 'Upload & Import Soal Microsoft Word';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-indigo-600 uppercase tracking-wider mb-1">
                <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="hover:underline flex items-center">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Bank Soal
                </a>
                <span>&bull;</span>
                <span>Upload Word (.docx / .doc)</span>
            </div>
            <h1 class="text-2xl font-black text-slate-800">Upload & Import Soal dari Word</h1>
            <p class="text-sm text-slate-500">Unggah butir soal langsung dari naskah Microsoft Word dengan format 6 jenis tipe soal.</p>
        </div>

        <div class="flex items-center space-x-2">
            <a href="<?= BASE_URL ?>/guru/bank_soal.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                <i class="fa-solid fa-boxes-stacked mr-1.5"></i> Bank Soal Saya
            </a>
        </div>
    </div>

    <!-- Download Word Template Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-indigo-800 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
        <div class="relative z-10 max-w-xl">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/20 text-blue-100 text-xs font-bold uppercase tracking-wider mb-3">
                <i class="fa-solid fa-file-word mr-1.5 text-blue-200"></i> Format Resmi Microsoft Word & Excel
            </span>
            <h2 class="text-xl sm:text-2xl font-bold leading-tight">Unduh Template Naskah Soal</h2>
            <p class="text-blue-100/90 text-xs sm:text-sm mt-1 leading-relaxed">
                Template dalam format <strong>tabel modern</strong> (.docx) siap diisi guru langsung di Microsoft Word atau LibreOffice. Mendukung 6 jenis tipe soal lengkap dengan kolom No, Tipe, Soal, Opsi, Kunci, dan Pembahasan.
            </p>
        </div>

        <div class="relative z-10 flex flex-wrap gap-3">
            <a href="<?= BASE_URL ?>/guru/soal_import.php?download_template=docx" class="px-5 py-3 bg-white hover:bg-blue-50 text-blue-900 font-extrabold rounded-2xl text-xs transition shadow-lg shadow-black/10 flex items-center">
                <i class="fa-solid fa-file-word text-blue-600 text-base mr-2"></i>
                <span>Template Word (.docx) Tabel</span>
            </a>
            <a href="<?= BASE_URL ?>/guru/soal_import.php?download_template=doc" class="px-5 py-3 bg-blue-800/60 hover:bg-blue-800 border border-white/20 text-white font-bold rounded-2xl text-xs transition flex items-center">
                <i class="fa-solid fa-file-word text-base mr-2"></i>
                <span>Template (.doc) Legacy</span>
            </a>
            <a href="<?= BASE_URL ?>/guru/soal_import.php?download_template=xlsx" class="px-5 py-3 bg-emerald-700/60 hover:bg-emerald-700 border border-white/20 text-white font-bold rounded-2xl text-xs transition flex items-center">
                <i class="fa-solid fa-file-excel text-emerald-300 text-base mr-2"></i>
                <span>Template Excel (.xlsx)</span>
            </a>
        </div>

        <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none transform translate-x-8 translate-y-8">
            <i class="fa-solid fa-file-lines text-[200px]"></i>
        </div>
    </div>

    <!-- Pilih Bank Soal Card -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-6">
        <div>
            <label class="block font-bold text-slate-800 text-sm mb-2">1. Pilih Bank Soal Tujuan Ujian *</label>
            <form method="GET" action="">
                <select name="bank_id" onchange="this.form.submit()" class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-semibold">
                    <option value="0">-- Pilih Bank Soal Terlebih Dahulu --</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $selectedBankId == $b['id'] ? 'selected' : '' ?>>
                            [<?= e($b['code']) ?>] <?= e($b['title']) ?> (<?= e($b['subject_name']) ?> &bull; Tingkat <?= e($b['class_level']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedBankId > 0 && $selectedBank): ?>
            
            <!-- STATUS 1: JIKA BELUM SETTING KOMPOSISI & BOBOT (UPLOAD DIKUNCI) -->
            <?php if (!$isConfigured): ?>
                <div class="p-6 rounded-3xl bg-amber-50 border-2 border-amber-300 text-amber-900 space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xl flex-shrink-0 mt-0.5">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-base text-amber-950">Akses Upload Soal Belum Dapat Digunakan!</h3>
                            <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                                Anda <strong>wajib mengatur jenis soal apa saja yang akan ditampilkan beserta bobot nilainya</strong> terlebih dahulu sebelum sistem mengizinkan proses unggah berkas Microsoft Word.
                            </p>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="button" onclick="openSettingModal()" class="px-6 py-3 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white font-extrabold rounded-2xl text-xs transition shadow-lg shadow-amber-500/20 flex items-center">
                            <i class="fa-solid fa-sliders mr-2"></i> Atur Komposisi Soal & Bobot Nilai Sekarang
                        </button>
                    </div>
                </div>

                <!-- Form Upload Disabled / Locked -->
                <div class="opacity-40 pointer-events-none select-none relative">
                    <div class="border-2 border-dashed border-slate-300 rounded-3xl p-8 text-center bg-slate-50">
                        <i class="fa-solid fa-lock text-3xl text-slate-400 mb-2"></i>
                        <div class="text-sm font-bold text-slate-500">Formulir Upload Terkunci</div>
                        <div class="text-xs text-slate-400">Atur bobot soal di atas untuk membuka akses upload.</div>
                    </div>
                </div>

            <!-- STATUS 2: JIKA SUDAH DIKONFIGURASI (UPLOAD DIIZINKAN) -->
            <?php else: ?>
                <div class="p-5 rounded-3xl bg-emerald-50/70 border border-emerald-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-emerald-800 font-extrabold text-sm">
                            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                            <span>Pengaturan Komposisi & Bobot Nilai Telah Terpasang</span>
                        </div>
                        <button type="button" onclick="openSettingModal()" class="text-xs font-bold text-indigo-700 hover:text-indigo-900 underline flex items-center">
                            <i class="fa-solid fa-pen-to-square mr-1"></i> Ubah Pengaturan Bobot
                        </button>
                    </div>

                    <!-- Target Breakdown Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs pt-1">
                        <?php 
                        $labels = [
                            'pilgan' => 'Pilgan Biasa',
                            'kompleks' => 'PG Kompleks',
                            'jodohkan' => 'Menjodohkan',
                            'benarsalah' => 'Benar / Salah',
                            'isian' => 'Isian Singkat',
                            'uraian' => 'Uraian / Essay'
                        ];
                        foreach ($labels as $tKey => $tName):
                            $cfg = $bankSettings[$tKey] ?? ['active' => 0, 'count' => 0, 'weight' => 0];
                            if (!empty($cfg['active'])):
                        ?>
                            <div class="p-2.5 bg-white rounded-xl border border-emerald-100 shadow-sm">
                                <span class="font-bold text-slate-700 block text-[11px]"><?= $tName ?></span>
                                <div class="text-xs text-emerald-700 font-bold mt-0.5">
                                    <?= $cfg['count'] ?> Butir &bull; @<?= $cfg['weight'] ?> poin
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>

                    <div class="text-[11px] text-slate-500 font-semibold pt-1">
                        Target Total: <strong><?= $bankSettings['total_questions'] ?> Butir Soal</strong> &bull; Total Nilai: <strong class="text-emerald-700 font-mono"><?= $bankSettings['total_weight'] ?> Poin</strong>
                    </div>
                </div>

                <!-- Formulir Upload Berkas Word Aktif -->
                <form method="POST" action="" enctype="multipart/form-data" class="space-y-6 text-xs pt-2">
                    <input type="hidden" name="action" value="import_word">
                    <input type="hidden" name="bank_id" value="<?= $selectedBankId ?>">

                    <div>
                        <label class="block font-bold text-slate-800 text-sm mb-1">2. Pilih Naskah Soal Microsoft Word (.docx / .doc) *</label>
                        <p class="text-slate-400 text-xs mb-3">Sistem akan membaca soal dan otomatis menetapkan bobot nilai sesuai pengaturan di atas.</p>

                        <div class="border-2 border-dashed border-indigo-300 hover:border-indigo-600 rounded-3xl p-8 text-center transition bg-indigo-50/20">
                            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl shadow-sm">
                                <i class="fa-solid fa-file-word"></i>
                            </div>
                            <div class="text-sm font-bold text-slate-800 mb-1">Klik untuk memilih berkas Word atau seret ke sini</div>
                            <div class="text-xs text-slate-400 mb-4">Format berkas: <strong>.docx, .doc</strong> (Maksimal 15 MB)</div>
                            <input type="file" name="file_soal" accept=".docx,.doc,.txt" required class="mx-auto block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-8 py-3.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold rounded-2xl transition shadow-xl shadow-indigo-300 flex items-center text-xs">
                            <i class="fa-solid fa-cloud-arrow-up mr-2 text-base"></i> Proses & Impor Naskah Soal Word
                        </button>
                    </div>
                </form>

            <?php endif; ?>

        <?php else: ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-200 text-slate-400 text-xs">
                <i class="fa-solid fa-arrow-up text-lg mb-2 text-slate-400 block"></i>
                Silakan pilih Bank Soal tujuan di atas terlebih dahulu untuk memeriksa konfigurasi dan membuka formulir upload.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL SETTING KOMPOSISI & BOBOT SOAL -->
<div id="modalSettingBobot" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-8 border border-slate-200 max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-lg font-black text-slate-900 flex items-center">
                    <i class="fa-solid fa-sliders text-indigo-600 mr-2.5"></i> Setting Komposisi & Bobot Nilai Soal
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Tentukan jenis soal apa saja yang mau ditampilkan dan bobot nilai per butir soal.</p>
            </div>
            <button onclick="closeSettingModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="bank_id" value="<?= $selectedBankId ?>">

            <!-- Table 6 Jenis Soal -->
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200">
                            <th class="py-3 px-3 text-center">Aktif</th>
                            <th class="py-3 px-3">Jenis Tipe Soal</th>
                            <th class="py-3 px-3">Jumlah Soal</th>
                            <th class="py-3 px-3">Bobot / Soal</th>
                            <th class="py-3 px-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php 
                        $typesForm = [
                            'pilgan' => ['name' => 'Pilihan Ganda Biasa', 'def_count' => 20, 'def_weight' => 2.5],
                            'kompleks' => ['name' => 'Pilihan Ganda Kompleks', 'def_count' => 5, 'def_weight' => 4.0],
                            'jodohkan' => ['name' => 'Menjodohkan (Matching)', 'def_count' => 5, 'def_weight' => 4.0],
                            'benarsalah' => ['name' => 'Pernyataan Benar / Salah', 'def_count' => 5, 'def_weight' => 2.0],
                            'isian' => ['name' => 'Isian Singkat', 'def_count' => 5, 'def_weight' => 2.0],
                            'uraian' => ['name' => 'Uraian / Essay', 'def_count' => 2, 'def_weight' => 10.0]
                        ];

                        foreach ($typesForm as $k => $info):
                            $curCfg = $bankSettings[$k] ?? ['active' => 1, 'count' => $info['def_count'], 'weight' => $info['def_weight']];
                            $isActive = isset($curCfg['active']) ? (int)$curCfg['active'] : 1;
                            $countVal = isset($curCfg['count']) ? (int)$curCfg['count'] : $info['def_count'];
                            $weightVal = isset($curCfg['weight']) ? (float)$curCfg['weight'] : $info['def_weight'];
                        ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-3 text-center">
                                    <input type="checkbox" name="<?= $k ?>_active" id="chk_<?= $k ?>" value="1" <?= $isActive ? 'checked' : '' ?> onchange="recalculateTotals()" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                </td>
                                <td class="py-3 px-3 font-bold text-slate-800">
                                    <label for="chk_<?= $k ?>" class="cursor-pointer"><?= $info['name'] ?></label>
                                </td>
                                <td class="py-3 px-3">
                                    <input type="number" name="<?= $k ?>_count" id="count_<?= $k ?>" value="<?= $countVal ?>" min="0" max="100" oninput="recalculateTotals()" class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 font-bold text-xs text-center focus:ring-2 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 px-3">
                                    <input type="number" step="0.5" name="<?= $k ?>_weight" id="weight_<?= $k ?>" value="<?= $weightVal ?>" min="0" max="100" oninput="recalculateTotals()" class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 font-bold text-xs text-center focus:ring-2 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-800" id="subtotal_<?= $k ?>">
                                    <?= round($countVal * $weightVal, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-50 font-black border-t-2 border-slate-200">
                        <tr>
                            <td colspan="2" class="py-3 px-3 text-slate-800 text-right uppercase tracking-wider">Total Target:</td>
                            <td class="py-3 px-3 text-center font-mono text-indigo-700" id="totalCountDisplay">0 Soal</td>
                            <td></td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-700 text-sm" id="totalScoreDisplay">0 Poin</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="bg-indigo-50 p-3 rounded-xl border border-indigo-100 text-[11px] text-indigo-800 flex items-center space-x-2">
                <i class="fa-solid fa-circle-info text-indigo-600 text-sm"></i>
                <span>Pastikan total nilai target sudah sesuai (misal: 100 poin). Bobot ini otomatis diterapkan pada butir soal yang diunggah dari Word.</span>
            </div>

            <div class="pt-3 flex justify-end space-x-3 border-t border-slate-100">
                <button type="button" onclick="closeSettingModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
                    Batal
                </button>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-lg shadow-indigo-200 flex items-center">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Simpan Konfigurasi Bobot
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSettingModal() {
    document.getElementById('modalSettingBobot').classList.remove('hidden');
    recalculateTotals();
}

function closeSettingModal() {
    document.getElementById('modalSettingBobot').classList.add('hidden');
}

function recalculateTotals() {
    const types = ['pilgan', 'kompleks', 'jodohkan', 'benarsalah', 'isian', 'uraian'];
    let totalQuestions = 0;
    let totalScore = 0;

    types.forEach(t => {
        const chk = document.getElementById('chk_' + t);
        const countInput = document.getElementById('count_' + t);
        const weightInput = document.getElementById('weight_' + t);
        const subtotalEl = document.getElementById('subtotal_' + t);

        if (chk && countInput && weightInput && subtotalEl) {
            const isActive = chk.checked;
            const count = isActive ? (parseInt(countInput.value) || 0) : 0;
            const weight = isActive ? (parseFloat(weightInput.value) || 0) : 0;
            const subtotal = count * weight;

            subtotalEl.innerText = subtotal.toFixed(1);

            if (isActive) {
                totalQuestions += count;
                totalScore += subtotal;
                countInput.disabled = false;
                weightInput.disabled = false;
            } else {
                countInput.disabled = true;
                weightInput.disabled = true;
            }
        }
    });

    document.getElementById('totalCountDisplay').innerText = totalQuestions + ' Soal';
    document.getElementById('totalScoreDisplay').innerText = totalScore.toFixed(1) + ' Poin';
}

document.addEventListener('DOMContentLoaded', () => {
    recalculateTotals();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
