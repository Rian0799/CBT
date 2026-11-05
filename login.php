<?php
// login.php - Portal Login Terpadu (Semua Role: Siswa, Guru, Admin, Operator)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Auth.php';

// Jika sudah login, langsung alihkan ke dashboard masing-masing
if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'admin') redirect(BASE_URL . '/admin/index.php');
    if ($role === 'guru') redirect(BASE_URL . '/guru/index.php');
    if ($role === 'operator') redirect(BASE_URL . '/operator/index.php');
}
if (Auth::checkStudent()) {
    redirect(BASE_URL . '/ujian/konfirmasi.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username/NISN dan password wajib diisi!';
    } else {
        // Coba login sebagai petugas (admin, guru, operator)
        $resStaff = Auth::loginUser($username, $password);
        if ($resStaff['success']) {
            $role = Auth::role();
            set_flash('success', 'Selamat datang, ' . ($_SESSION['full_name'] ?? $username) . '!');
            if ($role === 'admin') redirect(BASE_URL . '/admin/index.php');
            if ($role === 'guru') redirect(BASE_URL . '/guru/index.php');
            if ($role === 'operator') redirect(BASE_URL . '/operator/index.php');
            redirect(BASE_URL . '/');
        }

        // Jika gagal sebagai staff, coba login sebagai siswa
        $resStudent = Auth::loginStudent($username, $password);
        if ($resStudent['success']) {
            redirect(BASE_URL . '/ujian/konfirmasi.php');
        }

        $error = 'Username/NISN atau password salah! Pastikan akun Anda aktif dan terdaftar.';
    }
}

$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e($appTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .role-tab { color: #c7d2fe; transition: all .2s; cursor: pointer; }
        .role-tab.active { background: white; color: #4338ca; box-shadow: 0 1px 6px rgba(0,0,0,0.13); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 min-h-screen flex flex-col">

    <!-- TOP NAV: Logo + Quick Access Buttons -->
    <header class="w-full px-4 sm:px-8 py-4 flex items-center justify-between border-b border-white/10">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-white text-lg shadow-lg">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div class="font-extrabold text-white text-base leading-tight"><?= e($appTitle) ?></div>
                <div class="text-indigo-300 text-xs"><?= e($schoolName) ?></div>
            </div>
        </div>

        <!-- Quick role buttons - kanan atas -->
        <div class="flex items-center gap-1.5">
            <span class="hidden md:block text-xs text-indigo-400 mr-2 font-medium">Pilih Peran:</span>
            <button onclick="switchTab('admin')" class="flex items-center px-2.5 py-1.5 rounded-xl bg-purple-500/20 hover:bg-purple-500/40 border border-purple-400/20 text-purple-200 hover:text-white text-xs font-bold transition gap-1">
                <i class="fa-solid fa-crown"></i><span class="hidden sm:inline">Admin</span>
            </button>
            <button onclick="switchTab('guru')" class="flex items-center px-2.5 py-1.5 rounded-xl bg-blue-500/20 hover:bg-blue-500/40 border border-blue-400/20 text-blue-200 hover:text-white text-xs font-bold transition gap-1">
                <i class="fa-solid fa-chalkboard-user"></i><span class="hidden sm:inline">Guru</span>
            </button>
            <button onclick="switchTab('operator')" class="flex items-center px-2.5 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/40 border border-amber-400/20 text-amber-200 hover:text-white text-xs font-bold transition gap-1">
                <i class="fa-solid fa-tv"></i><span class="hidden sm:inline">Operator</span>
            </button>
            <button onclick="switchTab('siswa')" class="flex items-center px-2.5 py-1.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/40 border border-emerald-400/20 text-emerald-200 hover:text-white text-xs font-bold transition gap-1">
                <i class="fa-solid fa-user-graduate"></i><span class="hidden sm:inline">Siswa</span>
            </button>
        </div>
    </header>

    <!-- MAIN -->
    <main class="flex-1 flex items-center justify-center p-4 py-8">
        <div class="w-full max-w-md">

            <!-- Role Selector Tabs -->
            <div class="bg-white/10 rounded-2xl p-1 flex gap-1 mb-6 border border-white/10">
                <button type="button" id="tab-siswa" onclick="switchTab('siswa')" class="role-tab active flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-user-graduate text-emerald-400"></i> Siswa
                </button>
                <button type="button" id="tab-guru" onclick="switchTab('guru')" class="role-tab flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-chalkboard-user text-blue-400"></i> Guru
                </button>
                <button type="button" id="tab-operator" onclick="switchTab('operator')" class="role-tab flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-tv text-amber-400"></i> Operator
                </button>
                <button type="button" id="tab-admin" onclick="switchTab('admin')" class="role-tab flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-crown text-purple-400"></i> Admin
                </button>
            </div>

            <!-- Login Card -->
            <div class="bg-white rounded-3xl shadow-2xl border border-white/10 overflow-hidden">

                <!-- Dynamic Header -->
                <div id="cardHeader" class="bg-gradient-to-r from-emerald-600 to-teal-500 p-6 text-white text-center">
                    <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center mx-auto mb-3 text-3xl" id="headerIcon">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <h2 class="text-xl font-black" id="headerTitle">Portal Siswa Peserta Ujian</h2>
                    <p class="text-white/80 text-xs mt-1" id="headerSubtitle">Masukkan NISN atau Nomor Peserta dan Password Anda</p>
                </div>

                <!-- Form -->
                <div class="p-7 space-y-4">
                    <?php if (!empty($error)): ?>
                        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-3">
                            <i class="fa-solid fa-triangle-exclamation text-rose-500 flex-shrink-0"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php render_flash(); ?>

                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2" id="usernameLabel">
                                NISN / No. Peserta Ujian
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400" id="usernameIcon">
                                    <i class="fa-solid fa-id-badge"></i>
                                </span>
                                <input type="text" name="username" id="usernameInput"
                                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                    required autofocus
                                    placeholder="Contoh: 0061234501"
                                    class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                                <input type="password" name="password" id="passwordInput"
                                    required placeholder="Masukkan password..."
                                    class="w-full pl-11 pr-11 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition">
                                <button type="button" onclick="togglePass()" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-slate-600">
                                    <i class="fa-solid fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="pt-1">
                            <button type="submit" id="submitBtn" class="w-full py-3.5 px-6 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-700 hover:to-teal-600 text-white font-bold rounded-xl transition duration-200 shadow-lg flex items-center justify-center text-sm">
                                <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i>
                                <span id="submitText">Masuk & Konfirmasi Ujian</span>
                            </button>
                        </div>
                    </form>

                    <!-- Dynamic Hint Box -->
                    <div id="hintBox" class="mt-1 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                        <strong class="block font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Panduan Login Siswa</strong>
                        Gunakan <strong>NISN</strong> atau <strong>Nomor Peserta</strong>. Password default: <code class="bg-white px-1 rounded font-mono">siswa123</code>
                    </div>

                    <div class="text-center text-[11px] text-slate-400 pt-2 border-t border-slate-100">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Sistem mendeteksi peran Anda secara otomatis
                    </div>
                </div>
            </div>

            <!-- Demo Accounts -->
            <div class="mt-5 bg-white/5 rounded-2xl border border-white/10 p-4 text-xs text-white/70">
                <div class="font-bold text-white/90 mb-2 flex items-center">
                    <i class="fa-solid fa-key mr-1.5 text-amber-400"></i> Akun Demo Default:
                </div>
                <div class="grid grid-cols-2 gap-1.5 font-mono text-[11px]">
                    <div class="bg-white/5 rounded-lg px-2.5 py-1.5"><span class="text-purple-300">admin</span> / admin123</div>
                    <div class="bg-white/5 rounded-lg px-2.5 py-1.5"><span class="text-blue-300">guru</span> / guru123</div>
                    <div class="bg-white/5 rounded-lg px-2.5 py-1.5"><span class="text-amber-300">operator</span> / operator123</div>
                    <div class="bg-white/5 rounded-lg px-2.5 py-1.5"><span class="text-emerald-300">0061234501</span> / siswa123</div>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-4 text-xs text-indigo-300/40 border-t border-white/5">
        &copy; <?= date('Y') ?> <?= e($schoolName) ?> &bull; <?= APP_NAME ?> v<?= APP_VERSION ?>
    </footer>

<script>
const tabConfig = {
    'siswa': {
        headerGrad: 'from-emerald-600 to-teal-500',
        icon: 'fa-user-graduate',
        title: 'Portal Siswa Peserta Ujian',
        subtitle: 'Masukkan NISN atau Nomor Peserta dan Password Anda',
        userLabel: 'NISN / No. Peserta Ujian',
        userIcon: 'fa-id-badge',
        placeholder: 'Contoh: 0061234501',
        btnClass: 'from-emerald-600 to-teal-500 hover:from-emerald-700 hover:to-teal-600',
        btnText: 'Masuk & Konfirmasi Ujian',
        hintClass: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        hint: '<strong class="block font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Panduan Login Siswa</strong>Gunakan <strong>NISN</strong> atau <strong>Nomor Peserta</strong>. Password default: <code class="bg-white px-1 rounded font-mono">siswa123</code>'
    },
    'guru': {
        headerGrad: 'from-blue-600 to-indigo-600',
        icon: 'fa-chalkboard-user',
        title: 'Portal Guru Pengajar',
        subtitle: 'Kelola bank soal, analisis butir soal, dan rekap nilai',
        userLabel: 'Username Guru',
        userIcon: 'fa-user',
        placeholder: 'Masukkan username guru...',
        btnClass: 'from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700',
        btnText: 'Masuk Sebagai Guru',
        hintClass: 'bg-blue-50 border-blue-200 text-blue-800',
        hint: '<strong class="block font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Panduan Login Guru</strong>Akses: Bank Soal, Import Soal Word, Analisis, dan Rekap Nilai. Password diberikan oleh Administrator.'
    },
    'operator': {
        headerGrad: 'from-amber-600 to-orange-500',
        icon: 'fa-tv',
        title: 'Portal Proktor / Operator',
        subtitle: 'Monitor ujian berjalan dan generate token peserta',
        userLabel: 'Username Operator',
        userIcon: 'fa-user-gear',
        placeholder: 'Masukkan username operator...',
        btnClass: 'from-amber-600 to-orange-500 hover:from-amber-700 hover:to-orange-600',
        btnText: 'Masuk Sebagai Operator',
        hintClass: 'bg-amber-50 border-amber-200 text-amber-800',
        hint: '<strong class="block font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Panduan Login Operator</strong>Pantau peserta ujian secara live, atur token ujian, dan cetak kartu peserta.'
    },
    'admin': {
        headerGrad: 'from-purple-700 to-violet-700',
        icon: 'fa-crown',
        title: 'Portal Administrator',
        subtitle: 'Manajemen penuh sistem CBT seluruh jenjang',
        userLabel: 'Username Admin',
        userIcon: 'fa-user-shield',
        placeholder: 'Masukkan username admin...',
        btnClass: 'from-purple-700 to-violet-700 hover:from-purple-800 hover:to-violet-800',
        btnText: 'Masuk Sebagai Admin',
        hintClass: 'bg-purple-50 border-purple-200 text-purple-800',
        hint: '<strong class="block font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Panduan Login Admin</strong>Akses penuh: manajemen pengguna, kelas, mapel, siswa, jadwal ujian, dan pengaturan sistem.'
    }
};

function switchTab(role) {
    const cfg = tabConfig[role];
    // Update tabs
    ['siswa','guru','operator','admin'].forEach(r => document.getElementById('tab-'+r).classList.remove('active'));
    document.getElementById('tab-'+role).classList.add('active');
    // Header
    const hdr = document.getElementById('cardHeader');
    hdr.className = 'bg-gradient-to-r ' + cfg.headerGrad + ' p-6 text-white text-center';
    document.getElementById('headerIcon').innerHTML = '<i class="fa-solid ' + cfg.icon + '"></i>';
    document.getElementById('headerTitle').innerText = cfg.title;
    document.getElementById('headerSubtitle').innerText = cfg.subtitle;
    // Form
    document.getElementById('usernameLabel').innerText = cfg.userLabel;
    document.getElementById('usernameIcon').innerHTML = '<i class="fa-solid ' + cfg.userIcon + '"></i>';
    document.getElementById('usernameInput').placeholder = cfg.placeholder;
    // Button
    const btn = document.getElementById('submitBtn');
    btn.className = 'w-full py-3.5 px-6 bg-gradient-to-r ' + cfg.btnClass + ' text-white font-bold rounded-xl transition duration-200 shadow-lg flex items-center justify-center text-sm';
    document.getElementById('submitText').innerText = cfg.btnText;
    // Hint
    const hint = document.getElementById('hintBox');
    hint.className = 'mt-1 p-3.5 rounded-xl border text-xs ' + cfg.hintClass;
    hint.innerHTML = cfg.hint;
    // Focus
    setTimeout(() => document.getElementById('usernameInput').focus(), 50);
}

function togglePass() {
    const inp = document.getElementById('passwordInput');
    const eye = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; eye.classList.replace('fa-eye','fa-eye-slash'); }
    else { inp.type = 'password'; eye.classList.replace('fa-eye-slash','fa-eye'); }
}

// Auto-select tab from hash
document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#','');
    if (['admin','guru','operator','siswa'].includes(hash)) switchTab(hash);
});
</script>
</body>
</html>
