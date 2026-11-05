<?php
// guru/bank_soal.php - Manajemen Bank Soal & Setting Komposisi Bobot Nilai
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole(['guru', 'admin']);
$pdo = Database::getConnection();
$teacherId = Auth::user()['id'];
$isAdmin = Auth::role() === 'admin';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Buat Bank Soal Baru
    if ($action === 'create') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $title = trim($_POST['title'] ?? '');
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $kkm = (float)($_POST['kkm'] ?? 75.00);
        $classLevel = trim($_POST['class_level'] ?? 'Semua');

        if ($code && $title && $subjectId) {
            try {
                $stmt = $pdo->prepare("INSERT INTO question_banks (code, title, subject_id, teacher_id, class_level, kkm) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $title, $subjectId, $teacherId, $classLevel, $kkm]);
                $newId = $pdo->lastInsertId();
                set_flash('success', "Bank soal <strong>" . e($title) . "</strong> berhasil dibuat. Silakan atur komposisi soal dan bobot nilai di bawah sebelum mengunggah soal.");
            } catch (PDOException $e) {
                set_flash('error', "Kode bank soal sudah digunakan!");
            }
        }
        redirect(BASE_URL . '/guru/bank_soal.php');
    }

    // Simpan Pengaturan Komposisi & Bobot Nilai Soal
    if ($action === 'save_settings') {
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

        set_flash('success', "Pengaturan komposisi & bobot nilai berhasil disimpan! Target: {$settings['total_questions']} butir soal (Total Bobot: {$settings['total_weight']}). Fitur upload Word sekarang aktif.");
        redirect(BASE_URL . '/guru/bank_soal.php');
    }

    // Hapus Bank Soal
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $chk = $pdo->prepare("SELECT teacher_id FROM question_banks WHERE id = ?");
        $chk->execute([$id]);
        $owner = $chk->fetchColumn();

        if ($isAdmin || $owner == $teacherId) {
            $stmt = $pdo->prepare("DELETE FROM question_banks WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', "Bank soal dan seluruh butir soal di dalamnya berhasil dihapus.");
        } else {
            set_flash('error', "Anda tidak memiliki hak untuk menghapus bank soal ini.");
        }
        redirect(BASE_URL . '/guru/bank_soal.php');
    }
}

// Ambil daftar bank soal
$where = $isAdmin ? "" : "WHERE qb.teacher_id = $teacherId";
$banks = $pdo->query("SELECT qb.*, s.name as subject_name, u.full_name as teacher_name, 
                             (SELECT COUNT(*) FROM questions q WHERE q.bank_id = qb.id) as question_count 
                      FROM question_banks qb 
                      JOIN subjects s ON qb.subject_id = s.id 
                      JOIN users u ON qb.teacher_id = u.id 
                      $where 
                      ORDER BY qb.id DESC")->fetchAll();

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

$pageTitle = 'Bank Soal Ujian';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Bank Soal Ujian</h1>
            <p class="text-sm text-slate-500">Kelola paket bank soal berbasis 6 jenis tipe soal asesmen, komposisi dan bobot nilai.</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="<?= BASE_URL ?>/guru/soal_import.php?download_template=docx" class="px-3.5 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-xl text-xs transition flex items-center border border-blue-200" title="Unduh Template Word">
                <i class="fa-solid fa-file-word mr-1.5 text-blue-600"></i> Template Word (.docx)
            </a>
            <a href="<?= BASE_URL ?>/guru/soal_import.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition flex items-center">
                <i class="fa-solid fa-file-arrow-up mr-2 text-indigo-600"></i> Upload Soal Word
            </a>
            <button onclick="document.getElementById('modalAddBank').classList.remove('hidden')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition flex items-center">
                <i class="fa-solid fa-plus mr-2"></i> Buat Bank Soal Baru
            </button>
        </div>
    </div>

    <!-- Alert Edukasi Syarat Upload -->
    <div class="bg-gradient-to-r from-indigo-50 via-slate-50 to-blue-50 border border-indigo-100 rounded-3xl p-5 flex items-start space-x-4">
        <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 text-lg shadow-sm">
            <i class="fa-solid fa-info"></i>
        </div>
        <div class="text-xs text-slate-600 leading-relaxed">
            <strong class="text-indigo-950 font-extrabold text-sm block mb-0.5">Ketentuan Import Soal dari Microsoft Word:</strong>
            Sebelum mengunggah naskah soal Word, pastikan Anda telah menekan tombol <strong class="text-indigo-700 font-bold">⚙️ Setting Bobot & Komposisi</strong> pada bank soal yang dituju untuk menentukan jenis soal apa saja yang akan ditampilkan dan bobot nilai per butirnya. Sistem akan mengunci formulir unggah jika bank soal belum dikonfigurasi.
        </div>
    </div>

    <!-- Bank Soal Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($banks)): ?>
            <div class="col-span-full bg-white rounded-3xl p-12 text-center border border-slate-200">
                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-700">Belum ada Bank Soal</h3>
                <p class="text-sm text-slate-400 mt-1 mb-6">Mulai dengan membuat paket bank soal baru untuk mata pelajaran Anda.</p>
                <button onclick="document.getElementById('modalAddBank').classList.remove('hidden')" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs">
                    <i class="fa-solid fa-plus mr-1"></i> Buat Bank Soal Pertama
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($banks as $b): 
                $settings = json_decode($b['question_settings'] ?? 'null', true);
                $isConfigured = !empty($settings['is_configured']);
            ?>
                <div class="bg-white rounded-3xl border <?= $isConfigured ? 'border-slate-200' : 'border-amber-300 ring-2 ring-amber-100' ?> shadow-sm hover:shadow-md transition p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-mono font-bold text-xs">
                                <?= e($b['code']) ?>
                            </span>
                            <span class="text-xs font-bold text-slate-400">
                                KKM: <strong class="text-slate-800"><?= $b['kkm'] ?></strong>
                            </span>
                        </div>

                        <h3 class="text-base font-bold text-slate-900 mb-1 leading-snug">
                            <?= e($b['title']) ?>
                        </h3>
                        <p class="text-xs text-indigo-600 font-semibold mb-3">
                            <i class="fa-solid fa-book mr-1"></i> <?= e($b['subject_name']) ?> &bull; Tingkat <?= e($b['class_level']) ?>
                        </p>

                        <!-- STATUS BOBOT & KOMPOSISI -->
                        <?php if ($isConfigured): ?>
                            <div class="mb-3 p-3 rounded-2xl bg-emerald-50/80 border border-emerald-200">
                                <div class="flex items-center justify-between text-xs font-bold text-emerald-800 mb-1">
                                    <span class="flex items-center">
                                        <i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> Bobot Terkonfigurasi
                                    </span>
                                    <span class="bg-emerald-200/80 text-emerald-900 px-2 py-0.5 rounded-md text-[10px] font-mono">
                                        Total: <?= $settings['total_weight'] ?> Poin
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-600 leading-tight">
                                    Target: <strong class="text-slate-800"><?= $settings['total_questions'] ?> Soal</strong> &bull;
                                    <?php 
                                    $activePills = [];
                                    $typeNameMap = ['pilgan' => 'Pilgan', 'kompleks' => 'Kompleks', 'jodohkan' => 'Jodohkan', 'benarsalah' => 'B/S', 'isian' => 'Isian', 'uraian' => 'Uraian'];
                                    foreach ($typeNameMap as $k => $label) {
                                        if (!empty($settings[$k]['active']) && $settings[$k]['count'] > 0) {
                                            $activePills[] = $label . ': ' . $settings[$k]['count'] . ' (@' . $settings[$k]['weight'] . ')';
                                        }
                                    }
                                    echo e(implode(', ', $activePills));
                                    ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-3 p-3 rounded-2xl bg-amber-50 border border-amber-200">
                                <div class="flex items-center justify-between text-xs font-bold text-amber-900 mb-1">
                                    <span class="flex items-center">
                                        <i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1.5"></i> Belum Setting Bobot
                                    </span>
                                    <span class="bg-amber-200 text-amber-900 px-2 py-0.5 rounded-md text-[10px] font-bold">
                                        Upload Terkunci
                                    </span>
                                </div>
                                <p class="text-[11px] text-amber-800 leading-tight">
                                    Atur jenis soal & bobot nilai terlebih dahulu sebelum mengunggah naskah Word.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100 flex items-center justify-between text-xs mb-3">
                            <div class="text-slate-500">Soal di Database:</div>
                            <div class="font-extrabold text-slate-800 text-sm flex items-center">
                                <i class="fa-solid fa-circle-question text-indigo-500 mr-1.5"></i>
                                <?= $b['question_count'] ?> Soal
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                            <div class="text-[11px] text-slate-400 mb-3 flex items-center">
                                <i class="fa-solid fa-chalkboard-user mr-1.5"></i> Guru: <?= e($b['teacher_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <!-- Button Setting Komposisi & Bobot -->
                        <button type="button" onclick='openSettingModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)' class="w-full py-2 px-3 rounded-xl <?= $isConfigured ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-extrabold shadow-md shadow-amber-200' ?> font-bold text-xs transition flex items-center justify-center">
                            <i class="fa-solid fa-sliders mr-1.5 <?= $isConfigured ? 'text-indigo-600' : 'text-white' ?>"></i> 
                            <?= $isConfigured ? 'Ubah Bobot & Komposisi' : 'Setting Bobot & Komposisi' ?>
                        </button>

                        <div class="flex items-center space-x-2">
                            <!-- Link Upload Word -->
                            <a href="<?= BASE_URL ?>/guru/soal_import.php?bank_id=<?= $b['id'] ?>" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-xl text-xs transition border border-blue-200" title="Upload Naskah Word">
                                <i class="fa-solid fa-file-word mr-1.5 text-blue-600"></i> Upload Word
                            </a>

                            <!-- Link Visual Editor -->
                            <a href="<?= BASE_URL ?>/guru/soal_editor.php?bank_id=<?= $b['id'] ?>" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition shadow-sm" title="Buka Editor Soal">
                                <i class="fa-solid fa-pen-to-square mr-1.5"></i> Kelola (<?= $b['question_count'] ?>)
                            </a>

                            <!-- Delete Bank -->
                            <form method="POST" action="" onsubmit="return confirm('Hapus bank soal ini beserta seluruh butir soal di dalamnya?');" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                <button type="submit" class="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Hapus Bank Soal">
                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Bank Soal -->
<div id="modalAddBank" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-slate-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-boxes-stacked text-indigo-600 mr-2.5"></i> Buat Bank Soal Baru
            </h3>
            <button onclick="document.getElementById('modalAddBank').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Kode Bank Soal *</label>
                    <input type="text" name="code" required placeholder="Contoh: BANK-MAT-10" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs uppercase font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Tingkat Kelas</label>
                    <select name="class_level" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <option value="Semua">Semua Tingkat (Umum)</option>
                        <optgroup label="Jenjang SD / MI">
                            <option value="1">Kelas 1 SD/MI</option>
                            <option value="2">Kelas 2 SD/MI</option>
                            <option value="3">Kelas 3 SD/MI</option>
                            <option value="4">Kelas 4 SD/MI</option>
                            <option value="5">Kelas 5 SD/MI</option>
                            <option value="6">Kelas 6 SD/MI</option>
                        </optgroup>
                        <optgroup label="Jenjang SMP / MTs">
                            <option value="7">Kelas 7 SMP/MTs</option>
                            <option value="8">Kelas 8 SMP/MTs</option>
                            <option value="9">Kelas 9 SMP/MTs</option>
                        </optgroup>
                        <optgroup label="Jenjang SMA / SMK / MA">
                            <option value="10">Kelas 10 SMA/SMK/MA</option>
                            <option value="11">Kelas 11 SMA/SMK/MA</option>
                            <option value="12">Kelas 12 SMA/SMK/MA</option>
                            <option value="13">Kelas 13 (SMK 4 Tahun)</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Judul Bank Soal *</label>
                <input type="text" name="title" required placeholder="Contoh: Soal Asesmen Standar Pemrograman Web" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-semibold">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Mata Pelajaran *</label>
                    <select name="subject_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">KKM Kelulusan</label>
                    <input type="number" name="kkm" value="75.00" step="0.5" min="0" max="100" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                </div>
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalAddBank').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow shadow-indigo-200">Simpan Bank Soal</button>
            </div>
        </form>
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
                <p class="text-xs text-indigo-600 font-bold mt-0.5" id="modalBankTitle">Nama Bank Soal</p>
                <p class="text-[11px] text-slate-400">Tentukan jenis soal apa saja yang mau ditampilkan dan bobot nilai per butir soal.</p>
            </div>
            <button onclick="closeSettingModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="bank_id" id="settingBankId" value="0">

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
                        ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-3 text-center">
                                    <input type="checkbox" name="<?= $k ?>_active" id="chk_<?= $k ?>" value="1" checked onchange="recalculateTotals()" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                </td>
                                <td class="py-3 px-3 font-bold text-slate-800">
                                    <label for="chk_<?= $k ?>" class="cursor-pointer"><?= $info['name'] ?></label>
                                </td>
                                <td class="py-3 px-3">
                                    <input type="number" name="<?= $k ?>_count" id="count_<?= $k ?>" value="<?= $info['def_count'] ?>" min="0" max="100" oninput="recalculateTotals()" class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 font-bold text-xs text-center focus:ring-2 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 px-3">
                                    <input type="number" step="0.5" name="<?= $k ?>_weight" id="weight_<?= $k ?>" value="<?= $info['def_weight'] ?>" min="0" max="100" oninput="recalculateTotals()" class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 font-bold text-xs text-center focus:ring-2 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-800" id="subtotal_<?= $k ?>">
                                    <?= round($info['def_count'] * $info['def_weight'], 2) ?>
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
                <i class="fa-solid fa-circle-info text-indigo-600 text-sm flex-shrink-0"></i>
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
function openSettingModal(bank) {
    document.getElementById('modalSettingBobot').classList.remove('hidden');
    document.getElementById('settingBankId').value = bank.id;
    document.getElementById('modalBankTitle').innerText = bank.title + ' [' + bank.code + '] - ' + bank.subject_name;

    let settings = null;
    try {
        if (bank.question_settings) {
            settings = typeof bank.question_settings === 'string' ? JSON.parse(bank.question_settings) : bank.question_settings;
        }
    } catch(e) {}

    const defaultValues = {
        'pilgan': { count: 20, weight: 2.5, active: 1 },
        'kompleks': { count: 5, weight: 4.0, active: 1 },
        'jodohkan': { count: 5, weight: 4.0, active: 1 },
        'benarsalah': { count: 5, weight: 2.0, active: 1 },
        'isian': { count: 5, weight: 2.0, active: 1 },
        'uraian': { count: 2, weight: 10.0, active: 1 }
    };

    const types = ['pilgan', 'kompleks', 'jodohkan', 'benarsalah', 'isian', 'uraian'];
    types.forEach(t => {
        const chk = document.getElementById('chk_' + t);
        const countInput = document.getElementById('count_' + t);
        const weightInput = document.getElementById('weight_' + t);

        let active = 1;
        let count = defaultValues[t].count;
        let weight = defaultValues[t].weight;

        if (settings && settings[t]) {
            active = settings[t].active ? 1 : 0;
            count = settings[t].count !== undefined ? settings[t].count : defaultValues[t].count;
            weight = settings[t].weight !== undefined ? settings[t].weight : defaultValues[t].weight;
        }

        if (chk) chk.checked = !!active;
        if (countInput) countInput.value = count;
        if (weightInput) weightInput.value = weight;
    });

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>