<?php
// operator/token.php - Token Generator & Rilis Token Ujian
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole(['operator', 'admin']);
$pdo = Database::getConnection();
$operatorId = Auth::user()['id'];

// Daftar ujian aktif
$exams = $pdo->query("SELECT e.*, qb.title as bank_title, s.name as subject_name 
                      FROM exams e 
                      JOIN question_banks qb ON e.bank_id = qb.id 
                      JOIN subjects s ON qb.subject_id = s.id 
                      WHERE e.status = 'active' 
                      ORDER BY e.id DESC")->fetchAll();

$selectedExamId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));

// Handle Generate Token Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_token') {
    $examId = (int)($_POST['exam_id'] ?? 0);
    $customToken = strtoupper(trim($_POST['custom_token'] ?? ''));
    $validMinutes = (int)($_POST['valid_minutes'] ?? 30);

    $token = $customToken ?: generate_random_token(6);
    $validUntil = date('Y-m-d H:i:s', strtotime("+$validMinutes minutes"));

    // Update ujian aktif
    $upd = $pdo->prepare("UPDATE exams SET current_token = ?, is_token_active = 1 WHERE id = ?");
    $upd->execute([$token, $examId]);

    // Masukkan ke riwayat exam_tokens
    $ins = $pdo->prepare("INSERT INTO exam_tokens (exam_id, token, generated_by, valid_until) VALUES (?, ?, ?, ?)");
    $ins->execute([$examId, $token, $operatorId, $validUntil]);

    set_flash('success', "Token baru berhasil dibuat dan dirilis: <strong class='text-lg font-mono tracking-widest'>$token</strong> (Berlaku $validMinutes menit)");
    redirect(BASE_URL . "/operator/token.php?exam_id=$examId");
}

// Ambil info ujian terpilih
$currExam = null;
foreach ($exams as $ex) {
    if ($ex['id'] == $selectedExamId) {
        $currExam = $ex;
        break;
    }
}

// Ambil riwayat token untuk ujian ini
$tokens = [];
if ($selectedExamId > 0) {
    $tStmt = $pdo->prepare("SELECT et.*, u.full_name as operator_name 
                            FROM exam_tokens et 
                            JOIN users u ON et.generated_by = u.id 
                            WHERE et.exam_id = ? 
                            ORDER BY et.id DESC LIMIT 10");
    $tStmt->execute([$selectedExamId]);
    $tokens = $tStmt->fetchAll();
}

$pageTitle = 'Generate & Rilis Token Ujian';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Generate & Rilis Token Ujian</h1>
            <p class="text-sm text-slate-500">Kendalikan kode token rahasia yang dimasukkan siswa sebelum memulai tes.</p>
        </div>

        <form method="GET" action="">
            <select name="exam_id" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= $ex['id'] ?>" <?= $selectedExamId == $ex['id'] ? 'selected' : '' ?>>
                        <?= e($ex['title']) ?> (<?= e($ex['session_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!$currExam): ?>
        <div class="bg-amber-50 rounded-2xl p-6 text-center text-amber-800 border border-amber-200">
            Tidak ada ujian aktif saat ini.
        </div>
    <?php else: ?>
        <!-- Big Proctor Projection Card -->
        <div class="bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 rounded-3xl p-8 sm:p-12 text-white shadow-2xl text-center border border-white/10 relative overflow-hidden">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-4 border border-emerald-500/30">
                <span class="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-ping"></span>
                Token Aktif Sesi Ujian: <?= e($currExam['session_name']) ?>
            </span>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-300 mb-2"><?= e($currExam['title']) ?></h2>
            <div class="text-xs text-indigo-300 mb-8"><?= e($currExam['subject_name']) ?> &bull; <?= e($currExam['room']) ?></div>

            <!-- Big Token Display -->
            <div class="inline-block bg-white/10 backdrop-blur-md px-10 py-6 rounded-3xl border border-white/20 shadow-2xl mb-8">
                <div class="text-xs font-bold uppercase tracking-widest text-indigo-200 mb-1">KODE TOKEN SISWA:</div>
                <div id="bigToken" class="font-mono text-5xl sm:text-7xl font-black tracking-widest text-amber-300 drop-shadow-md select-all cursor-pointer" onclick="copyToken('<?= e($currExam['current_token']) ?>')">
                    <?= e($currExam['current_token']) ?>
                </div>
            </div>

            <div class="flex items-center justify-center space-x-3">
                <button onclick="copyToken('<?= e($currExam['current_token']) ?>')" class="px-5 py-2.5 bg-white/15 hover:bg-white/25 border border-white/20 text-white font-bold rounded-xl text-xs transition flex items-center">
                    <i class="fa-solid fa-copy mr-2 text-indigo-300"></i> Salin Token
                </button>
                <button onclick="document.getElementById('modalGenToken').classList.remove('hidden')" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-amber-500/30 flex items-center">
                    <i class="fa-solid fa-arrows-rotate mr-2"></i> Rilis Token Baru Sekarang
                </button>
            </div>
        </div>

        <!-- Token History Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center">
                <i class="fa-solid fa-clock-rotate-left text-slate-400 mr-2"></i> Riwayat Token yang Pernah Dirilis
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                            <th class="py-3 px-4">Kode Token</th>
                            <th class="py-3 px-4">Operator Pembuat</th>
                            <th class="py-3 px-4">Waktu Dirilis</th>
                            <th class="py-3 px-4">Berlaku Sampai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($tokens)): ?>
                            <tr><td colspan="4" class="py-4 text-center text-slate-400">Belum ada riwayat token tersimpan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tokens as $t): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-indigo-700 text-sm tracking-wider">
                                        <?= e($t['token']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-700"><?= e($t['operator_name']) ?></td>
                                    <td class="py-3 px-4 text-slate-500"><?= date('H:i:s, d M Y', strtotime($t['created_at'])) ?></td>
                                    <td class="py-3 px-4 text-slate-500"><?= date('H:i:s, d M Y', strtotime($t['valid_until'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Generate Token -->
<div id="modalGenToken" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-slate-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-key text-amber-500 mr-2.5"></i> Generate Token Ujian
            </h3>
            <button onclick="document.getElementById('modalGenToken').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="generate_token">
            <input type="hidden" name="exam_id" value="<?= $selectedExamId ?>">

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Masa Berlaku Token</label>
                <select name="valid_minutes" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-xs font-semibold">
                    <option value="15">15 Menit</option>
                    <option value="30" selected>30 Menit</option>
                    <option value="60">1 Jam</option>
                    <option value="120">2 Jam</option>
                    <option value="360">6 Jam (Sepanjang Sesi)</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Custom Token (Opsional)</label>
                <input type="text" name="custom_token" placeholder="Kosongkan untuk otomatis 6 digit acak" maxlength="10" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-xs uppercase font-mono tracking-wider">
                <span class="text-[11px] text-slate-400 block mt-1">Jika dikosongkan, sistem akan mengacak 6 karakter alfanumerik secara otomatis.</span>
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalGenToken').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl transition shadow shadow-amber-200">Rilis Token</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyToken(tok) {
    navigator.clipboard.writeText(tok);
    Swal.fire({
        icon: 'success',
        title: 'Token Disalin!',
        text: 'Token ' + tok + ' berhasil disalin.',
        timer: 1500,
        showConfirmButton: false
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
