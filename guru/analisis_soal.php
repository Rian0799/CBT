<?php
// guru/analisis_soal.php - Analisis Butir Soal (Tingkat Kesukaran, Daya Pembeda, Pengecoh)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/AnalysisService.php';

Auth::requireRole(['guru', 'admin']);
$pdo = Database::getConnection();
$teacherId = Auth::user()['id'];
$isAdmin = Auth::role() === 'admin';

// Daftar ujian yang bisa dianalisis
$where = $isAdmin ? "" : "WHERE qb.teacher_id = $teacherId";
$exams = $pdo->query("SELECT e.id, e.title, qb.title as bank_title, s.name as subject_name 
                      FROM exams e 
                      JOIN question_banks qb ON e.bank_id = qb.id 
                      JOIN subjects s ON qb.subject_id = s.id 
                      $where 
                      ORDER BY e.id DESC")->fetchAll();

$selectedExamId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$analysisData = $selectedExamId > 0 ? AnalysisService::analyzeExam($selectedExamId) : [];

$pageTitle = 'Analisis Butir Soal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Top Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Analisis Butir Soal</h1>
            <p class="text-sm text-slate-500">Evaluasi psikometri: Tingkat Kesukaran ($P$), Daya Pembeda ($D$), dan Efektivitas Pengecoh.</p>
        </div>

        <form method="GET" action="" class="flex items-center space-x-2">
            <label class="text-xs font-bold text-slate-700 whitespace-nowrap">Pilih Ujian:</label>
            <select name="exam_id" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= $ex['id'] ?>" <?= $selectedExamId == $ex['id'] ? 'selected' : '' ?>>
                        <?= e($ex['title']) ?> (<?= e($ex['subject_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="window.print()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                <i class="fa-solid fa-print mr-1"></i> Cetak Laporan
            </button>
        </form>
    </div>

    <?php if (empty($analysisData) || empty($analysisData['questions'])): ?>
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200">
            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Belum Ada Data Peserta yang Menyelesaikan Ujian</h3>
            <p class="text-sm text-slate-400 mt-1 max-w-md mx-auto">
                Analisis butir soal membutuhkan setidaknya peserta yang telah menyelesaikan ujian agar rumus daya pembeda dan tingkat kesukaran dapat dikalkulasikan secara akurat.
            </p>
        </div>
    <?php else: 
        $summary = $analysisData['summary'];
        $qList = $analysisData['questions'];
        $totalP = $analysisData['total_participants'];

        // Hitung aggregasi untuk chart
        $diffMudah = count(array_filter($qList, fn($q) => $q['difficulty_category'] === 'Mudah'));
        $diffSedang = count(array_filter($qList, fn($q) => $q['difficulty_category'] === 'Sedang'));
        $diffSukar = count(array_filter($qList, fn($q) => $q['difficulty_category'] === 'Sukar'));

        $discSangatBaik = count(array_filter($qList, fn($q) => $q['discrimination_category'] === 'Sangat Baik'));
        $discBaik = count(array_filter($qList, fn($q) => $q['discrimination_category'] === 'Baik'));
        $discCukup = count(array_filter($qList, fn($q) => $q['discrimination_category'] === 'Cukup'));
        $discBuruk = count(array_filter($qList, fn($q) => $q['discrimination_category'] === 'Buruk / Jelek'));
    ?>

        <!-- Summary Metrics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs text-slate-400 font-bold uppercase mb-1">Total Peserta Ujian</div>
                <div class="text-2xl font-black text-slate-800"><?= $totalP ?> <span class="text-xs font-normal text-slate-500">Siswa</span></div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs text-slate-400 font-bold uppercase mb-1">Rata-Rata Nilai</div>
                <div class="text-2xl font-black text-indigo-600"><?= $summary['avg_score'] ?></div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs text-slate-400 font-bold uppercase mb-1">Nilai Tertinggi / Terendah</div>
                <div class="text-2xl font-black text-slate-800"><?= $summary['highest_score'] ?> <span class="text-sm font-medium text-slate-400">/ <?= $summary['lowest_score'] ?></span></div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs text-slate-400 font-bold uppercase mb-1">Kelulusan KKM (<?= $analysisData['exam']['kkm'] ?>)</div>
                <div class="text-2xl font-black text-emerald-600"><?= $summary['pass_rate'] ?>% <span class="text-xs font-normal text-slate-400">(<?= $summary['passed_count'] ?> Lulus)</span></div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fa-solid fa-scale-balanced text-indigo-600 mr-2"></i> Proporsi Tingkat Kesukaran Soal
                </h3>
                <div class="h-56">
                    <canvas id="chartKesukaran"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fa-solid fa-arrows-split-up-and-left text-purple-600 mr-2"></i> Distribusi Daya Pembeda Soal
                </h3>
                <div class="h-56">
                    <canvas id="chartPembeda"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Tabel Hasil Analisis Butir Soal</h3>
                <span class="text-xs text-slate-400">Total: <?= count($qList) ?> Butir Soal</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                            <th class="py-3 px-4">No & Tipe</th>
                            <th class="py-3 px-4">Pertanyaan</th>
                            <th class="py-3 px-4 text-center">Menjawab Benar</th>
                            <th class="py-3 px-4 text-center">Tingkat Kesukaran (P)</th>
                            <th class="py-3 px-4 text-center">Daya Pembeda (D)</th>
                            <th class="py-3 px-4">Sebaran Pengecoh (Pilgan)</th>
                            <th class="py-3 px-4 text-center">Rekomendasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($qList as $item): 
                            $q = $item['question'];
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="w-6 h-6 rounded bg-slate-900 text-white flex items-center justify-center text-xs">
                                            <?= $q['question_number'] ?>
                                        </span>
                                        <span class="uppercase text-[10px] text-slate-500 font-semibold"><?= $q['type'] ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-700 max-w-xs truncate" title="<?= strip_tags($q['question_text']) ?>">
                                    <?= strip_tags($q['question_text']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-semibold text-slate-800">
                                    <?= $item['correct_count'] ?> / <?= $item['total_participants'] ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $item['difficulty_color'] ?>">
                                        <?= $item['difficulty_index'] ?> (<?= $item['difficulty_category'] ?>)
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $item['discrimination_color'] ?>">
                                        <?= $item['discrimination_index'] ?> (<?= $item['discrimination_category'] ?>)
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($q['type'] === 'pilgan'): ?>
                                        <div class="flex items-center space-x-1 text-[11px] font-mono">
                                            <?php foreach ($item['distractor_counts'] as $optK => $optC): ?>
                                                <?php if ($optK !== 'OTHER'): ?>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700"><?= $optK ?>: <strong><?= $optC ?></strong></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[11px]">- Non-Pilgan -</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="font-bold text-[11px] text-slate-800"><?= $item['recommendation'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        // Chart Tingkat Kesukaran
        const ctxKesukaran = document.getElementById('chartKesukaran').getContext('2d');
        new Chart(ctxKesukaran, {
            type: 'doughnut',
            data: {
                labels: ['Mudah (P ≥ 0.70)', 'Sedang (0.30 - 0.70)', 'Sukar (P < 0.30)'],
                datasets: [{
                    data: [<?= $diffMudah ?>, <?= $diffSedang ?>, <?= $diffSukar ?>],
                    backgroundColor: ['#10b981', '#3b82f6', '#f43f5e']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Chart Daya Pembeda
        const ctxPembeda = document.getElementById('chartPembeda').getContext('2d');
        new Chart(ctxPembeda, {
            type: 'bar',
            data: {
                labels: ['Sangat Baik', 'Baik', 'Cukup', 'Buruk / Jelek'],
                datasets: [{
                    label: 'Jumlah Butir Soal',
                    data: [<?= $discSangatBaik ?>, <?= $discBaik ?>, <?= $discCukup ?>, <?= $discBuruk ?>],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#f43f5e'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
