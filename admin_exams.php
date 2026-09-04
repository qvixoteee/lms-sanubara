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

// Ambil daftar jabatan unik
$stmtJabatan = $db->prepare("SELECT DISTINCT jabatan FROM users WHERE jabatan IS NOT NULL AND jabatan != '' ORDER BY jabatan ASC");
$stmtJabatan->execute();
$listJabatan = $stmtJabatan->fetchAll(PDO::FETCH_COLUMN);

// Ambil daftar video aktif untuk prerequisite
$stmtVid = $db->prepare("SELECT id_video, judul_video FROM videos WHERE status = 'Aktif' ORDER BY judul_video ASC");
$stmtVid->execute();
$listVideos = $stmtVid->fetchAll(PDO::FETCH_ASSOC);

$successMsg = '';
$errorMsg = '';

// 1. Proses Hapus Ujian (Beserta relasi bank_soal dan nilai_kuis)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idHapus = $_GET['id'];
    try {
        // Hapus soal di bank soal yang terikat dengan ujian ini
        $stmtDelSoal = $db->prepare("DELETE FROM bank_soal WHERE id_ujian = :id");
        $stmtDelSoal->execute([':id' => $idHapus]);

        // Hapus nilai kuis karyawan yang terikat dengan ujian ini
        $stmtDelNilai = $db->prepare("DELETE FROM nilai_kuis WHERE id_ujian = :id");
        $stmtDelNilai->execute([':id' => $idHapus]);

        // Terakhir, hapus paket ujian utamanya
        $stmtDel = $db->prepare("DELETE FROM exams WHERE id_ujian = :id");
        $stmtDel->execute([':id' => $idHapus]);

        header("Location: admin_exams.php?status=success_delete");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus ujian: " . $e->getMessage();
    }
}

// 2. Proses Tambah atau Update Ujian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_ujian'])) {
    $idUjian = trim($_POST['id_ujian'] ?? '');
    $idUjianLama = trim($_POST['id_ujian_lama'] ?? '');
    $namaUjian = trim($_POST['nama_ujian'] ?? '');
    $aksesJabatan = isset($_POST['akses_jabatan']) ? implode(', ', $_POST['akses_jabatan']) : 'Semua Jabatan';
    $prerequisiteVideoId = isset($_POST['prerequisite_videos']) ? implode(', ', $_POST['prerequisite_videos']) : null;
    $passingGrade = $_POST['passing_grade'] ?? 75;
    $durasiMenit = !empty($_POST['durasi_menit']) ? $_POST['durasi_menit'] : null;
    $jumlahSoalPerVideo = !empty($_POST['jumlah_soal_per_video']) ? intval($_POST['jumlah_soal_per_video']) : 5;
    $status = 'Aktif';

    if (!empty($namaUjian)) {
        if (!empty($idUjianLama)) {
            // Mode Update
            $stmtUpdate = $db->prepare("UPDATE exams SET nama_ujian = :nama, akses_jabatan = :akses, prerequisite_video_id = :prereq, passing_grade = :pg, durasi_menit = :durasi, jumlah_soal_per_video = :jml_soal WHERE id_ujian = :id");
            $stmtUpdate->execute([
                ':nama' => $namaUjian,
                ':akses' => $aksesJabatan,
                ':prereq' => $prerequisiteVideoId,
                ':pg' => $passingGrade,
                ':durasi' => $durasiMenit,
                ':jml_soal' => $jumlahSoalPerVideo,
                ':id' => $idUjianLama
            ]);
            header("Location: admin_exams.php?status=success_update");
            exit;
        } else {
            // Mode Tambah Baru
            $newIdUjian = 'EXAM_' . time();
            $stmtInsert = $db->prepare("INSERT INTO exams (id_ujian, nama_ujian, akses_jabatan, prerequisite_video_id, passing_grade, durasi_menit, jumlah_soal_per_video, status) VALUES (:id, :nama, :akses, :prereq, :pg, :durasi, :jml_soal, :status)");
            $stmtInsert->execute([
                ':id' => $newIdUjian,
                ':nama' => $namaUjian,
                ':akses' => $aksesJabatan,
                ':prereq' => $prerequisiteVideoId,
                ':pg' => $passingGrade,
                ':durasi' => $durasiMenit,
                ':jml_soal' => $jumlahSoalPerVideo,
                ':status' => $status
            ]);
            $successMsg = "Paket ujian baru berhasil ditambahkan!";
        }
    } else {
        $errorMsg = "Nama ujian wajib diisi.";
    }
}

// Cek Mode Edit Ujian
$editExam = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmtEdit = $db->prepare("SELECT * FROM exams WHERE id_ujian = :id LIMIT 1");
    $stmtEdit->execute([':id' => $_GET['id']]);
    $editExam = $stmtEdit->fetch(PDO::FETCH_ASSOC);
}

// Ambil daftar ujian
$stmtExams = $db->prepare("SELECT * FROM exams ORDER BY id_ujian DESC");
$stmtExams->execute();
$exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['user']['name'] ?? 'HRD Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Ujian & Soal - Sanubara Learning Center</title>
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
                    <a href="admin_exams.php" class="w-full text-left px-4 py-3 rounded-2xl bg-white/25 font-bold text-white font-medium transition flex items-center gap-3">
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
                <h1 class="text-xl sm:text-2xl font-black text-gray-900">Manajemen Ujian & Evaluasi</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola paket ujian, durasi, syarat video, dan passing grade.</p>
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

        <!-- Form Tambah / Edit Ujian -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-sm font-bold text-gray-900">
                    <?= $editExam ? 'Edit Paket Ujian: ' . htmlspecialchars($editExam['nama_ujian']) : 'Buat Paket Ujian Baru'; ?>
                </h2>
                <?php if ($editExam): ?>
                    <a href="admin_exams.php" class="text-xs font-bold text-rose-600 hover:underline">Batal Edit</a>
                <?php endif; ?>
            </div>
            
            <form action="" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <input type="hidden" name="id_ujian_lama" value="<?= htmlspecialchars($editExam['id_ujian'] ?? ''); ?>">

                <div class="sm:col-span-3">
                    <label class="block font-bold text-gray-700 mb-1">Nama Ujian / Modul Asesmen</label>
                    <input type="text" name="nama_ujian" value="<?= htmlspecialchars($editExam['nama_ujian'] ?? ''); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: Ujian Akhir SOP">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Passing Grade</label>
                    <input type="number" name="passing_grade" value="<?= htmlspecialchars($editExam['passing_grade'] ?? '75'); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Durasi (Menit)</label>
                    <input type="number" name="durasi_menit" value="<?= htmlspecialchars($editExam['durasi_menit'] ?? ''); ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Bebas">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Jumlah Soal per Video</label>
                    <input type="number" name="jumlah_soal_per_video" value="<?= htmlspecialchars($editExam['jumlah_soal_per_video'] ?? '5'); ?>" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Contoh: 5">
                </div>

                <!-- Prerequisite Video -->
                <div class="sm:col-span-3">
                    <label class="block font-bold text-gray-700 mb-1">Syarat Video Terkait</label>
                    <div class="flex flex-wrap gap-3 mt-2 max-h-40 overflow-y-auto p-2 bg-gray-50 rounded-2xl border border-gray-200">
                        <?php 
                        $currentPrereq = $editExam['prerequisite_video_id'] ?? '';
                        if (count($listVideos) > 0): 
                            foreach ($listVideos as $vid): 
                                $isChecked = (strpos($currentPrereq, $vid['id_video']) !== false || strpos($currentPrereq, $vid['judul_video']) !== false) ? 'checked' : '';
                        ?>
                                <label class="flex items-center gap-2 font-medium text-gray-700 bg-white px-3 py-2 rounded-xl border border-gray-200 shadow-sm cursor-pointer hover:border-orange-300">
                                    <input type="checkbox" name="prerequisite_videos[]" value="<?= htmlspecialchars($vid['id_video']); ?>" <?= $isChecked; ?> class="accent-orange-500"> 
                                    <?= htmlspecialchars($vid['judul_video']); ?> (<?= htmlspecialchars($vid['id_video']); ?>)
                                </label>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </div>
                </div>

                <!-- Akses Jabatan -->
                <div class="sm:col-span-3">
                    <label class="block font-bold text-gray-700 mb-1">Akses Jabatan Karyawan</label>
                    <div class="flex flex-wrap gap-3 mt-2 p-2 bg-gray-50 rounded-2xl border border-gray-200">
                        <?php 
                        $currentAksesExam = $editExam['akses_jabatan'] ?? 'Semua Jabatan';
                        if (count($listJabatan) > 0): 
                            foreach ($listJabatan as $jab): 
                                $checkedExam = (strpos($currentAksesExam, $jab) !== false) ? 'checked' : '';
                        ?>
                                <label class="flex items-center gap-2 font-medium text-gray-700 bg-white px-3 py-2 rounded-xl border border-gray-200 shadow-sm cursor-pointer hover:border-orange-300">
                                    <input type="checkbox" name="akses_jabatan[]" value="<?= htmlspecialchars($jab); ?>" <?= $checkedExam; ?> class="accent-orange-500"> 
                                    <?= htmlspecialchars($jab); ?>
                                </label>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                        <label class="flex items-center gap-2 font-medium text-orange-700 bg-orange-50 px-3 py-2 rounded-xl border border-orange-200 shadow-sm cursor-pointer">
                            <input type="checkbox" name="akses_jabatan[]" value="Semua Jabatan" <?= (strpos($currentAksesExam, 'Semua Jabatan') !== false) ? 'checked' : ''; ?> class="accent-orange-500"> 
                            Semua Jabatan
                        </label>
                    </div>
                </div>

                <div class="sm:col-span-3 pt-2">
                    <button type="submit" name="simpan_ujian" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-3 rounded-xl transition shadow-sm">
                        <?= $editExam ? 'Simpan Perubahan Paket Ujian' : 'Simpan Paket Ujian'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Ujian -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-sm font-bold text-gray-900">Daftar Paket Ujian Aktif</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                            <th class="py-3 px-4">ID</th>
                            <th class="py-3 px-4">Nama Ujian</th>
                            <th class="py-3 px-4">Akses Jabatan</th>
                            <th class="py-3 px-4">Passing Grade</th>
                            <th class="py-3 px-4 text-center">Kelola Soal</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($exams) > 0): ?>
                            <?php foreach ($exams as $ex): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3.5 px-4 font-semibold text-orange-600"><?= htmlspecialchars($ex['id_ujian']); ?></td>
                                <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($ex['nama_ujian']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($ex['akses_jabatan']); ?></td>
                                <td class="py-3.5 px-4 font-extrabold text-orange-600"><?= htmlspecialchars($ex['passing_grade']); ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="admin_questions.php?id_ujian=<?= $ex['id_ujian']; ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-3 py-1.5 rounded-xl transition inline-block">
                                        Kelola Soal ➔
                                    </a>
                                </td>
                                <td class="py-3.5 px-4 text-center space-x-1">
                                    <a href="admin_exams.php?action=edit&id=<?= urlencode($ex['id_ujian']); ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-2.5 py-1.5 rounded-xl transition inline-block">Ubah</a>
                                    <a href="admin_exams.php?action=delete&id=<?= urlencode($ex['id_ujian']); ?>" onclick="return confirm('Hapus ujian ini beserta seluruh bank soal di dalamnya?')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-2.5 py-1.5 rounded-xl transition inline-block">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400">Belum ada paket ujian yang ditambahkan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>