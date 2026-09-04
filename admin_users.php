<?php
session_start();
include_once 'config/Database.php';

// Validasi akses: Hanya departemen 'Manajemen' yang boleh masuk
$departemenUser = $_SESSION['user']['departemen'] ?? '';
if (!isset($_SESSION['user']) || strtolower(trim($departemenUser)) !== 'manajemen') {
    header("Location: index.php?error=access_denied");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$successMsg = '';
$errorMsg = '';

// 1. Proses Hapus User
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idHapus = $_GET['id'];
    try {
        // Jangan biarkan admin menghapus akunnya sendiri yang sedang aktif
        if ($idHapus == $_SESSION['user']['id']) {
            $errorMsg = "Anda tidak dapat menghapus akun yang sedang digunakan saat ini.";
        } else {
            $stmtDel = $db->prepare("DELETE FROM users WHERE id_user = :id");
            $stmtDel->execute([':id' => $idHapus]);
            header("Location: admin_users.php?status=success_delete");
            exit;
        }
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus user: " . $e->getMessage();
    }
}

// 2. Proses Tambah atau Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_user'])) {
    $idUserLama = trim($_POST['id_user_lama'] ?? '');
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $departemen = trim($_POST['departemen'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $pabrik = trim($_POST['pabrik'] ?? '');
    $statusKaryawan = trim($_POST['status_karyawan'] ?? 'Aktif');
    $tanggalBergabung = trim($_POST['tanggal_bergabung'] ?? date('Y-m-d'));

    if (!empty($namaLengkap) && !empty($username)) {
        if (!empty($idUserLama)) {
            // Mode Update User
            try {
                if (!empty($password)) {
                    // Jika password diisi, update beserta passwordnya
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUpdate = $db->prepare("UPDATE users SET nama_lengkap = :nama, email = :email, username = :username, password = :pass, departemen = :dept, jabatan = :jab, pabrik = :pabrik, status_karyawan = :status, tanggal_bergabung = :tgl WHERE id_user = :id");
                    $stmtUpdate->execute([
                        ':nama' => $namaLengkap,
                        ':email' => $email,
                        ':username' => $username,
                        ':pass' => $hashedPassword,
                        ':dept' => $departemen,
                        ':jab' => $jabatan,
                        ':pabrik' => $pabrik,
                        ':status' => $statusKaryawan,
                        ':tgl' => $tanggalBergabung,
                        ':id' => $idUserLama
                    ]);
                } else {
                    // Jika password kosong, jangan ubah password
                    $stmtUpdate = $db->prepare("UPDATE users SET nama_lengkap = :nama, email = :email, username = :username, departemen = :dept, jabatan = :jab, pabrik = :pabrik, status_karyawan = :status, tanggal_bergabung = :tgl WHERE id_user = :id");
                    $stmtUpdate->execute([
                        ':nama' => $namaLengkap,
                        ':email' => $email,
                        ':username' => $username,
                        ':dept' => $departemen,
                        ':jab' => $jabatan,
                        ':pabrik' => $pabrik,
                        ':status' => $statusKaryawan,
                        ':tgl' => $tanggalBergabung,
                        ':id' => $idUserLama
                    ]);
                }
                header("Location: admin_users.php?status=success_update");
                exit;
            } catch (PDOException $e) {
                $errorMsg = "Gagal memperbarui: Username atau email mungkin sudah digunakan.";
            }
        } else {
            // Mode Tambah User Baru
            try {
                $hashedPassword = password_hash(!empty($password) ? $password : 'password123', PASSWORD_DEFAULT);
                $stmtInsert = $db->prepare("INSERT INTO users (nama_lengkap, email, username, password, departemen, jabatan, pabrik, status_karyawan, tanggal_bergabung) VALUES (:nama, :email, :username, :pass, :dept, :jab, :pabrik, :status, :tgl)");
                $stmtInsert->execute([
                    ':nama' => $namaLengkap,
                    ':email' => $email,
                    ':username' => $username,
                    ':pass' => $hashedPassword,
                    ':dept' => $departemen,
                    ':jab' => $jabatan,
                    ':pabrik' => $pabrik,
                    ':status' => $statusKaryawan,
                    ':tgl' => $tanggalBergabung
                ]);
                $successMsg = "Karyawan baru berhasil ditambahkan!";
            } catch (PDOException $e) {
                $errorMsg = "Gagal menyimpan: Username atau email sudah terdaftar.";
            }
        }
    } else {
        $errorMsg = "Nama lengkap dan username wajib diisi.";
    }
}

// Cek Mode Edit
$editUser = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmtEdit = $db->prepare("SELECT * FROM users WHERE id_user = :id LIMIT 1");
    $stmtEdit->execute([':id' => $_GET['id']]);
    $editUser = $stmtEdit->fetch(PDO::FETCH_ASSOC);
}

// Ambil daftar seluruh user
$stmtUsers = $db->prepare("SELECT * FROM users ORDER BY id_user DESC");
$stmtUsers->execute();
$usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['user']['name'] ?? 'HRD Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans flex text-gray-800">

    <!-- SIDEBAR KIRI -->
    <aside class="w-64 bg-orange-600 min-h-screen p-6 flex flex-col justify-between text-white hidden md:flex shadow-lg">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-2xl bg-white/20 flex items-center justify-center font-extrabold text-lg">S</div>
                <div>
                    <h2 class="font-bold text-sm tracking-wider">SANUBARA</h2>
                    <p class="text-[10px] text-orange-200">Learning Center</p>
                </div>
            </div>

            <div class="bg-orange-700/50 p-4 rounded-2xl flex items-center gap-3 border border-orange-500/30">
                <div class="w-10 h-10 rounded-full bg-white text-orange-600 font-bold flex items-center justify-center text-sm">
                    <?= strtoupper(substr($adminName, 0, 1)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold truncate"><?= htmlspecialchars($adminName); ?></p>
                    <p class="text-[10px] text-orange-200">Administrator / HRD</p>
                </div>
            </div>

            <div>
                <p class="text-[10px] font-bold text-orange-200 uppercase tracking-wider mb-2 px-1">Navigasi Sistem</p>
                <nav class="space-y-1.5 text-xs">
                    <a href="admin_dashboard.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        ← Dashboard Utama
                    </a>
                    <a href="admin_videos.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        • Kelola Modul Video
                    </a>
                    <a href="admin_exams.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        • Kelola Paket Ujian & Soal
                    </a>
                    <a href="admin_users.php" class="w-full text-left px-4 py-3 rounded-2xl bg-white/25 font-bold text-white  font-medium transition flex items-center gap-3">
                        • Kelola Pengguna
                    </a>
                </nav>
            </div>
        </div>

        <div>
            <a href="logout.php" class="w-full bg-orange-700/60 hover:bg-orange-700 text-rose-200 text-xs font-bold px-4 py-3 rounded-2xl transition flex items-center justify-center gap-2 border border-orange-500/30">
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="flex-1 p-4 sm:p-8 overflow-y-auto space-y-6">
        
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900">Manajemen Pengguna (Karyawan & Admin)</h1>
                <p class="text-xs text-gray-500 mt-0.5">Tambah, ubah, dan kelola data akun akses pengguna sistem.</p>
            </div>
            <a href="admin_dashboard.php" class="md:hidden bg-white text-orange-600 border border-orange-200 text-xs font-bold px-3 py-2 rounded-xl">
                ← Dashboard
            </a>
        </div>

        <?php if ($successMsg || isset($_GET['status'])): ?>
            <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs p-4 rounded-2xl font-medium">
                ✅ <?= $successMsg ?: "Operasi berhasil dilakukan!"; ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="bg-rose-50 text-rose-800 border border-rose-200 text-xs p-4 rounded-2xl font-medium">
                ❌ <?= $errorMsg; ?>
            </div>
        <?php endif; ?>

        <!-- Form Tambah / Edit User -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-sm font-bold text-gray-900">
                    <?= $editUser ? 'Edit Data Pengguna: ' . htmlspecialchars($editUser['nama_lengkap']) : 'Tambah Pengguna Baru'; ?>
                </h2>
                <?php if ($editUser): ?>
                    <a href="admin_users.php" class="text-xs font-bold text-rose-600 hover:underline">Batal Edit</a>
                <?php endif; ?>
            </div>
            
            <form action="" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <input type="hidden" name="id_user_lama" value="<?= htmlspecialchars($editUser['id_user'] ?? ''); ?>">

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($editUser['nama_lengkap'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: Budi Santoso">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? ''); ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="budi@email.com">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Username Login</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($editUser['username'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="budi123">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Password <?= $editUser ? '(Kosongkan jika tidak diubah)' : ''; ?></label>
                    <input type="password" name="password" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="••••••••">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Departemen</label>
                    <input type="text" name="departemen" value="<?= htmlspecialchars($editUser['departemen'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Manajemen / Produksi">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Jabatan</label>
                    <input type="text" name="jabatan" value="<?= htmlspecialchars($editUser['jabatan'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Sekretaris / Staff">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Pabrik</label>
                    <input type="text" name="pabrik" value="<?= htmlspecialchars($editUser['pabrik'] ?? ''); ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Pabrik A">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Status Karyawan</label>
                    <select name="status_karyawan" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500 font-medium">
                        <option value="Aktif" <?= (($editUser['status_karyawan'] ?? '') === 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="Nonaktif" <?= (($editUser['status_karyawan'] ?? '') === 'Nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Tanggal Bergabung</label>
                    <input type="date" name="tanggal_bergabung" value="<?= htmlspecialchars($editUser['tanggal_bergabung'] ?? date('Y-m-d')); ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500">
                </div>

                <div class="sm:col-span-3 pt-2">
                    <button type="submit" name="simpan_user" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-3 rounded-xl transition shadow-sm">
                        <?= $editUser ? 'Simpan Perubahan Data Pengguna' : 'Simpan & Daftarkan Karyawan'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Pengguna -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-sm font-bold text-gray-900">Daftar Pengguna Terdaftar</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Nama Lengkap</th>
                            <th class="py-3 px-4">Username</th>
                            <th class="py-3 px-4">Departemen</th>
                            <th class="py-3 px-4">Jabatan</th>
                            <th class="py-3 px-4">Pabrik</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($usersList) > 0): ?>
                            <?php foreach ($usersList as $u): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($u['nama_lengkap']); ?></td>
                                <td class="py-3.5 px-4 text-orange-600 font-semibold"><?= htmlspecialchars($u['username']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($u['departemen']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($u['jabatan']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($u['pabrik'] ?? '-'); ?></td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-full font-bold text-[10px] <?= ($u['status_karyawan'] === 'Aktif') ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>">
                                        <?= htmlspecialchars($u['status_karyawan'] ?? 'Aktif'); ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center space-x-1">
                                    <a href="admin_users.php?action=edit&id=<?= $u['id_user']; ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-2.5 py-1.5 rounded-xl transition inline-block">Ubah</a>
                                    <a href="admin_users.php?action=delete&id=<?= $u['id_user']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-2.5 py-1.5 rounded-xl transition inline-block">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400">Belum ada data pengguna.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>