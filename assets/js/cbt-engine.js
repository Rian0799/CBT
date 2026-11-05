/**
 * CBT Engine - Core Client-Side Examination Runner
 * Handles 6 Question Types, Real-time Autosave, Anti-cheat Lockdown, Timer Sync, and Palette Navigation
 */

let currentIndex = 0;
const questions = CBT_CONFIG.questions;
let remainingSeconds = CBT_CONFIG.remainingSeconds;
let timerInterval = null;
let saveDebounceTimer = null;
let violationCount = 0;

// Type Badges styling
const TYPE_LABELS = {
    'pilgan': { label: 'Pilihan Ganda', class: 'bg-blue-100 text-blue-700 border-blue-200' },
    'kompleks': { label: 'PG Kompleks', class: 'bg-purple-100 text-purple-700 border-purple-200' },
    'jodohkan': { label: 'Menjodohkan', class: 'bg-teal-100 text-teal-700 border-teal-200' },
    'benarsalah': { label: 'Benar / Salah', class: 'bg-amber-100 text-amber-700 border-amber-200' },
    'isian': { label: 'Isian Singkat', class: 'bg-indigo-100 text-indigo-700 border-indigo-200' },
    'uraian': { label: 'Uraian / Essay', class: 'bg-rose-100 text-rose-700 border-rose-200' }
};

document.addEventListener('DOMContentLoaded', () => {
    initTimer();
    renderPalette();
    loadQuestion(0);
    setupAntiCheat();
});

// ================= TIMER ENGINE =================
function initTimer() {
    updateTimerDisplay();
    timerInterval = setInterval(() => {
        remainingSeconds--;
        updateTimerDisplay();

        // Sinkronisasi ke server setiap 30 detik
        if (remainingSeconds % 30 === 0) {
            syncTimerWithServer();
        }

        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            timeUpSubmit();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const timerEl = document.getElementById('countdownTimer');
    const timerIcon = document.getElementById('timerIcon');
    if (!timerEl) return;

    const hours = Math.floor(remainingSeconds / 3600);
    const minutes = Math.floor((remainingSeconds % 3600) / 60);
    const seconds = remainingSeconds % 60;

    let display = '';
    if (hours > 0) {
        display = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    } else {
        display = `${pad(minutes)}:${pad(seconds)}`;
    }

    timerEl.innerText = display;

    // Peringatan 5 menit terakhir
    if (remainingSeconds <= 300) {
        timerEl.classList.add('text-rose-400', 'animate-pulse');
        if (timerIcon) timerIcon.classList.add('text-rose-500');
    }
}

function pad(num) {
    return num.toString().padStart(2, '0');
}

async function syncTimerWithServer() {
    try {
        const formData = new FormData();
        formData.append('action', 'sync_timer');
        formData.append('remaining_seconds', remainingSeconds);
        await fetch(CBT_CONFIG.apiEndpoint, { method: 'POST', body: formData });
    } catch (e) {
        console.warn('Sync timer network retry:', e);
    }
}

function timeUpSubmit() {
    Swal.fire({
        title: 'Waktu Ujian Habis!',
        text: 'Waktu pengerjaan telah berakhir. Jawaban Anda akan otomatis disimpan dan diserahkan.',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        timer: 3000
    }).then(() => {
        submitExamFinal();
    });
}

// ================= QUESTION NAVIGATION & RENDERING =================
function loadQuestion(index) {
    if (index < 0 || index >= questions.length) return;
    currentIndex = index;

    const q = questions[currentIndex];

    // Header info
    document.getElementById('qNumberDisplay').innerText = q.display_number;
    document.getElementById('qWeightDisplay').innerText = 'Bobot: ' + q.score_weight;
    
    const badgeInfo = TYPE_LABELS[q.type] || { label: q.type.toUpperCase(), class: 'bg-slate-100 text-slate-700' };
    const badgeEl = document.getElementById('qTypeBadge');
    badgeEl.innerText = badgeInfo.label;
    badgeEl.className = `px-3 py-1 rounded-full text-xs font-bold uppercase border ${badgeInfo.class}`;

    // Question text
    document.getElementById('qTextDisplay').innerHTML = q.question_text;

    // Checkbox Ragu-Ragu
    const doubtfulCheckbox = document.getElementById('doubtfulCheck');
    doubtfulCheckbox.checked = parseInt(q.is_marked_doubtful) === 1;

    // Render Answer Widget for specific Question Type
    renderAnswerWidget(q);

    // Prev / Next button states
    document.getElementById('btnPrev').disabled = (currentIndex === 0);
    document.getElementById('btnPrev').style.opacity = (currentIndex === 0) ? '0.5' : '1';

    const isLast = (currentIndex === questions.length - 1);
    document.getElementById('btnNext').classList.toggle('hidden', isLast);
    document.getElementById('btnFinish').classList.toggle('hidden', !isLast);

    renderPalette();
}

function navigateQuestion(offset) {
    loadQuestion(currentIndex + offset);
}

// ================= 6 QUESTION TYPES INTERACTIVE WIDGETS =================
function renderAnswerWidget(q) {
    const container = document.getElementById('qAnswerContainer');
    container.innerHTML = '';
    const currentAns = q.student_answer_decoded;

    // 1. Tipe PILGAN (Pilihan Ganda Tunggal)
    if (q.type === 'pilgan') {
        const opts = q.options_decoded;
        let html = '<div class="space-y-3">';
        for (const [key, text] of Object.entries(opts)) {
            const isSelected = (currentAns === key);
            html += `
            <div onclick="selectPilgan('${key}')" class="flex items-center space-x-3 p-4 rounded-2xl border-2 cursor-pointer transition ${isSelected ? 'border-indigo-600 bg-indigo-50/70 shadow-sm' : 'border-slate-200 hover:border-slate-300 bg-white'}">
                <span class="w-8 h-8 rounded-xl font-bold flex items-center justify-center text-sm transition ${isSelected ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-300' : 'bg-slate-100 text-slate-700'}">
                    ${key}
                </span>
                <span class="text-sm font-medium text-slate-800 flex-1 leading-snug">${escapeHtml(text)}</span>
                ${isSelected ? '<i class="fa-solid fa-circle-check text-indigo-600 text-lg"></i>' : ''}
            </div>`;
        }
        html += '</div>';
        container.innerHTML = html;
    }

    // 2. Tipe KOMPLEKS (Pilihan Ganda Kompleks - Multi Select)
    else if (q.type === 'kompleks') {
        const opts = q.options_decoded || [];
        const selectedArr = Array.isArray(currentAns) ? currentAns : [];
        let html = '<div class="space-y-3"><p class="text-xs text-indigo-600 font-semibold mb-2"><i class="fa-solid fa-circle-info mr-1"></i> Anda dapat memilih lebih dari satu jawaban yang benar:</p>';
        opts.forEach(item => {
            const isChecked = selectedArr.includes(item.key);
            html += `
            <div onclick="toggleKompleks('${item.key}')" class="flex items-center space-x-3 p-4 rounded-2xl border-2 cursor-pointer transition ${isChecked ? 'border-purple-600 bg-purple-50/70 shadow-sm' : 'border-slate-200 hover:border-slate-300 bg-white'}">
                <span class="w-8 h-8 rounded-xl font-bold flex items-center justify-center text-sm transition ${isChecked ? 'bg-purple-600 text-white shadow-sm shadow-purple-300' : 'bg-slate-100 text-slate-700'}">
                    ${item.key}
                </span>
                <span class="text-sm font-medium text-slate-800 flex-1 leading-snug">${escapeHtml(item.text)}</span>
                <div class="w-5 h-5 rounded-md border flex items-center justify-center transition ${isChecked ? 'bg-purple-600 border-purple-600 text-white' : 'border-slate-300'}">
                    ${isChecked ? '<i class="fa-solid fa-check text-xs"></i>' : ''}
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // 3. Tipe MENJODOHKAN (Matching Pairs)
    else if (q.type === 'jodohkan') {
        const premises = q.options_decoded.premises || [];
        const targets = q.options_decoded.targets || [];
        const matches = (typeof currentAns === 'object' && currentAns !== null) ? currentAns : {};

        let html = '<div class="space-y-4 bg-slate-50 p-5 rounded-3xl border border-slate-200">';
        html += '<p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Pasangkan setiap pernyataan di kolom kiri dengan pilihan yang tepat di kolom kanan:</p>';

        premises.forEach(pr => {
            const selectedVal = matches[pr.id] || '';
            html += `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <div class="font-bold text-sm text-slate-800 flex-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-teal-500 mr-2"></span>
                    ${escapeHtml(pr.text)}
                </div>
                <div class="sm:w-1/2">
                    <select onchange="updateJodohkan('${pr.id}', this.value)" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-teal-500 outline-none bg-slate-50">
                        <option value="">-- Pilih Pasangan --</option>
                        ${targets.map(t => `<option value="${t.id}" ${selectedVal === t.id ? 'selected' : ''}>${escapeHtml(t.text)}</option>`).join('')}
                    </select>
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // 4. Tipe BENAR / SALAH (Matrix Statements)
    else if (q.type === 'benarsalah') {
        const statements = q.options_decoded || [];
        const answers = (typeof currentAns === 'object' && currentAns !== null) ? currentAns : {};

        let html = '<div class="space-y-3">';
        statements.forEach(st => {
            const val = answers[st.id] || '';
            html += `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-white rounded-2xl border-2 border-slate-200">
                <span class="text-sm font-medium text-slate-800 flex-1">${escapeHtml(st.text)}</span>
                <div class="inline-flex space-x-2">
                    <button type="button" onclick="selectBenarSalah('${st.id}', 'B')" class="px-4 py-2 rounded-xl font-bold text-xs transition ${val === 'B' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
                        BENAR
                    </button>
                    <button type="button" onclick="selectBenarSalah('${st.id}', 'S')" class="px-4 py-2 rounded-xl font-bold text-xs transition ${val === 'S' ? 'bg-rose-600 text-white shadow-md shadow-rose-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
                        SALAH
                    </button>
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // 5. Tipe ISIAN SINGKAT (Short Answer)
    else if (q.type === 'isian') {
        const textVal = (typeof currentAns === 'string') ? currentAns : '';
        let html = `
        <div class="space-y-2">
            <label class="block font-bold text-slate-700 text-xs uppercase tracking-wider">Ketik Jawaban Singkat Anda:</label>
            <input type="text" id="inputIsian" value="${escapeHtml(textVal)}" oninput="debounceIsian(this.value)" placeholder="Tuliskan jawaban singkat disini..." class="w-full px-4 py-3 rounded-2xl border-2 border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-100 text-sm font-semibold outline-none transition bg-white">
            <span class="text-[11px] text-slate-400 italic block">Jawaban akan otomatis tersimpan saat Anda mengetik.</span>
        </div>`;
        container.innerHTML = html;
    }

    // 6. Tipe URAIAN (Essay)
    else if (q.type === 'uraian') {
        const textVal = (typeof currentAns === 'string') ? currentAns : '';
        let html = `
        <div class="space-y-2">
            <label class="block font-bold text-slate-700 text-xs uppercase tracking-wider">Tuliskan Jawaban Uraian / Essay Anda:</label>
            <textarea id="textareaUraian" rows="6" oninput="debounceUraian(this.value)" placeholder="Tuliskan penjabaran uraian jawaban Anda secara komprehensif..." class="w-full p-4 rounded-2xl border-2 border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-100 text-sm outline-none transition leading-relaxed bg-white">${escapeHtml(textVal)}</textarea>
            <div class="flex items-center justify-between text-[11px] text-slate-400">
                <span id="charCounter">${textVal.length} karakter</span>
                <span>Otomatis tersimpan berkala</span>
            </div>
        </div>`;
        container.innerHTML = html;
    }
}

// ================= HANDLERS UNTUK TIAP WIDGET =================
function selectPilgan(key) {
    const q = questions[currentIndex];
    q.student_answer_decoded = key;
    renderAnswerWidget(q);
    saveAnswer(q.id, key);
}

function toggleKompleks(key) {
    const q = questions[currentIndex];
    let arr = Array.isArray(q.student_answer_decoded) ? [...q.student_answer_decoded] : [];
    if (arr.includes(key)) {
        arr = arr.filter(k => k !== key);
    } else {
        arr.push(key);
    }
    arr.sort();
    q.student_answer_decoded = arr;
    renderAnswerWidget(q);
    saveAnswer(q.id, arr);
}

function updateJodohkan(premiseId, targetId) {
    const q = questions[currentIndex];
    let map = (typeof q.student_answer_decoded === 'object' && q.student_answer_decoded !== null) ? {...q.student_answer_decoded} : {};
    if (targetId) {
        map[premiseId] = targetId;
    } else {
        delete map[premiseId];
    }
    q.student_answer_decoded = map;
    saveAnswer(q.id, map);
    renderPalette();
}

function selectBenarSalah(statementId, val) {
    const q = questions[currentIndex];
    let map = (typeof q.student_answer_decoded === 'object' && q.student_answer_decoded !== null) ? {...q.student_answer_decoded} : {};
    map[statementId] = val;
    q.student_answer_decoded = map;
    renderAnswerWidget(q);
    saveAnswer(q.id, map);
}

function debounceIsian(val) {
    const q = questions[currentIndex];
    q.student_answer_decoded = val;
    clearTimeout(saveDebounceTimer);
    saveDebounceTimer = setTimeout(() => {
        saveAnswer(q.id, val);
    }, 600);
}

function debounceUraian(val) {
    const q = questions[currentIndex];
    q.student_answer_decoded = val;
    const charEl = document.getElementById('charCounter');
    if (charEl) charEl.innerText = val.length + ' karakter';

    clearTimeout(saveDebounceTimer);
    saveDebounceTimer = setTimeout(() => {
        saveAnswer(q.id, val);
    }, 800);
}

function toggleDoubtfulCurrent() {
    const q = questions[currentIndex];
    const isDoubtful = document.getElementById('doubtfulCheck').checked ? 1 : 0;
    q.is_marked_doubtful = isDoubtful;

    // Send update
    const formData = new FormData();
    formData.append('action', 'toggle_doubtful');
    formData.append('question_id', q.id);
    formData.append('is_marked_doubtful', isDoubtful);
    fetch(CBT_CONFIG.apiEndpoint, { method: 'POST', body: formData });

    renderPalette();
}

// ================= SAVE ANSWER VIA AJAX =================
async function saveAnswer(questionId, answerData) {
    const statusEl = document.getElementById('saveStatus');
    if (statusEl) {
        statusEl.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> <span>Menyimpan...</span>';
        statusEl.className = 'flex items-center space-x-1.5 text-xs font-semibold text-amber-600 bg-amber-50 px-3 py-1 rounded-full border border-amber-200 transition';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'save_answer');
        formData.append('question_id', questionId);

        if (typeof answerData === 'object' && answerData !== null) {
            formData.append('student_answer', JSON.stringify(answerData));
        } else {
            formData.append('student_answer', answerData || '');
        }

        const res = await fetch(CBT_CONFIG.apiEndpoint, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && statusEl) {
            statusEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Tersimpan</span>';
            statusEl.className = 'flex items-center space-x-1.5 text-xs font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200 transition';
        }
    } catch (e) {
        if (statusEl) {
            statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>Offline</span>';
            statusEl.className = 'flex items-center space-x-1.5 text-xs font-semibold text-rose-600 bg-rose-50 px-3 py-1 rounded-full border border-rose-200 transition';
        }
    }

    renderPalette();
}

// ================= PALETTE NAVIGATION =================
function isQuestionAnswered(q) {
    const ans = q.student_answer_decoded;
    if (ans === null || ans === undefined || ans === '') return false;
    if (Array.isArray(ans) && ans.length === 0) return false;
    if (typeof ans === 'object' && Object.keys(ans).length === 0) return false;
    return true;
}

function renderPalette() {
    const grid = document.getElementById('paletteGrid');
    if (!grid) return;

    let answeredCount = 0;
    let html = '';

    questions.forEach((q, idx) => {
        const answered = isQuestionAnswered(q);
        const isDoubtful = parseInt(q.is_marked_doubtful) === 1;
        const isCurrent = (idx === currentIndex);

        if (answered) answeredCount++;

        let colorClasses = 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200';
        if (isDoubtful) {
            colorClasses = 'bg-amber-400 text-amber-950 font-black border-amber-500 shadow-sm shadow-amber-200';
        } else if (answered) {
            colorClasses = 'bg-emerald-600 text-white font-black border-emerald-700 shadow-sm shadow-emerald-200';
        }

        const ringClass = isCurrent ? 'ring-4 ring-indigo-500 ring-offset-2' : '';

        html += `
        <button type="button" onclick="loadQuestion(${idx})" class="w-10 h-10 rounded-xl font-bold text-xs flex items-center justify-center border transition ${colorClasses} ${ringClass}">
            ${q.display_number}
        </button>`;
    });

    grid.innerHTML = html;

    const counterEl = document.getElementById('answeredCounter');
    if (counterEl) {
        counterEl.innerText = `${answeredCount} / ${questions.length}`;
    }
}

// ================= ANTI-CHEAT WATCHDOG =================
function setupAntiCheat() {
    if (!CBT_CONFIG.antiCheatEnabled) return;

    // Deteksi pergantian tab browser
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            handleViolation('tab_switch');
        }
    });

    // Deteksi window blur
    window.addEventListener('blur', () => {
        handleViolation('window_blur');
    });

    // Cegah F12, Ctrl+U, Ctrl+Shift+I
    window.addEventListener('keydown', (e) => {
        if (
            e.key === 'F12' ||
            (e.ctrlKey && (e.key === 'u' || e.key === 'U' || e.key === 'c' || e.key === 'C')) ||
            (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C'))
        ) {
            e.preventDefault();
            return false;
        }
    });
}

async function handleViolation(type) {
    try {
        const formData = new FormData();
        formData.append('action', 'log_violation');
        formData.append('event_type', type);

        const res = await fetch(CBT_CONFIG.apiEndpoint, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.is_blocked) {
            Swal.fire({
                title: 'Ujian Terkunci!',
                html: 'Anda telah melebihi batas toleransi pelanggaran (keluar layar ujian). Hubungi Proktor / Pengawas untuk membuka kunci.',
                icon: 'error',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            document.body.innerHTML = `
            <div class="min-h-screen flex items-center justify-center bg-slate-900 text-white p-6 text-center">
                <div class="max-w-md bg-white/10 p-8 rounded-3xl border border-white/20">
                    <i class="fa-solid fa-lock text-5xl text-rose-500 mb-4"></i>
                    <h2 class="text-2xl font-bold mb-2">Ujian Anda Terkunci</h2>
                    <p class="text-sm text-slate-300">Terdeteksi melakukan pelanggaran membuka tab/jendela lain melebihi batas. Silakan melapor ke Pengawas/Proktor ruangan.</p>
                </div>
            </div>`;
        } else {
            Swal.fire({
                title: 'Peringatan Anti-Curang!',
                html: `Anda terdeteksi meninggalkan jendela ujian! Pelanggaran: <strong>${data.violation_count} / ${data.max_violations}</strong>.<br><small class="text-slate-500">Jika melebihi batas, ujian Anda akan terkunci otomatis.</small>`,
                icon: 'warning',
                confirmButtonText: 'Kembali Mengerjakan',
                confirmButtonColor: '#4f46e5'
            });
        }
    } catch (e) {
        console.warn('Violation logging network:', e);
    }
}

// ================= FINISH EXAM CONFIRMATION =================
function confirmFinishExam() {
    let answered = 0;
    let doubtful = 0;
    let unanswered = 0;

    questions.forEach(q => {
        if (parseInt(q.is_marked_doubtful) === 1) doubtful++;
        if (isQuestionAnswered(q)) {
            answered++;
        } else {
            unanswered++;
        }
    });

    let extraWarning = '';
    if (doubtful > 0) {
        extraWarning += `<div class="p-2 rounded-lg bg-amber-50 text-amber-800 text-xs mt-2 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Anda masih memiliki ${doubtful} soal berstatus Ragu-Ragu!</div>`;
    }
    if (unanswered > 0) {
        extraWarning += `<div class="p-2 rounded-lg bg-rose-50 text-rose-800 text-xs mt-2 font-bold"><i class="fa-solid fa-circle-xmark mr-1"></i> Masih ada ${unanswered} soal yang belum dijawab!</div>`;
    }

    Swal.fire({
        title: 'Konfirmasi Selesai Ujian',
        html: `
        <div class="text-left text-xs space-y-2 mt-2">
            <p class="text-slate-600">Apakah Anda yakin ingin menyelesaikan ujian ini? Pastikan seluruh soal telah diperiksa.</p>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div>Sudah Dijawab: <strong class="text-emerald-700">${answered} Soal</strong></div>
                <div>Belum Dijawab: <strong class="text-rose-700">${unanswered} Soal</strong></div>
                <div>Ragu-Ragu: <strong class="text-amber-700">${doubtful} Soal</strong></div>
            </div>
            ${extraWarning}
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Selesaikan Ujian',
        cancelButtonText: 'Periksa Kembali',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b'
    }).then((result) => {
        if (result.isConfirmed) {
            submitExamFinal();
        }
    });
}

async function submitExamFinal() {
    Swal.fire({
        title: 'Mengirimkan Lembar Ujian...',
        text: 'Mohon tunggu sementara sistem menghitung hasil pengerjaan Anda.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const formData = new FormData();
        formData.append('action', 'submit_exam');
        const res = await fetch(CBT_CONFIG.apiEndpoint, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else {
            window.location.href = CBT_CONFIG.finishEndpoint;
        }
    } catch (e) {
        window.location.href = CBT_CONFIG.finishEndpoint;
    }
}

// ================= UTILITIES =================
function setFontSize(size) {
    const card = document.getElementById('questionCard');
    if (!card) return;
    card.classList.remove('font-size-small', 'font-size-normal', 'font-size-large');
    card.classList.add('font-size-' + size);
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

function togglePaletteDrawer() {
    const palette = document.getElementById('paletteCard');
    if (palette) {
        palette.scrollIntoView({ behavior: 'smooth' });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
