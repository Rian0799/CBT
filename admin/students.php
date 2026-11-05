<?php
// admin/students.php - Manajemen Siswa Peserta Ujian
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nisn = trim($_POST['nisn'] ?? '');
        $examNumber = trim($_POST['exam_number'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $gender = trim($_POST['gender'] ?? 'L');
        $password = trim($_POST['password'] ?? 'siswa123');

        if ($nisn && $examNumber && $fullName && $classId) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO students (nisn, exam_number, full_name, class_id, gender, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nisn, $examNumber, $fullName, $classId, $gender, $hash]);
                set_flash('success', "Data siswa <strong>" . e($fullName) . "</strong> berhasil ditambahkan.");
            } catch (PDOException $e) {
                set_flash('error', "Gagal: NISN atau Nomor Ujian sudah terdaftar.");
            }
        }
        redirect(BASE_URL . '/admin/students.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Data siswa berhasil dihapus.");
        redirect(BASE_URL . '/admin/students.php');
    }

    if ($action === 'reset_login') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Status login siswa berhasil di-reset (siswa dapat login kembali).");
        redirect(BASE_URL . '/admin/students.php');
    }

    if ($action === 'reset_all_logins') {
        $pdo->exec("UPDATE students SET is_logged_in = 0");
        set_flash('success', "Semua sesi login siswa berhasil di-reset secara massal.");
        redirect(BASE_URL . '/admin/students.php');
    }
}

// Filter Kelas
$filterClass = (int)($_GET['class_id'] ?? 0);
$whereClause = $filterClass > 0 ? "WHERE s.class_id = $filterClass" : "";

$students = $pdo->query("SELECT s.*, c.name as class_name 
                         FROM students s 
                         JOIN classes c ON s.class_id = c.id 
                         $whereClause 
                         ORDER BY c.name ASC, s.full_name ASC")->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

$pageTitle = 'Data Siswa Peserta Ujian';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Data Siswa Peserta Ujian</h1>
            <p class="text-sm text-slate-500">Kelola akun dan nomor peserta ujian untuk seluruh siswa.</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="<?= BASE_URL ?>/admin/student_import.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-200 transition flex items-center">
                <i class="fa-solid fa-file-excel mr-1.5"></i> Import Siswa (Excel)
            </a>
            <form method="POST" action="" onsubmit="return confirm('Reset semua status login siswa?');" class="inline">
                <input type="hidden" name="action" value="reset_all_logins">
                <button type="submit" class="px-3 py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold rounded-xl text-xs border border-amber-200 transition">
                    <i class="fa-solid fa-arrows-rotate mr-1.5"></i> Reset Semua Login
                </button>
            </form>
            <button onclick="document.getElementById('modalAddStudent').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition flex items-center">
                <i class="fa-solid fa-user-plus mr-1.5"></i> Tambah Siswa Baru
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-4 text-xs">
        <form method="GET" action="" class="flex items-center space-x-3">
            <label class="font-bold text-slate-700">Filter Kelas:</label>
            <select name="class_id" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs">
                <option value="0">Semua Kelas (<?= count($students) ?> Siswa)</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="text-slate-500 font-medium">
            Total Tampil: <strong class="text-slate-800"><?= count($students) ?></strong> Siswa
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3.5 px-4">Nama Lengkap & L/P</th>
                        <th class="py-3.5 px-4">NISN (ID Login)</th>
                        <th class="py-3.5 px-4">No. Peserta Ujian</th>
                        <th class="py-3.5 px-4">Kelas</th>
                        <th class="py-3.5 px-4">Status Login</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($students)): ?>
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada data siswa ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= e($s['full_name']) ?></div>
                                    <span class="text-[11px] text-slate-400"><?= $s['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-indigo-700"><?= e($s['nisn']) ?></td>
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-700"><?= e($s['exam_number']) ?></td>
                                <td class="py-3.5 px-4 font-semibold text-slate-700"><?= e($s['class_name']) ?></td>
                                <td class="py-3.5 px-4">
                                    <?php if ($s['is_logged_in']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Sedang Login
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                                            Offline
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex items-center space-x-1.5">
                                        <?php if ($s['is_logged_in']): ?>
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="action" value="reset_login">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Reset Sesi Login">
                                                    <i class="fa-solid fa-arrow-rotate-right"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="" onsubmit="return confirm('Hapus siswa ini?');" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus Siswa">
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

<!-- Modal Tambah Siswa -->
<div id="modalAddStudent" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-slate-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-user-plus text-indigo-600 mr-2.5"></i> Tambah Data Siswa Baru
            </h3>
            <button onclick="document.getElementById('modalAddStudent').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Nama Lengkap Siswa *</label>
                <input type="text" name="full_name" required placeholder="Contoh: Muhammad Rizky" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">NISN (Untuk Login) *</label>
                    <input type="text" name="nisn" required placeholder="10 digit NISN" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">No. Peserta Ujian *</label>
                    <input type="text" name="exam_number" required placeholder="UJN-2026-xxx" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Kelas *</label>
                    <select name="class_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Jenis Kelamin</label>
                    <select name="gender" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Password Login Siswa</label>
                <input type="password" name="password" value="siswa123" placeholder="Default: siswa123" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalAddStudent').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow shadow-indigo-200">Simpan Siswa</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
