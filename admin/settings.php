<?php
// admin/settings.php - Pengaturan Aplikasi CBT
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'school_name' => trim($_POST['school_name'] ?? ''),
        'app_title' => trim($_POST['app_title'] ?? ''),
        'institution_level' => trim($_POST['institution_level'] ?? 'all'),
        'academic_year' => trim($_POST['academic_year'] ?? ''),
        'semester' => trim($_POST['semester'] ?? ''),
        'anti_cheat_strictness' => trim($_POST['anti_cheat_strictness'] ?? 'medium'),
        'max_tab_violations' => (int)($_POST['max_tab_violations'] ?? 3),
        'token_auto_refresh_minutes' => (int)($_POST['token_auto_refresh_minutes'] ?? 15),
    ];

    foreach ($settings as $key => $val) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, (string)$val, (string)$val]);
    }

    set_flash('success', 'Pengaturan aplikasi CBT berhasil disimpan.');
    redirect(BASE_URL . '/admin/settings.php');
}

$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);
$institutionLevel = get_setting('institution_level', 'all');
$academicYear = get_setting('academic_year', '2025/2026');
$semester = get_setting('semester', 'Genap');
$strictness = get_setting('anti_cheat_strictness', 'medium');
$maxViolations = get_setting('max_tab_violations', '3');
$tokenMinutes = get_setting('token_auto_refresh_minutes', '15');

$pageTitle = 'Pengaturan Aplikasi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Pengaturan Aplikasi</h1>
        <p class="text-sm text-slate-500">Konfigurasi identitas lembaga sekolah, aturan anti-curang, dan token CBT.</p>
    </div>

    <form method="POST" action="" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-6 text-xs">
        <!-- Identitas Sekolah -->
        <div>
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center text-indigo-700">
                <i class="fa-solid fa-school mr-2"></i> Identitas Lembaga & Aplikasi
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Lembaga / Sekolah *</label>
                    <input type="text" name="school_name" value="<?= e($schoolName) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-semibold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Judul / Brand Aplikasi CBT *</label>
                    <input type="text" name="app_title" value="<?= e($appTitle) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-semibold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Jenjang Pendidikan Lembaga</label>
                    <select name="institution_level" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-semibold">
                        <option value="all" <?= $institutionLevel === 'all' ? 'selected' : '' ?>>Semua Jenjang / Terpadu (SD/MI, SMP/MTs, SMA/SMK/MA)</option>
                        <option value="sd_mi" <?= $institutionLevel === 'sd_mi' ? 'selected' : '' ?>>SD / MI (Sekolah Dasar / Madrasah Ibtidaiyah)</option>
                        <option value="smp_mts" <?= $institutionLevel === 'smp_mts' ? 'selected' : '' ?>>SMP / MTs (Sekolah Menengah Pertama / Tsanawiyah)</option>
                        <option value="sma_ma" <?= $institutionLevel === 'sma_ma' ? 'selected' : '' ?>>SMA / MA (Sekolah Menengah Atas / Aliyah)</option>
                        <option value="smk" <?= $institutionLevel === 'smk' ? 'selected' : '' ?>>SMK (Sekolah Menengah Kejuruan)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tahun Ajaran Aktif</label>
                    <input type="text" name="academic_year" value="<?= e($academicYear) ?>" placeholder="2025/2026" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Semester</label>
                    <select name="semester" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-6"></div>

        <!-- Aturan Anti-Curang (Lockdown) -->
        <div>
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center text-indigo-700">
                <i class="fa-solid fa-shield-halved mr-2"></i> Konfigurasi Pengamanan & Anti-Curang
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tingkat Ketat Anti-Curang</label>
                    <select name="anti_cheat_strictness" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                        <option value="low" <?= $strictness === 'low' ? 'selected' : '' ?>>Rendah (Hanya Peringatan Teks di Layar)</option>
                        <option value="medium" <?= $strictness === 'medium' ? 'selected' : '' ?>>Sedang (Peringatan & Catat Log ke Operator)</option>
                        <option value="high" <?= $strictness === 'high' ? 'selected' : '' ?>>Ketat (Kunci Ujian Otomatis Jika Melebihi Batas Pelanggaran)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Batas Maksimal Pindah Tab / Layar Sebelum Terkunci</label>
                    <input type="number" name="max_tab_violations" value="<?= e($maxViolations) ?>" min="1" max="10" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Interval Refresh Token Ujian (Menit)</label>
                    <input type="number" name="token_auto_refresh_minutes" value="<?= e($tokenMinutes) ?>" min="5" max="120" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs font-bold">
                </div>
            </div>
        </div>

        <div class="pt-4 flex justify-end">
            <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-lg shadow-indigo-200 flex items-center">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Simpan Semua Pengaturan
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
