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

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header("Location: admin_dashboard.php");
    exit;
}

// 1. Ambil data profil karyawan
$stmtUser = $db->prepare("SELECT * FROM users WHERE id_user = :id LIMIT 1");
$stmtUser->bindParam(':id', $userId);
$stmtUser->execute();
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Karyawan tidak ditemukan.";
    exit;
}

// Hitung Masa Kerja
$tglGabung = $user['tanggal_bergabung'] ?? '2025-01-01';
$diff = (new DateTime())->diff(new DateTime($tglGabung));
$masaKerja = $diff->y > 0 ? $diff->y . " Tahun " . $diff->m . " Bulan" : $diff->m . " Bulan";

$jabatanUser = $user['jabatan'] ?? 'Staff';
$adminName = $_SESSION['user']['name'] ?? 'HRD Admin';

// 2. Ambil progres video
$stmtVideos = $db->prepare("
    SELECT v.*, COALESCE(vp.persentase_progres, 0) as progres 
    FROM videos v 
    LEFT JOIN video_progress vp ON v.id_video = vp.video_id AND vp.id_user = :uid
    WHERE v.status = 'Aktif' AND (v.akses_jabatan LIKE :jabatan OR v.akses_jabatan LIKE '%Semua Jabatan%')
");
$stmtVideos->bindValue(':uid', $userId);
$stmtVideos->bindValue(':jabatan', "%" . $jabatanUser . "%");
$stmtVideos->execute();
$videoProgressList = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

// 3. Ambil riwayat ujian
$stmtExams = $db->prepare("
    SELECT n.*, e.nama_ujian, e.passing_grade 
    FROM nilai_kuis n 
    JOIN exams e ON n.id_ujian = e.id_ujian 
    WHERE n.id_user = :uid
");
$stmtExams->bindParam(':uid', $userId);
$stmtExams->execute();
$examHistory = $stmtExams->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Karyawan - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans flex text-gray-800">

    <!-- SIDEBAR KIRI (Tema Oranye Konsisten) -->
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

            <nav class="space-y-1.5 text-xs">
                <a href="admin_dashboard.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                    ← Kembali ke Dashboard
                </a>
            </nav>
        </div>

        <div>
            <a href="logout.php" class="w-full bg-orange-700/60 hover:bg-orange-700 text-rose-200 text-xs font-bold px-4 py-3 rounded-2xl transition flex items-center justify-center gap-2 border border-orange-500/30">
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="flex-1 p-4 sm:p-8 overflow-y-auto space-y-6">
        
        <!-- Header & Tombol Kembali Mobile -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900">Detail Profil Karyawan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Informasi data diri dan rincian progres pembelajaran.</p>
            </div>
            <a href="admin_dashboard.php" class="md:hidden bg-white text-orange-600 border border-orange-200 text-xs font-bold px-3 py-2 rounded-xl">
                ← Kembali
            </a>
        </div>

        <!-- Kartu Profil Data Diri -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-center gap-6">
            <div class="w-20 h-20 rounded-2xl bg-orange-500 text-white font-black text-2xl flex items-center justify-center shadow-md">
                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h2 class="text-lg font-extrabold text-gray-900"><?= htmlspecialchars($user['nama_lengkap']); ?></h2>
                <p class="text-xs text-gray-500 mt-0.5">Email: <?= htmlspecialchars($user['email'] ?? '-'); ?></p>
                
                <div class="flex flex-wrap justify-center sm:justify-start gap-2 mt-4 text-xs font-semibold">
                    <span class="bg-orange-50 text-orange-700 px-3 py-1 rounded-xl border border-orange-100">
                        Jabatan: <?= htmlspecialchars($user['jabatan']); ?>
                    </span>
                    <span class="bg-purple-50 text-purple-700 px-3 py-1 rounded-xl border border-purple-100">
                        Pabrik: <?= htmlspecialchars($user['pabrik'] ?? '-'); ?>
                    </span>
                    <span class="bg-emerald-50 text-emerald-700 px-3 py-1 rounded-xl border border-emerald-100">
                        Masa Kerja: <?= $masaKerja; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Bagian 1: Progres Modul Video -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">Progres Menonton Modul Video</h3>
            
            <div class="space-y-3">
                <?php if (count($videoProgressList) > 0): ?>
                    <?php foreach ($videoProgressList as $vid): ?>
                        <?php $persen = intval($vid['progres']); ?>
                        <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-2">
                            <div class="flex justify-between items-center text-xs">
                                <div>
                                    <span class="font-bold text-gray-900"><?= htmlspecialchars($vid['judul_video']); ?></span>
                                    <span class="text-gray-400 ml-2">(<?= htmlspecialchars($vid['kategori']); ?>)</span>
                                </div>
                                <span class="font-bold <?= $persen >= 95 ? 'text-emerald-600' : 'text-orange-600'; ?>">
                                    <?= $persen; ?>% <?= $persen >= 95 ? '✅ Selesai' : ''; ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden">
                                <div class="<?= $persen >= 95 ? 'bg-emerald-500' : 'bg-orange-500'; ?> h-full rounded-full transition-all" style="width: <?= $persen; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-gray-400 text-center py-4">Belum ada modul video aktif untuk jabatan ini.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bagian 2: Riwayat Hasil Ujian -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">Riwayat Hasil Ujian & Asesmen</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Nama Ujian</th>
                            <th class="py-3 px-4">Nilai Diperoleh</th>
                            <th class="py-3 px-4">Passing Grade</th>
                            <th class="py-3 px-4">Status Kelulusan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($examHistory) > 0): ?>
                            <?php foreach ($examHistory as $ex): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($ex['nama_ujian']); ?></td>
                                <td class="py-3.5 px-4 font-extrabold text-orange-600"><?= htmlspecialchars($ex['nilai']); ?></td>
                                <td class="py-3.5 px-4 text-gray-500"><?= htmlspecialchars($ex['passing_grade']); ?></td>
                                <td class="py-3.5 px-4">
                                    <?php if ($ex['nilai'] >= $ex['passing_grade']): ?>
                                        <span class="bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full font-bold">Lulus</span>
                                    <?php else: ?>
                                        <span class="bg-rose-50 text-rose-700 px-3 py-1 rounded-full font-bold">Remediasi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-400">Karyawan ini belum pernah mengerjakan ujian.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>