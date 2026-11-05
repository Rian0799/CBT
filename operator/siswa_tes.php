<?php
// operator/siswa_tes.php - Cek Data Siswa Yang Akan Tes & Cetak Kartu Peserta
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole(['operator', 'admin']);
$pdo = Database::getConnection();

// Filter Kelas
$filterClass = (int)($_GET['class_id'] ?? 0);
$where = $filterClass > 0 ? "WHERE s.class_id = $filterClass" : "";

$students = $pdo->query("SELECT s.*, c.name as class_name 
                         FROM students s 
                         JOIN classes c ON s.class_id = c.id 
                         $where 
                         ORDER BY c.name ASC, s.full_name ASC")->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

// Ujian aktif untuk info sesi
$activeExam = $pdo->query("SELECT * FROM exams WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();

$schoolName = get_setting('school_name', APP_INSTITUTION);

$pageTitle = 'Cek Data Siswa Yang Akan Tes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Cek Data Siswa Yang Akan Tes</h1>
            <p class="text-sm text-slate-500">Verifikasi kelengkapan identitas peserta, sesi, ruang, dan cetak kartu ujian.</p>
        </div>

        <div class="flex items-center space-x-2">
            <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-200 flex items-center">
                <i class="fa-solid fa-id-card mr-1.5"></i> Cetak Kartu Peserta Ujian
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
        <form method="GET" action="" class="flex items-center space-x-3">
            <label class="font-bold text-slate-700">Filter Kelas:</label>
            <select name="class_id" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold">
                <option value="0">Semua Kelas</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="text-slate-500 font-medium">
            Terverifikasi Siap Tes: <strong class="text-slate-900"><?= count($students) ?> Siswa</strong>
            <?php if ($activeExam): ?>
                &bull; Ruang: <span class="text-indigo-600 font-bold"><?= e($activeExam['room']) ?></span> (<?= e($activeExam['session_name']) ?>)
            <?php endif; ?>
        </div>
    </div>

    <!-- Table Siswa Tes -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3.5 px-4">No. Peserta</th>
                        <th class="py-3.5 px-4">Nama Lengkap Siswa</th>
                        <th class="py-3.5 px-4">NISN (Username)</th>
                        <th class="py-3.5 px-4">Kelas</th>
                        <th class="py-3.5 px-4">Sesi & Ruang</th>
                        <th class="py-3.5 px-4">Status Siswa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($students)): ?>
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">Tidak ada data siswa ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900 text-sm">
                                    <?= e($s['exam_number']) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= e($s['full_name']) ?></div>
                                    <span class="text-[11px] text-slate-400">Jenis Kelamin: <?= $s['gender'] === 'L' ? 'L' : 'P' ?></span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-indigo-700">
                                    <?= e($s['nisn']) ?>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-700">
                                    <?= e($s['class_name']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <div><?= e($activeExam['session_name'] ?? 'Sesi 1') ?></div>
                                    <div class="text-[11px] text-slate-400"><?= e($activeExam['room'] ?? 'Lab Komputer') ?></div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-circle-check mr-1 text-emerald-500"></i> Siap Ikut Tes
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section Cetak Kartu Peserta (Khusus Print Preview) -->
    <div class="hidden print:block space-y-6 pt-6">
        <h2 class="text-xl font-bold text-center mb-6">KARTU PESERTA UJIAN BERBASIS KOMPUTER (CBT)</h2>
        <div class="grid grid-cols-2 gap-4">
            <?php foreach ($students as $s): ?>
                <div class="border-2 border-slate-800 rounded-xl p-4 text-xs space-y-2 page-break-inside-avoid">
                    <div class="border-b border-slate-300 pb-2 flex items-center justify-between">
                        <div>
                            <div class="font-extrabold text-sm uppercase"><?= e($schoolName) ?></div>
                            <div class="text-[10px] text-slate-500">KARTU PESERTA ASESMEN STANDAR</div>
                        </div>
                        <i class="fa-solid fa-graduation-cap text-xl text-slate-700"></i>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-1">
                        <div class="col-span-2 space-y-1">
                            <div>Nama: <strong><?= e($s['full_name']) ?></strong></div>
                            <div>No. Ujian: <strong class="font-mono"><?= e($s['exam_number']) ?></strong></div>
                            <div>NISN / ID: <strong class="font-mono"><?= e($s['nisn']) ?></strong></div>
                            <div>Kelas: <?= e($s['class_name']) ?></div>
                            <div>Password: <span class="font-mono">siswa123</span></div>
                        </div>
                        <div class="border border-slate-300 rounded flex items-center justify-center text-[10px] text-slate-400 h-24">
                            FOTO 3x4
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
@media print {
    aside, header, button, form, .no-print { display: none !important; }
    main { padding: 0 !important; background: white !important; }
    .page-break-inside-avoid { break-inside: avoid; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
