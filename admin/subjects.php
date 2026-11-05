<?php
// admin/subjects.php - Manajemen Mata Pelajaran
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');

        if ($code && $name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (code, name) VALUES (?, ?)");
                $stmt->execute([$code, $name]);
                set_flash('success', "Mata pelajaran <strong>" . e($name) . "</strong> ($code) berhasil ditambahkan.");
            } catch (PDOException $e) {
                set_flash('error', "Kode mata pelajaran sudah digunakan!");
            }
        }
        redirect(BASE_URL . '/admin/subjects.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Mata pelajaran berhasil dihapus.");
        redirect(BASE_URL . '/admin/subjects.php');
    }
}

// Ambil daftar mapel
$subjects = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM question_banks qb WHERE qb.subject_id = s.id) as bank_count 
                         FROM subjects s 
                         ORDER BY s.name ASC")->fetchAll();

$pageTitle = 'Manajemen Mata Pelajaran';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Mata Pelajaran</h1>
            <p class="text-sm text-slate-500">Kelola kurikulum dan mata pelajaran untuk bank soal dan ujian.</p>
        </div>
        <button onclick="document.getElementById('modalAddSubject').classList.remove('hidden')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Mapel Baru
        </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                    <th class="py-3.5 px-4">Kode Mapel</th>
                    <th class="py-3.5 px-4">Nama Mata Pelajaran</th>
                    <th class="py-3.5 px-4">Bank Soal Terkait</th>
                    <th class="py-3.5 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($subjects)): ?>
                    <tr><td colspan="4" class="py-8 text-center text-slate-400">Belum ada mata pelajaran terdaftar.</td></tr>
                <?php else: ?>
                    <?php foreach ($subjects as $s): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-indigo-700"><?= e($s['code']) ?></td>
                            <td class="py-3.5 px-4 font-bold text-slate-800 text-sm"><?= e($s['name']) ?></td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    <?= $s['bank_count'] ?> Bank Soal
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <form method="POST" action="" onsubmit="return confirm('Hapus mata pelajaran ini?');" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus Mapel">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Mapel -->
<div id="modalAddSubject" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-slate-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-book-bookmark text-indigo-600 mr-2.5"></i> Tambah Mata Pelajaran Baru
            </h3>
            <button onclick="document.getElementById('modalAddSubject').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Kode Mapel *</label>
                <input type="text" name="code" required placeholder="Contoh: BIND-01, MAT-IPA" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs uppercase font-mono">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Nama Mata Pelajaran *</label>
                <input type="text" name="name" required placeholder="Contoh: Bahasa Indonesia, Matematika Terapan" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalAddSubject').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow shadow-indigo-200">Simpan Mapel</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
