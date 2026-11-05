<?php
// ujian/ruang.php - Antarmuka Ruang Ujian CBT Siswa (6 Jenis Soal)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

Auth::requireStudent();
$student = Auth::student();
$pdo = Database::getConnection();

// Ambil sesi partisipasi aktif siswa
$pStmt = $pdo->prepare("SELECT ep.*, e.title as exam_title, e.duration_minutes, e.anti_cheat_enabled, 
                               qb.title as bank_title, s.name as subject_name 
                        FROM exam_participations ep 
                        JOIN exams e ON ep.exam_id = e.id 
                        JOIN question_banks qb ON e.bank_id = qb.id 
                        JOIN subjects s ON qb.subject_id = s.id 
                        WHERE ep.student_id = ? AND ep.status = 'in_progress' 
                        ORDER BY ep.id DESC LIMIT 1");
$pStmt->execute([$student['id']]);
$part = $pStmt->fetch();

if (!$part) {
    // Jika tidak ada yang sedang in_progress, arahkan ke konfirmasi
    redirect(BASE_URL . '/ujian/konfirmasi.php');
}

$partId = (int)$part['id'];
$remainingSeconds = max(10, (int)$part['remaining_seconds']);

// Ambil urutan soal yang telah diacak khusus untuk peserta ini
$qOrder = json_decode($part['question_order'] ?? '[]', true);
if (empty($qOrder)) {
    // Fallback jika belum diacak
    $fallbackQ = $pdo->prepare("SELECT id FROM questions WHERE bank_id = (SELECT bank_id FROM exams WHERE id = ?) ORDER BY question_number ASC");
    $fallbackQ->execute([$part['exam_id']]);
    $qOrder = $fallbackQ->fetchAll(PDO::FETCH_COLUMN);
}

// Ambil seluruh data soal dan jawaban siswa yang sudah tersimpan
$placeholders = implode(',', array_fill(0, count($qOrder), '?'));
$qDataStmt = $pdo->prepare("SELECT q.*, sa.student_answer, sa.is_marked_doubtful 
                            FROM questions q 
                            LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.participation_id = ? 
                            WHERE q.id IN ($placeholders)");
$params = array_merge([$partId], $qOrder);
$qDataStmt->execute($params);
$rawQuestions = $qDataStmt->fetchAll();

// Susun array soal terurut rapi sesuai $qOrder
$questionsMap = [];
foreach ($rawQuestions as $rq) {
    $questionsMap[$rq['id']] = $rq;
}

$orderedQuestions = [];
$number = 1;
foreach ($qOrder as $qid) {
    if (isset($questionsMap[$qid])) {
        $item = $questionsMap[$qid];
        $item['display_number'] = $number++;
        $item['options_decoded'] = json_decode($item['options_data'] ?? '[]', true) ?: [];
        $item['student_answer_decoded'] = json_decode($item['student_answer'] ?? 'null', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $item['student_answer_decoded'] = $item['student_answer'];
        }
        $orderedQuestions[] = $item;
    }
}

$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);
$maxViolations = (int)get_setting('max_tab_violations', '3');
$antiCheatStrictness = get_setting('anti_cheat_strictness', 'medium');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($part['exam_title']) ?> - <?= e($appTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; user-select: none; }
        .font-size-small { font-size: 0.875rem !important; }
        .font-size-normal { font-size: 1rem !important; }
        .font-size-large { font-size: 1.15rem !important; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col justify-between" oncontextmenu="return false;">

    <!-- Top Examination Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm px-4 sm:px-6 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Left: Info Siswa & Ujian -->
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white font-extrabold flex items-center justify-center text-sm shadow-md shadow-indigo-200">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <div class="font-extrabold text-slate-900 text-sm leading-tight"><?= e($student['full_name']) ?></div>
                    <div class="text-xs text-slate-500 font-medium"><?= e($part['subject_name']) ?> &bull; <?= e($student['class_name']) ?></div>
                </div>
            </div>

            <!-- Center: Countdown Timer -->
            <div class="flex items-center space-x-3 bg-slate-900 text-white px-4 py-2 rounded-2xl shadow-inner">
                <i class="fa-solid fa-stopwatch text-amber-400 text-base" id="timerIcon"></i>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 leading-none">Sisa Waktu</div>
                    <div id="countdownTimer" class="font-mono text-lg font-black tracking-widest text-white leading-tight">
                        --:--
                    </div>
                </div>
            </div>

            <!-- Right: Font Resizer & Palette Toggle -->
            <div class="flex items-center space-x-2">
                <!-- Ukuran Font -->
                <div class="hidden sm:inline-flex items-center bg-slate-100 rounded-xl p-1 border border-slate-200">
                    <button onclick="setFontSize('small')" title="Font Kecil" class="px-2 py-1 text-xs font-bold rounded-lg hover:bg-white text-slate-600 transition">A-</button>
                    <button onclick="setFontSize('normal')" title="Font Normal" class="px-2 py-1 text-xs font-bold rounded-lg bg-white shadow-sm text-slate-800 transition">A</button>
                    <button onclick="setFontSize('large')" title="Font Besar" class="px-2 py-1 text-xs font-bold rounded-lg hover:bg-white text-slate-600 transition">A+</button>
                </div>

                <!-- Fullscreen Toggle -->
                <button onclick="toggleFullscreen()" title="Layar Penuh" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition">
                    <i class="fa-solid fa-expand text-base"></i>
                </button>

                <!-- Tombol Buka Palet Nomor Soal (Mobile & Desktop) -->
                <button onclick="togglePaletteDrawer()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition shadow shadow-indigo-200 flex items-center">
                    <i class="fa-solid fa-table-cells mr-1.5"></i>
                    <span class="hidden sm:inline">Daftar Soal</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Exam Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left/Center: Question View Area (Cols 1-3) -->
        <div class="lg:col-span-3 space-y-4">
            
            <!-- Question Card -->
            <div id="questionCard" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 min-h-[480px] flex flex-col justify-between font-size-normal">
                
                <!-- Question Header -->
                <div>
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
                        <div class="flex items-center space-x-2">
                            <span class="w-8 h-8 rounded-xl bg-slate-900 text-white font-black flex items-center justify-center text-sm" id="qNumberDisplay">
                                1
                            </span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase" id="qTypeBadge">
                                Pilihan Ganda
                            </span>
                            <span class="text-xs text-slate-400 font-semibold" id="qWeightDisplay">
                                Bobot: 10
                            </span>
                        </div>

                        <!-- Auto-Save Status Indicator -->
                        <div id="saveStatus" class="flex items-center space-x-1.5 text-xs font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200 transition">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Tersimpan</span>
                        </div>
                    </div>

                    <!-- Question Text / Stimulus -->
                    <div id="qTextDisplay" class="prose max-w-none text-slate-800 leading-relaxed mb-6 font-normal">
                        <!-- Teks soal di-render dinamis via JS -->
                    </div>

                    <!-- Dynamic Answer Widgets Area -->
                    <div id="qAnswerContainer" class="pt-2">
                        <!-- Render 6 jenis widget jawaban via JS -->
                    </div>
                </div>

                <!-- Bottom Navigation Inside Card -->
                <div class="pt-8 mt-8 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <button id="btnPrev" onclick="navigateQuestion(-1)" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition flex items-center justify-center">
                        <i class="fa-solid fa-chevron-left mr-2"></i> Soal Sebelumnya
                    </button>

                    <!-- Checkbox Ragu-Ragu -->
                    <label class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 rounded-xl border-2 border-amber-300 bg-amber-50/60 text-amber-900 font-bold text-xs cursor-pointer hover:bg-amber-100 transition">
                        <input type="checkbox" id="doubtfulCheck" onchange="toggleDoubtfulCurrent()" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400">
                        <span>Ragu-Ragu</span>
                    </label>

                    <div class="flex items-center space-x-2">
                        <button id="btnNext" onclick="navigateQuestion(1)" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-200 flex items-center justify-center">
                            <span>Soal Berikutnya</span>
                            <i class="fa-solid fa-chevron-right ml-2"></i>
                        </button>

                        <button id="btnFinish" onclick="confirmFinishExam()" class="hidden px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-200 flex items-center justify-center">
                            <i class="fa-solid fa-flag-checkered mr-2"></i> Selesai Ujian
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Right Side: Question Palette (Palet Nomor Soal) -->
        <div id="paletteCard" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm flex items-center">
                    <i class="fa-solid fa-table-cells text-indigo-600 mr-2"></i> Nomor Soal
                </h3>
                <span class="text-xs text-slate-400 font-bold" id="answeredCounter">
                    0 / <?= count($orderedQuestions) ?>
                </span>
            </div>

            <!-- Legend -->
            <div class="grid grid-cols-3 gap-2 text-[10px] font-bold text-center">
                <div class="p-1 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-200">Dijawab</div>
                <div class="p-1 rounded-lg bg-amber-100 text-amber-800 border border-amber-200">Ragu-Ragu</div>
                <div class="p-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200">Belum</div>
            </div>

            <!-- Numbers Grid -->
            <div class="grid grid-cols-5 gap-2 max-h-[380px] overflow-y-auto pr-1" id="paletteGrid">
                <!-- Di-render dinamis oleh JS -->
            </div>

            <div class="pt-4 border-t border-slate-100">
                <button onclick="confirmFinishExam()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl text-xs transition shadow-lg shadow-emerald-500/20 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check mr-2"></i> Selesaikan Ujian Sekarang
                </button>
            </div>
        </div>

    </main>

    <!-- Data Injected from Server for CBT Client Engine -->
    <script>
    const CBT_CONFIG = {
        apiEndpoint: '<?= BASE_URL ?>/ujian/api_ujian.php',
        finishEndpoint: '<?= BASE_URL ?>/ujian/selesai.php?participation_id=<?= $partId ?>',
        participationId: <?= $partId ?>,
        remainingSeconds: <?= $remainingSeconds ?>,
        antiCheatEnabled: <?= $part['anti_cheat_enabled'] ? 'true' : 'false' ?>,
        maxViolations: <?= $maxViolations ?>,
        questions: <?= json_encode($orderedQuestions, JSON_UNESCAPED_UNICODE) ?>
    };
    </script>

    <!-- CBT Client-side Script -->
    <script src="<?= BASE_URL ?>/assets/js/cbt-engine.js"></script>
</body>
</html>
