<?php
// operator/monitoring.php - Live Proctor Monitoring Room
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole(['operator', 'admin']);
$pdo = Database::getConnection();

// Ambil semua ujian yang aktif
$exams = $pdo->query("SELECT e.*, qb.title as bank_title, s.name as subject_name 
                      FROM exams e 
                      JOIN question_banks qb ON e.bank_id = qb.id 
                      JOIN subjects s ON qb.subject_id = s.id 
                      ORDER BY e.status = 'active' DESC, e.id DESC")->fetchAll();

$selectedExamId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));

$pageTitle = 'Live Monitoring Ujian';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="flex h-2.5 w-2.5 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <h1 class="text-2xl font-black text-slate-800">Live Monitoring Ujian</h1>
            </div>
            <p class="text-sm text-slate-500">Pemantauan real-time status pengerjaan, sisa waktu, dan kontrol sesi peserta ujian.</p>
        </div>

        <div class="flex items-center space-x-3">
            <form method="GET" action="">
                <select name="exam_id" id="examSelect" onchange="this.form.submit()" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $selectedExamId == $ex['id'] ? 'selected' : '' ?>>
                            [<?= ucfirst($ex['status']) ?>] <?= e($ex['title']) ?> (<?= e($ex['session_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <button onclick="fetchMonitoringData()" class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs transition" title="Refresh Sekarang">
                <i class="fa-solid fa-arrows-rotate" id="refreshIcon"></i>
            </button>
        </div>
    </div>

    <!-- Live Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-blue-500/10">
            <div class="text-xs font-bold uppercase text-slate-400 mb-1">Sedang Mengerjakan</div>
            <div class="text-3xl font-black text-blue-600" id="statWorking">0</div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-emerald-500/10">
            <div class="text-xs font-bold uppercase text-slate-400 mb-1">Sudah Submit / Selesai</div>
            <div class="text-3xl font-black text-emerald-600" id="statSubmitted">0</div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm ring-2 ring-rose-500/10">
            <div class="text-xs font-bold uppercase text-slate-400 mb-1">Terkunci / Pelanggaran</div>
            <div class="text-3xl font-black text-rose-600" id="statBlocked">0</div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="text-xs font-bold uppercase text-slate-400 mb-1">Total Peserta Terdaftar</div>
            <div class="text-3xl font-black text-slate-800" id="statTotal">0</div>
        </div>
    </div>

    <!-- Live Data Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <h3 class="font-bold text-slate-800 text-sm">Daftar Status Peserta Ujian</h3>
                <span class="text-xs text-slate-400">&bull; Auto-refresh tiap 4 detik</span>
            </div>
            <div class="flex items-center space-x-2">
                <input type="text" id="searchStudent" placeholder="Cari nama siswa / NISN..." onkeyup="filterTable()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs" id="monitorTable">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3.5 px-4">Nama Siswa & NISN</th>
                        <th class="py-3.5 px-4">Kelas & IP</th>
                        <th class="py-3.5 px-4">Sisa Waktu</th>
                        <th class="py-3.5 px-4">Progress Pengerjaan</th>
                        <th class="py-3.5 px-4 text-center">Pelanggaran Tab</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-center">Kontrol Proktor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="monitorTbody">
                    <tr><td colspan="7" class="py-12 text-center text-slate-400">Memuat data live monitoring...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const currentExamId = <?= $selectedExamId ?>;

async function fetchMonitoringData() {
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('animate-spin');

    try {
        const response = await fetch(`<?= BASE_URL ?>/operator/api_monitor.php?exam_id=${currentExamId}`);
        const result = await response.json();

        if (result.success) {
            document.getElementById('statWorking').innerText = result.summary.in_progress || 0;
            document.getElementById('statSubmitted').innerText = result.summary.submitted || 0;
            document.getElementById('statBlocked').innerText = result.summary.blocked || 0;
            document.getElementById('statTotal').innerText = result.summary.total || 0;

            renderTable(result.participants);
        }
    } catch (err) {
        console.error("Gagal polling data monitoring:", err);
    } finally {
        if (icon) icon.classList.remove('animate-spin');
    }
}

function renderTable(participants) {
    const tbody = document.getElementById('monitorTbody');
    if (!participants || participants.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-slate-400">Belum ada siswa yang masuk ke sesi ujian ini.</td></tr>`;
        return;
    }

    let html = '';
    participants.forEach(p => {
        let statusBadge = '';
        if (p.status === 'in_progress') {
            statusBadge = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 animate-pulse"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5"></span> Mengerjakan</span>`;
        } else if (p.status === 'submitted') {
            statusBadge = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700"><i class="fa-solid fa-check mr-1"></i> Selesai</span>`;
        } else if (p.status === 'blocked') {
            statusBadge = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700"><i class="fa-solid fa-lock mr-1"></i> Terkunci</span>`;
        } else {
            statusBadge = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">Belum Mulai</span>`;
        }

        let violationBadge = p.violation_count > 0 
            ? `<span class="px-2 py-0.5 rounded font-bold bg-rose-100 text-rose-700">${p.violation_count}x</span>` 
            : `<span class="text-slate-400 font-mono">0</span>`;

        html += `
        <tr class="hover:bg-slate-50/80 transition search-row">
            <td class="py-3 px-4">
                <div class="font-bold text-slate-900 text-sm">${escapeHtml(p.full_name)}</div>
                <div class="text-[11px] font-mono text-slate-400">NISN: ${escapeHtml(p.nisn)} &bull; ${escapeHtml(p.exam_number)}</div>
            </td>
            <td class="py-3 px-4">
                <div class="font-semibold text-slate-700">${escapeHtml(p.class_name)}</div>
                <div class="text-[11px] font-mono text-slate-400">IP: ${escapeHtml(p.ip_address)}</div>
            </td>
            <td class="py-3 px-4 font-mono font-bold ${p.remaining_seconds < 300 ? 'text-rose-600 animate-pulse' : 'text-slate-800'}">
                ${p.status === 'in_progress' ? p.remaining_formatted : (p.status === 'submitted' ? '-' : '60:00')}
            </td>
            <td class="py-3 px-4">
                <div class="flex items-center justify-between text-[11px] font-semibold mb-1">
                    <span>${p.answered_count} / ${p.total_questions} Soal</span>
                    <span class="text-indigo-600">${p.progress_percent}%</span>
                </div>
                <div class="w-28 bg-slate-200 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" style="width: ${p.progress_percent}%"></div>
                </div>
                ${p.doubtful_count > 0 ? `<div class="text-[10px] text-amber-600 mt-0.5">${p.doubtful_count} ragu-ragu</div>` : ''}
            </td>
            <td class="py-3 px-4 text-center font-bold">
                ${violationBadge}
            </td>
            <td class="py-3 px-4">
                ${statusBadge}
            </td>
            <td class="py-3 px-4 text-center">
                <div class="inline-flex items-center space-x-1">
                    <button onclick="proctorAction('reset_login', ${p.id}, '${escapeHtml(p.full_name)}')" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Reset Login (Siswa bisa login lagi)">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                    ${p.status === 'in_progress' ? `
                        <button onclick="promptExtendTime(${p.id}, '${escapeHtml(p.full_name)}')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Tambah Waktu Ujian (+10m)">
                            <i class="fa-solid fa-clock-medical"></i>
                        </button>
                        <button onclick="proctorAction('force_submit', ${p.id}, '${escapeHtml(p.full_name)}')" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Paksa Selesai">
                            <i class="fa-solid fa-flag-checkered"></i>
                        </button>
                    ` : ''}
                    ${p.status === 'blocked' ? `
                        <button onclick="proctorAction('unblock', ${p.id}, '${escapeHtml(p.full_name)}')" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Buka Blokir">
                            <i class="fa-solid fa-lock-open"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
        `;
    });

    tbody.innerHTML = html;
}

function filterTable() {
    const val = document.getElementById('searchStudent').value.toLowerCase();
    document.querySelectorAll('.search-row').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
}

function proctorAction(actionType, partId, studentName) {
    let confirmMsg = 'Apakah Anda yakin ingin menjalankan aksi ini?';
    if (actionType === 'reset_login') confirmMsg = `Reset status login siswa <strong>${studentName}</strong>? (Gunakan jika browser siswa hang atau ganti komputer)`;
    if (actionType === 'force_submit') confirmMsg = `Paksa selesaikan ujian untuk siswa <strong>${studentName}</strong> sekarang?`;
    if (actionType === 'unblock') confirmMsg = `Buka blokir ujian siswa <strong>${studentName}</strong> agar bisa melanjutkan pengerjaan?`;

    Swal.fire({
        title: 'Konfirmasi Operator',
        html: confirmMsg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Jalankan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#4f46e5'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', actionType);
            formData.append('participation_id', partId);

            const res = await fetch(`<?= BASE_URL ?>/operator/api_monitor.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 1500, showConfirmButton: false });
                fetchMonitoringData();
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
            }
        }
    });
}

function promptExtendTime(partId, studentName) {
    Swal.fire({
        title: 'Tambah Waktu Ujian',
        html: `Berapa menit waktu tambahan untuk <strong>${studentName}</strong>?`,
        input: 'number',
        inputValue: 10,
        showCancelButton: true,
        confirmButtonText: 'Tambah Waktu',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#4f46e5',
        inputValidator: (val) => {
            if (!val || val <= 0) return 'Masukkan jumlah menit yang valid (minimal 1)!';
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'extend_time');
            formData.append('participation_id', partId);
            formData.append('minutes', result.value);

            const res = await fetch(`<?= BASE_URL ?>/operator/api_monitor.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Waktu Ditambahkan!', text: data.message, timer: 1500, showConfirmButton: false });
                fetchMonitoringData();
            }
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.innerText = text || '';
    return div.innerHTML;
}

// Jalankan auto polling tiap 4 detik
fetchMonitoringData();
setInterval(fetchMonitoringData, 4000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
