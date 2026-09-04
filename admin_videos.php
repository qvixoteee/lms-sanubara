<?php
session_start();
include_once 'config/Database.php';
$database = new Database();
$db = $database->getConnection();

// Validasi ketat: Hanya departemen 'Manajemen' yang boleh masuk
$departemenUser = $_SESSION['user']['departemen'] ?? '';
if (!isset($_SESSION['user']) || strtolower(trim($departemenUser)) !== 'manajemen') {
    header("Location: index.php?error=access_denied");
    exit;
}

// Ambil daftar jabatan unik dari tabel users
$stmtJabatan = $db->prepare("SELECT DISTINCT jabatan FROM users WHERE jabatan IS NOT NULL AND jabatan != '' ORDER BY jabatan ASC");
$stmtJabatan->execute();
$listJabatan = $stmtJabatan->fetchAll(PDO::FETCH_COLUMN);

$successMsg = '';
$errorMsg = '';

// 1. Proses Hapus Video
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idHapus = $_GET['id'];
    try {
        $stmtDel = $db->prepare("DELETE FROM videos WHERE id_video = :id");
        $stmtDel->execute([':id' => $idHapus]);
        header("Location: admin_videos.php?status=success_delete");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus video karena sedang digunakan sebagai prasyarat.";
    }
}

// 2. Proses Tambah atau Update Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_video'])) {
    $idVideo = trim($_POST['id_video'] ?? '');
    $idVideoLama = trim($_POST['id_video_lama'] ?? ''); // Untuk mode edit
    $judul = trim($_POST['judul_video'] ?? '');
    $kategori = trim($_POST['kategori'] ?? 'Umum');
    $urlVideo = trim($_POST['url_video'] ?? '');
    $durasiMenit = !empty($_POST['durasi_menit']) ? $_POST['durasi_menit'] : null;
    $aksesJabatan = isset($_POST['akses_jabatan']) ? implode(', ', $_POST['akses_jabatan']) : 'Semua Jabatan';
    $status = 'Aktif';

    if (!empty($idVideo) && !empty($judul)) {
        if (!empty($idVideoLama)) {
            // Mode Update / Ubah
            try {
                $stmtUpdate = $db->prepare("UPDATE videos SET id_video = :new_id, judul_video = :judul, kategori = :kategori, akses_jabatan = :akses, url_video = :url, durasi_menit = :durasi WHERE id_video = :old_id");
                $stmtUpdate->execute([
                    ':new_id' => $idVideo,
                    ':judul' => $judul,
                    ':kategori' => $kategori,
                    ':akses' => $aksesJabatan,
                    ':url' => $urlVideo,
                    ':durasi' => $durasiMenit,
                    ':old_id' => $idVideoLama
                ]);
                header("Location: admin_videos.php?status=success_update");
                exit;
            } catch (PDOException $e) {
                $errorMsg = "Gagal memperbarui: ID Video '$idVideo' mungkin sudah digunakan.";
            }
        } else {
            // Mode Tambah Baru
            try {
                $stmtInsert = $db->prepare("INSERT INTO videos (id_video, judul_video, kategori, akses_jabatan, url_video, durasi_menit, status) VALUES (:id_video, :judul, :kategori, :akses, :url, :durasi, :status)");
                $stmtInsert->execute([
                    ':id_video' => $idVideo,
                    ':judul' => $judul,
                    ':kategori' => $kategori,
                    ':akses' => $aksesJabatan,
                    ':url' => $urlVideo,
                    ':durasi' => $durasiMenit,
                    ':status' => $status
                ]);
                $successMsg = "Modul video berhasil ditambahkan!";
            } catch (PDOException $e) {
                $errorMsg = "Gagal menyimpan: ID Video '$idVideo' sudah terdaftar.";
            }
        }
    } else {
        $errorMsg = "ID Video dan Judul wajib diisi.";
    }
}

// Cek jika sedang mode edit (mengambil data video yang akan diubah)
$editData = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmtEdit = $db->prepare("SELECT * FROM videos WHERE id_video = :id LIMIT 1");
    $stmtEdit->execute([':id' => $_GET['id']]);
    $editData = $stmtEdit->fetch(PDO::FETCH_ASSOC);
}

// Ambil daftar video
$stmtVideos = $db->prepare("SELECT * FROM videos ORDER BY id_video DESC");
$stmtVideos->execute();
$videos = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['user']['name'] ?? 'HRD Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Modul Video - Sanubara Learning Center</title>
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
                    <a href="admin_videos.php" class="w-full text-left px-4 py-3 rounded-2xl bg-white/25 font-bold text-white font-medium transition flex items-center gap-3">
                        • Kelola Modul Video
                    </a>
                    <a href="admin_exams.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        • Kelola Paket Ujian & Soal
                    </a>
                    <a href="admin_users.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
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
                <h1 class="text-xl sm:text-2xl font-black text-gray-900">Manajemen Modul Video</h1>
                <p class="text-xs text-gray-500 mt-0.5">Tambah, ubah, dan kelola materi pembelajaran video.</p>
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

        <!-- Form Tambah / Edit Video -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-sm font-bold text-gray-900">
                    <?= $editData ? 'Edit Modul Video: ' . htmlspecialchars($editData['judul_video']) : 'Tambah Modul Video Baru'; ?>
                </h2>
                <?php if ($editData): ?>
                    <a href="admin_videos.php" class="text-xs font-bold text-rose-600 hover:underline">Batal Edit</a>
                <?php endif; ?>
            </div>
            
            <form action="" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <input type="hidden" name="id_video_lama" value="<?= htmlspecialchars($editData['id_video'] ?? ''); ?>">

                <div>
                    <label class="block font-bold text-gray-700 mb-1">ID Video</label>
                    <input type="text" name="id_video" value="<?= htmlspecialchars($editData['id_video'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: VID-003">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kategori</label>
                    <input type="text" name="kategori" value="<?= htmlspecialchars($editData['kategori'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: On Boarding">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 mb-1">Judul Video</label>
                    <input type="text" name="judul_video" value="<?= htmlspecialchars($editData['judul_video'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: Modul 3">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Durasi Video (Menit)</label>
                    <input type="number" name="durasi_menit" value="<?= htmlspecialchars($editData['durasi_menit'] ?? ''); ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: 25">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Tautan URL Video</label>
                    <input type="text" name="url_video" value="<?= htmlspecialchars($editData['url_video'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="https://...">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 mb-1">Akses Jabatan</label>
                    <div class="flex flex-wrap gap-4 mt-2">
                        <?php 
                        $currentAkses = $editData['akses_jabatan'] ?? 'Semua Jabatan';
                        foreach ($listJabatan as $jab): 
                            $checked = (strpos($currentAkses, $jab) !== false) ? 'checked' : '';
                        ?>
                            <label class="flex items-center gap-2 font-medium text-gray-700 bg-gray-50 px-3 py-2 rounded-xl border border-gray-200">
                                <input type="checkbox" name="akses_jabatan[]" value="<?= htmlspecialchars($jab); ?>" <?= $checked; ?> class="accent-orange-500"> 
                                <?= htmlspecialchars($jab); ?>
                            </label>
                        <?php endforeach; ?>
                        <label class="flex items-center gap-2 font-medium text-orange-700 bg-orange-50 px-3 py-2 rounded-xl border border-orange-200">
                            <input type="checkbox" name="akses_jabatan[]" value="Semua Jabatan" <?= (strpos($currentAkses, 'Semua Jabatan') !== false) ? 'checked' : ''; ?> class="accent-orange-500"> 
                            Semua Jabatan
                        </label>
                    </div>
                </div>
                <div class="sm:col-span-2 pt-2">
                    <button type="submit" name="simpan_video" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-3 rounded-xl transition shadow-sm">
                        <?= $editData ? 'Simpan Perubahan Video' : 'Simpan & Publikasikan Video'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Video Aktif -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-sm font-bold text-gray-900">Daftar Modul Video Tersedia</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                            <th class="py-3 px-4">ID Video</th>
                            <th class="py-3 px-4">Judul Video</th>
                            <th class="py-3 px-4">Kategori</th>
                            <th class="py-3 px-4">Durasi</th>
                            <th class="py-3 px-4">Akses Jabatan</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($videos) > 0): ?>
                            <?php foreach ($videos as $v): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3.5 px-4 font-bold text-orange-600"><?= htmlspecialchars($v['id_video']); ?></td>
                                <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($v['judul_video']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($v['kategori']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= $v['durasi_menit'] ? $v['durasi_menit'] . ' Menit' : '-'; ?></td>
                                <td class="py-3.5 px-4 text-gray-600 font-medium"><?= htmlspecialchars($v['akses_jabatan']); ?></td>
                                <td class="py-3.5 px-4 text-center space-x-2">
                                    <a href="admin_videos.php?action=edit&id=<?= urlencode($v['id_video']); ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-xl transition inline-block">Ubah</a>
                                    <a href="admin_videos.php?action=delete&id=<?= urlencode($v['id_video']); ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus video ini?')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-3 py-1.5 rounded-xl transition inline-block">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400">Belum ada modul video yang ditambahkan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>