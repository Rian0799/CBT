<?php
// admin/exams.php - Manajemen Jadwal & Sesi Ujian
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $bankId = (int)($_POST['bank_id'] ?? 0);
        $sessionName = trim($_POST['session_name'] ?? 'Sesi 1');
        $room = trim($_POST['room'] ?? 'Lab 1');
        $startTime = $_POST['start_time'] ?? date('Y-m-d H:i');
        $endTime = $_POST['end_time'] ?? date('Y-m-d H:i', strtotime('+4 hours'));
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        $token = strtoupper(trim($_POST['token'] ?? generate_random_token(6)));
        $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
        $randomizeOptions = isset($_POST['randomize_options']) ? 1 : 0;
        $antiCheat = isset($_POST['anti_cheat_enabled']) ? 1 : 0;
        $showResults = isset($_POST['show_results_immediately']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $createdBy = Auth::user()['id'];

        if ($title && $bankId) {
            $stmt = $pdo->prepare("INSERT INTO exams (title, bank_id, session_name, room, start_time, end_time, duration_minutes, current_token, randomize_questions, randomize_options, anti_cheat_enabled, show_results_immediately, status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $bankId, $sessionName, $room, $startTime, $endTime, $duration, $token, $randomizeQuestions, $randomizeOptions, $antiCheat, $showResults, $status, $createdBy]);
            
            $examId = $pdo->lastInsertId();
            // Rekam juga ke exam_tokens
            $tokStmt = $pdo->prepare("INSERT INTO exam_tokens (exam_id, token, generated_by, valid_until) VALUES (?, ?, ?, ?)");
            $tokStmt->execute([$examId, $token, $createdBy, date('Y-m-d H:i:s', strtotime('+4 hours'))]);

            set_flash('success', "Jadwal ujian <strong>" . e($title) . "</strong> berhasil dibuat dengan Token: <code>$token</code>");
        }
        redirect(BASE_URL . '/admin/exams.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Jadwal ujian berhasil dihapus.");
        redirect(BASE_URL . '/admin/exams.php');
    }

    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'draft';
        $stmt = $pdo->prepare("UPDATE exams SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        set_flash('success', "Status ujian berhasil diubah menjadi: " . ucfirst($newStatus));
        redirect(BASE_URL . '/admin/exams.php');
    }
}

// Ambil daftar ujian beserta info bank soal
$exams = $pdo->query("SELECT e.*, qb.title as bank_title, s.name as subject_name, 
                             (SELECT COUNT(*) FROM exam_participations ep WHERE ep.exam_id = e.id) as participant_count 
                      FROM exams e 
                      JOIN question_banks qb ON e.bank_id = qb.id 
                      JOIN subjects s ON qb.subject_id = s.id 
                      ORDER BY e.id DESC")->fetchAll();

$bankList = $pdo->query("SELECT qb.*, s.name as subject_name FROM question_banks qb JOIN subjects s ON qb.subject_id = s.id ORDER BY qb.title ASC")->fetchAll();

$pageTitle = 'Jadwal & Sesi Ujian';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Jadwal & Sesi Ujian</h1>
            <p class="text-sm text-slate-500">Atur jadwal pelaksanaan ujian, bank soal rujukan, durasi, dan token.</p>
        </div>
        <button onclick="document.getElementById('modalAddExam').classList.remove('hidden')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition flex items-center">
            <i class="fa-solid fa-calendar-plus mr-2"></i> Buat Jadwal Ujian Baru
        </button>
    </div>

    <!-- Exams List Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3.5 px-4">Nama Ujian & Bank Soal</th>
                        <th class="py-3.5 px-4">Sesi & Ruang</th>
                        <th class="py-3.5 px-4">Durasi & Waktu</th>
                        <th class="py-3.5 px-4">Token Aktif</th>
                        <th class="py-3.5 px-4">Peserta</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($exams)): ?>
                        <tr><td colspan="7" class="py-8 text-center text-slate-400">Belum ada jadwal ujian dibuat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($exams as $ex): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= e($ex['title']) ?></div>
                                    <div class="text-slate-500 font-medium"><?= e($ex['bank_title']) ?> &bull; <span class="text-indigo-600"><?= e($ex['subject_name']) ?></span></div>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <?php if ($ex['anti_cheat_enabled']): ?>
                                            <span class="inline-flex items-center text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                                <i class="fa-solid fa-shield mr-1"></i> Anti-Curang
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($ex['randomize_questions']): ?>
                                            <span class="inline-flex items-center text-[10px] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                                <i class="fa-solid fa-shuffle mr-1"></i> Acak Soal
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-700">
                                    <div class="font-bold"><?= e($ex['session_name']) ?></div>
                                    <div class="text-slate-400"><?= e($ex['room']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <div class="font-bold text-slate-800"><?= $ex['duration_minutes'] ?> Menit</div>
                                    <div class="text-[11px] text-slate-400"><?= date('d/m/Y H:i', strtotime($ex['start_time'])) ?></div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-mono font-bold text-sm px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 tracking-wider">
                                        <?= e($ex['current_token']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-slate-800 text-sm"><?= $ex['participant_count'] ?></span> Siswa
                                </td>
                                <td class="py-3.5 px-4">
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                                        <select name="new_status" onchange="this.form.submit()" class="px-2 py-1 rounded-lg text-xs font-bold border <?= $ex['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : ($ex['status'] === 'finished' ? 'bg-slate-100 text-slate-600 border-slate-300' : 'bg-amber-50 text-amber-700 border-amber-300') ?>">
                                            <option value="active" <?= $ex['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                            <option value="draft" <?= $ex['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="finished" <?= $ex['status'] === 'finished' ? 'selected' : '' ?>>Selesai</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex items-center space-x-2">
                                        <a href="<?= BASE_URL ?>/operator/monitoring.php?exam_id=<?= $ex['id'] ?>" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Live Monitor Ujian">
                                            <i class="fa-solid fa-tv"></i>
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('Hapus jadwal ujian ini?');" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                                            <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus Ujian">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Ujian -->
<div id="modalAddExam" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-8 border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-calendar-plus text-indigo-600 mr-2.5"></i> Buat Jadwal Ujian Baru
            </h3>
            <button onclick="document.getElementById('modalAddExam').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Judul / Nama Ujian *</label>
                <input type="text" name="title" required placeholder="Contoh: Asesmen Sumatif Akhir Semester Genap" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-semibold">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Pilih Bank Soal *</label>
                    <select name="bank_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <?php foreach ($bankList as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['title']) ?> (<?= e($b['subject_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Durasi Pengerjaan (Menit) *</label>
                    <input type="number" name="duration_minutes" value="60" min="5" max="300" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Nama Sesi</label>
                    <input type="text" name="session_name" value="Sesi 1" placeholder="Sesi 1, Pagi, dll" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Ruang Ujian</label>
                    <input type="text" name="room" value="Lab Komputer 1" placeholder="Lab 1, Ruang 03, dll" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Waktu Mulai Ujian</label>
                    <input type="datetime-local" name="start_time" value="<?= date('Y-m-d\TH:i') ?>" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Waktu Selesai (Batas Akhir)</label>
                    <input type="datetime-local" name="end_time" value="<?= date('Y-m-d\TH:i', strtotime('+6 hours')) ?>" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Token Ujian Awal</label>
                    <input type="text" name="token" value="<?= generate_random_token(6) ?>" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-mono font-bold uppercase tracking-wider text-indigo-700">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Status Awal</label>
                    <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                        <option value="active">Langsung Aktif</option>
                        <option value="draft">Draft (Disimpan Dahulu)</option>
                    </select>
                </div>
            </div>

            <!-- Toggles & Options -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                <div class="font-bold text-slate-700 uppercase text-[11px] mb-2">Konfigurasi Pengamanan & Aturan:</div>
                <label class="flex items-center space-x-2.5 cursor-pointer">
                    <input type="checkbox" name="anti_cheat_enabled" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span class="font-medium text-slate-700">Aktifkan Mode Anti-Curang (Deteksi pindah tab browser, fullscreen lock)</span>
                </label>
                <label class="flex items-center space-x-2.5 cursor-pointer">
                    <input type="checkbox" name="randomize_questions" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span class="font-medium text-slate-700">Acak Urutan Soal Tiap Siswa</span>
                </label>
                <label class="flex items-center space-x-2.5 cursor-pointer">
                    <input type="checkbox" name="randomize_options" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span class="font-medium text-slate-700">Acak Opsi Pilihan Jawaban (A, B, C, D, E)</span>
                </label>
                <label class="flex items-center space-x-2.5 cursor-pointer">
                    <input type="checkbox" name="show_results_immediately" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span class="font-medium text-slate-700">Tampilkan Nilai ke Siswa Langsung Setelah Selesai</span>
                </label>
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalAddExam').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow shadow-indigo-200">Terbitkan Jadwal Ujian</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
