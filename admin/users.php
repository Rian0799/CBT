<?php
// admin/users.php - User Management (Admin, Guru, Operator)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? 'guru');
        $nip = trim($_POST['nip'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($username && $password && $fullName) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, nip, email, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $fullName, $role, $nip, $email, $phone]);
                set_flash('success', "Pengguna baru <strong>" . e($fullName) . "</strong> berhasil ditambahkan sebagai " . ucfirst($role) . ".");
            } catch (PDOException $e) {
                set_flash('error', "Gagal menambah pengguna: Username sudah digunakan.");
            }
        } else {
            set_flash('error', "Username, password, dan nama lengkap wajib diisi.");
        }
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        // Cegah menghapus diri sendiri
        if ($userId === Auth::user()['id']) {
            set_flash('error', "Anda tidak dapat menghapus akun Anda sendiri.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            set_flash('success', "Pengguna berhasil dihapus.");
        }
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '123456');
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        set_flash('success', "Password pengguna berhasil direset menjadi: <code>" . e($newPass) . "</code>");
        redirect(BASE_URL . '/admin/users.php');
    }
}

// Ambil daftar pengguna
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC")->fetchAll();

$pageTitle = 'Manajemen Pengguna';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Manajemen Pengguna</h1>
            <p class="text-sm text-slate-500">Kelola akun Administrator, Guru Pengajar, dan Operator CBT.</p>
        </div>
        <button onclick="document.getElementById('modalAddUser').classList.remove('hidden')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-200 transition">
            <i class="fa-solid fa-user-plus mr-2"></i> Tambah Pengguna Baru
        </button>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3.5 px-4">Nama Lengkap & NIP</th>
                        <th class="py-3.5 px-4">Username</th>
                        <th class="py-3.5 px-4">Peran (Role)</th>
                        <th class="py-3.5 px-4">Kontak</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800 text-sm"><?= e($u['full_name']) ?></div>
                                <div class="text-slate-400 font-mono text-[11px]"><?= e($u['nip'] ?: 'NIP: -') ?></div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-medium text-slate-700">
                                @<?= e($u['username']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-100 text-purple-700 border border-purple-200">
                                        <i class="fa-solid fa-crown mr-1"></i> Admin
                                    </span>
                                <?php elseif ($u['role'] === 'guru'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                        <i class="fa-solid fa-chalkboard-user mr-1"></i> Guru
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                                        <i class="fa-solid fa-headset mr-1"></i> Operator
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <div><?= e($u['email'] ?: '-') ?></div>
                                <div class="text-[11px] text-slate-400"><?= e($u['phone'] ?: '') ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="inline-flex items-center space-x-2">
                                    <button onclick="promptResetPassword(<?= $u['id'] ?>, '<?= e($u['username']) ?>')" title="Reset Password" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <?php if ($u['id'] !== Auth::user()['id']): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" title="Hapus Pengguna" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div id="modalAddUser" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-slate-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-user-plus text-indigo-600 mr-2.5"></i> Tambah Pengguna Baru
            </h3>
            <button onclick="document.getElementById('modalAddUser').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Username *</label>
                    <input type="text" name="username" required placeholder="Contoh: guru_math" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Password *</label>
                    <input type="password" name="password" required placeholder="Minimal 6 karakter" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase mb-1">Nama Lengkap & Gelar *</label>
                <input type="text" name="full_name" required placeholder="Contoh: Ahmad Subardjo, S.Pd." class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Peran (Role) *</label>
                    <select name="role" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                        <option value="guru">Guru (Upload Soal, Analisis & Nilai)</option>
                        <option value="operator">Operator (Cek Siswa, Monitoring & Token)</option>
                        <option value="admin">Administrator (Full Management)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">NIP (Opsional)</label>
                    <input type="text" name="nip" placeholder="Nomor Induk Pegawai" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Email</label>
                    <input type="email" name="email" placeholder="nama@sekolah.sch.id" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">No. HP / WhatsApp</label>
                    <input type="text" name="phone" placeholder="0812xxxx" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-xs">
                </div>
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('modalAddUser').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow shadow-indigo-200">
                    Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Form for Reset Password -->
<form id="formResetPassword" method="POST" action="" class="hidden">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="user_id" id="resetUserId">
    <input type="hidden" name="new_password" id="resetNewPass">
</form>

<script>
function promptResetPassword(userId, username) {
    Swal.fire({
        title: 'Reset Password @' + username,
        input: 'text',
        inputLabel: 'Masukkan password baru:',
        inputValue: 'guru123',
        showCancelButton: true,
        confirmButtonText: 'Reset Sekarang',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#4f46e5',
        inputValidator: (value) => {
            if (!value) {
                return 'Password baru tidak boleh kosong!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetNewPass').value = result.value;
            document.getElementById('formResetPassword').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
