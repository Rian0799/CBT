<?php
// ujian/konfirmasi.php - Konfirmasi Data Siswa & Masukkan Token Ujian
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireStudent();
$student = Auth::student();
$pdo = Database::getConnection();

// Cari ujian yang sedang aktif saat ini
$activeExam = $pdo->query("SELECT e.*, qb.title as bank_title, s.name as subject_name 
                           FROM exams e 
                           JOIN question_banks qb ON e.bank_id = qb.id 
                           JOIN subjects s ON qb.subject_id = s.id 
                           WHERE e.status = 'active' 
                           ORDER BY e.id DESC LIMIT 1")->fetch();

// Cek apakah siswa sudah punya partisipasi di ujian ini
$existingParticipation = null;
if ($activeExam) {
    $pStmt = $pdo->prepare("SELECT * FROM exam_participations WHERE exam_id = ? AND student_id = ? LIMIT 1");
    $pStmt->execute([$activeExam['id'], $student['id']]);
    $existingParticipation = $pStmt->fetch();

    // Jika sudah disubmit (selesai), langsung arahkan ke halaman selesai
    if ($existingParticipation && $existingParticipation['status'] === 'submitted') {
        redirect(BASE_URL . '/ujian/selesai.php?participation_id=' . $existingParticipation['id']);
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeExam) {
    $inputToken = strtoupper(trim($_POST['token'] ?? ''));

    if (empty($inputToken)) {
        $error = 'Token ujian wajib diisi!';
    } elseif ($inputToken !== $activeExam['current_token']) {
        $error = 'Token ujian salah atau sudah kadaluarsa! Silakan tanyakan token terbaru kepada Pengawas/Proktor.';
    } else {
        // Token valid! Buat atau lanjutkan sesi pengerjaan
        if (!$existingParticipation) {
            // Ambil semua ID soal untuk diacak urutannya jika randomize_questions aktif
            $qStmt = $pdo->prepare("SELECT id FROM questions WHERE bank_id = ? ORDER BY question_number ASC");
            $qStmt->execute([$activeExam['bank_id']]);
            $qIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);

            if ($activeExam['randomize_questions']) {
                shuffle($qIds);
            }
            $questionOrderJson = json_encode($qIds);
            $durationSeconds = $activeExam['duration_minutes'] * 60;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $ins = $pdo->prepare("INSERT INTO exam_participations (exam_id, student_id, start_time, remaining_seconds, status, ip_address, user_agent, question_order) 
                                  VALUES (?, ?, NOW(), ?, 'in_progress', ?, ?, ?)");
            $ins->execute([$activeExam['id'], $student['id'], $durationSeconds, $ip, $ua, $questionOrderJson]);
            $participationId = $pdo->lastInsertId();

            // Inisialisasi baris student_answers kosong untuk setiap soal
            $insAns = $pdo->prepare("INSERT INTO student_answers (participation_id, question_id, student_answer, is_marked_doubtful) VALUES (?, ?, NULL, 0)");
            foreach ($qIds as $qid) {
                $insAns->execute([$participationId, $qid]);
            }

            // Catat log login
            $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, 'start', 'Mulai pengerjaan ujian')");
            $log->execute([$participationId, $student['id'], $activeExam['id']]);
        } else {
            // Update status jika not_started
            if ($existingParticipation['status'] === 'not_started') {
                $upd = $pdo->prepare("UPDATE exam_participations SET status = 'in_progress', start_time = NOW() WHERE id = ?");
                $upd->execute([$existingParticipation['id']]);
            }
        }

        redirect(BASE_URL . '/ujian/ruang.php');
    }
}

$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Data & Token - <?= e($appTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 px-4 flex flex-col justify-between">
    <!-- Top Header -->
    <header class="max-w-3xl mx-auto w-full flex items-center justify-between mb-6 pb-4 border-b border-slate-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shadow-md shadow-emerald-200">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h1 class="font-bold text-slate-800 text-base leading-tight"><?= e($appTitle) ?></h1>
                <p class="text-xs text-slate-500"><?= e($schoolName) ?></p>
            </div>
        </div>

        <div>
            <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Keluar dari sesi siswa?')" class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 transition border border-rose-200">
                <i class="fa-solid fa-power-off mr-1"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Confirmation Card -->
    <main class="max-w-3xl mx-auto w-full my-auto">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
            <!-- Header Card Banner -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 sm:p-8 text-white">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/20 text-white text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-circle-check mr-1.5 text-emerald-200"></i> Konfirmasi Peserta Ujian
                </span>
                <h2 class="text-2xl font-black">Selamat Datang, <?= e($student['full_name']) ?>!</h2>
                <p class="text-xs text-emerald-100 mt-1">Periksa kembali data diri dan mata pelajaran ujian sebelum memulai pengerjaan.</p>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <?php if (!empty($error)): ?>
                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-3">
                        <i class="fa-solid fa-circle-xmark text-lg text-rose-500"></i>
                        <span class="font-semibold"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Grid Data Siswa & Ujian -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                        <span class="font-bold uppercase tracking-wider text-slate-400 block text-[10px]">Identitas Siswa:</span>
                        <div>Nama: <strong class="text-slate-800"><?= e($student['full_name']) ?></strong></div>
                        <div>NISN: <strong class="text-slate-800 font-mono"><?= e($student['nisn']) ?></strong></div>
                        <div>No. Peserta: <strong class="text-slate-800 font-mono"><?= e($student['exam_number']) ?></strong></div>
                        <div>Kelas: <strong class="text-slate-800"><?= e($student['class_name']) ?></strong></div>
                    </div>

                    <?php if ($activeExam): ?>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                            <span class="font-bold uppercase tracking-wider text-slate-400 block text-[10px]">Informasi Tes:</span>
                            <div>Mata Pelajaran: <strong class="text-indigo-700"><?= e($activeExam['subject_name']) ?></strong></div>
                            <div>Ujian: <strong class="text-slate-800"><?= e($activeExam['title']) ?></strong></div>
                            <div>Durasi: <strong class="text-slate-800"><?= $activeExam['duration_minutes'] ?> Menit</strong></div>
                            <div>Ruang / Sesi: <?= e($activeExam['room']) ?> &bull; <?= e($activeExam['session_name']) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-center text-center">
                            Tidak ada jadwal ujian aktif saat ini. Silakan hubungi proktor.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Petunjuk & Tata Tertib -->
                <div class="bg-indigo-50/60 rounded-2xl p-4 border border-indigo-100 text-xs text-indigo-900 space-y-1.5">
                    <div class="font-bold flex items-center text-indigo-700 mb-1">
                        <i class="fa-solid fa-circle-exclamation mr-1.5"></i> Tata Tertib Ujian Berbasis Komputer:
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-600">
                        <li>Pastikan koneksi perangkat stabil. Jawaban Anda akan otomatis tersimpan (auto-save).</li>
                        <li><strong>Dilarang membuka tab baru, browser lain, atau keluar dari jendela ujian.</strong> Sistem otomatis mencatat pelanggaran dan dapat mengunci ujian Anda.</li>
                        <li>Gunakan tombol nomor soal untuk melompat antar butir pertanyaan.</li>
                        <li>Tanyakan kode <strong>TOKEN UJIAN</strong> kepada Pengawas / Proktor di ruangan.</li>
                    </ul>
                </div>

                <?php if ($activeExam): ?>
                    <!-- Form Masukkan Token -->
                    <form method="POST" action="" class="space-y-4 pt-2">
                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider text-xs mb-2 text-center">
                                Masukkan Token Ujian yang Diberikan Pengawas *
                            </label>
                            <input type="text" name="token" required maxlength="10" placeholder="KODE TOKEN" autofocus class="w-full max-w-xs mx-auto block px-4 py-3 text-center rounded-2xl border-2 border-emerald-500 focus:ring-4 focus:ring-emerald-200 outline-none text-xl font-mono font-black uppercase tracking-widest text-emerald-700 bg-emerald-50/40 transition">
                        </div>

                        <div class="text-center pt-2">
                            <button type="submit" class="px-8 py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-2xl transition duration-200 shadow-xl shadow-emerald-500/20 text-sm inline-flex items-center">
                                <i class="fa-solid fa-play mr-2"></i> Mulai Mengerjakan Ujian
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="max-w-3xl mx-auto w-full text-center text-xs text-slate-400 mt-6">
        &copy; <?= date('Y') ?> <?= e($schoolName) ?> &bull; CBT Smart Exam
    </footer>
</body>
</html>
