<?php
// admin/classes.php - Manajemen Kelas & Rombel
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $level = trim($_POST['level'] ?? '10');
        $major = trim($_POST['major'] ?? 'Umum');

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO classes (name, level, major) VALUES (?, ?, ?)");
            $stmt->execute([$name, $level, $major]);
            set_flash('success', "Kelas <strong>" . e($name) . "</strong> berhasil ditambahkan.");
        }
        redirect(BASE_URL . '/admin/classes.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Kelas berhasil dihapus.");
        redirect(BASE_URL . '/admin/classes.php');
    }
}

// Filter Jenjang
$filterJenjang = $_GET['jenjang'] ?? 'all';
$whereClause = "";
if ($filterJenjang === 'sd_mi') {
    $whereClause = "WHERE CAST(c.level AS UNSIGNED) BETWEEN 1 AND 6";
} elseif ($filterJenjang === 'smp_mts') {
    $whereClause = "WHERE CAST(c.level AS UNSIGNED) BETWEEN 7 AND 9";
} elseif ($filterJenjang === 'sma_smk_ma') {
    $whereClause = "WHERE CAST(c.level AS UNSIGNED) >= 10 OR c.level IN ('X', 'XI', 'XII', 'XIII')";
}

// Ambil daftar kelas beserta jumlah siswanya
$classes = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count 
                        FROM classes c 
                        $whereClause
                        ORDER BY CAST(c.level AS UNSIGNED) ASC, c.name ASC")->fetchAll();

$pageTitle = 'Manajemen Kelas & Rombel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Manajemen Kelas & Rombel</h1>
            <p class="text-sm text-slate-500">Kelola daftar rombongan belajar dan tingkat pendidikan.</p>
        </div>
        <button onclick="document.getElementById('modalAddClass').classList.remove('hidden')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Kelas Baru
        </button>
    </div>

    <!-- Filter Jenjang Tabs -->
    <div class="flex items-center space-x-2 text-xs overflow-x-auto pb-1">
        <a href="?jenjang=all" class="px-3 py-1.5 rounded-xl font-bold transition <?= $filterJenjang === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            Semua Jenjang
        </a>
        <a href="?jenjang=sd_mi" class="px-3 py-1.5 rounded-xl font-bold transition <?= $filterJenjang === 'sd_mi' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-child mr-1"></i> SD / MI (Kelas 1 - 6)
        </a>
        <a href="?jenjang=smp_mts" class="px-3 py-1.5 rounded-xl font-bold transition <?= $filterJenjang === 'smp_mts' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-school mr-1"></i> SMP / MTs (Kelas 7 - 9)
        </a>
        <a href="?jenjang=sma_smk_ma" class="px-3 py-1.5 rounded-xl font-bold transition <?= $filterJenjang === 'sma_smk_ma' ? 'bg-purple-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-graduation-cap mr-1"></i> SMA / SMK / MA (Kelas 10 - 13)
        </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                    <th class="py-3.5 px-4">Nama Kelas</th>
                    <th class="py-3.5 px-4">Jenjang & Tingkat</th>
                    <th class="py-3.5 px-4">Jurusan / Peminatan</th>
                    <th class="py-3.5 px-4">Jumlah Siswa</th>
                    <th class="py-3.5 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($classes)): ?>
                    <tr><td colspan="5" class="py-8 text-center text-slate-400">Belum ada kelas yang terdaftar pada filter ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($classes as $c): 
                        $lvlNum = (int)$c['level'];
                        $jenjangBadge = '';
                        if ($lvlNum >= 1 && $lvlNum <= 6) {
                            $jenjangBadge = '<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200"><i class="fa-solid fa-child mr-1"></i> SD/MI (Kelas ' . $c['level'] . ')</span>';
                        } elseif ($lvlNum >= 7 && $lvlNum <= 9) {
                            $jenjangBadge = '<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800 border border-blue-200"><i class="fa-solid fa-school mr-1"></i> SMP/MTs (Kelas ' . $c['level'] . ')</span>';
                        } else {
                            $jenjangBadge = '<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800 border border-purple-200"><i class="fa-solid fa-graduation-cap mr-1"></i> SMA/SMK/MA (Kelas ' . $c['level'] . ')</span>';
                        }
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-800 text-sm"><?= e($c['name']) ?></td>
                            <td class="py-3.5 px-4">
                                <?= $jenjangBadge ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600"><?= e($c['major']) ?></td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-slate-800"><?= $c['student_count'] ?></span> Siswa
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <form method="POST" action="" onsubmit="return confirm('Hapus kelas ini? Semua siswa di kelas ini juga akan terhapus!');" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus Kelas">
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

<!-- Modal Tambah Kelas -->
<div id="modalAddClass" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-slate-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-chalkboard text-indigo-600 mr-2.5"></i> Tambah Kelas Baru
            </h3>
            <button onclick="document.getElementById('modalAddClass').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Nama Kelas / Rombel *</label>
                <input type="text" name="name" required placeholder="Contoh: 1A, V MI, 7B, IX MTs, X RPL 1, XII MIPA" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Tingkat Pendidikan *</label>
                    <select name="level" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <optgroup label="SD / MI (Sekolah Dasar / Madrasah)">
                            <option value="1">Kelas 1 (Satu)</option>
                            <option value="2">Kelas 2 (Dua)</option>
                            <option value="3">Kelas 3 (Tiga)</option>
                            <option value="4">Kelas 4 (Empat)</option>
                            <option value="5">Kelas 5 (Lima)</option>
                            <option value="6">Kelas 6 (Enam)</option>
                        </optgroup>
                        <optgroup label="SMP / MTs (Menengah Pertama)">
                            <option value="7">Kelas 7 / VII</option>
                            <option value="8">Kelas 8 / VIII</option>
                            <option value="9">Kelas 9 / IX</option>
                        </optgroup>
                        <optgroup label="SMA / SMK / MA (Menengah Atas/Kejuruan)">
                            <option value="10" selected>Kelas 10 / X</option>
                            <option value="11">Kelas 11 / XI</option>
                            <option value="12">Kelas 12 / XII</option>
                            <option value="13">Kelas 13 / XIII (SMK 4 Tahun)</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Jurusan / Peminatan</label>
                    <input type="text" name="major" placeholder="Umum, Tematik, MIPA, RPL, dll" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalAddClass').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow shadow-indigo-200">Simpan Kelas</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
