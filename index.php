<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include_once 'config/Database.php';
$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user']['id'] ?? 1;
$namaKaryawan = $_SESSION['user']['name'] ?? 'Karyawan Sanubara';

// Cek apakah user yang login berasal dari departemen Manajemen
$isManajemen = (isset($_SESSION['user']['departemen']) && strtolower(trim($_SESSION['user']['departemen'])) === 'manajemen');

// Ambil jabatan pengguna untuk pencocokan modul
$jabatanUser = 'Staff';
try {
    $stmtUser = $db->prepare("SELECT jabatan FROM users WHERE id_user = :id LIMIT 1");
    $stmtUser->bindParam(':id', $userId);
    $stmtUser->execute();
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $jabatanUser = $userData['jabatan'] ?? 'Staff';
} catch (Exception $e) {
    $jabatanUser = 'Staff';
}

// 1. Hitung total modul video aktif yang tersedia sesuai jabatan karyawan
$totalModulTersedia = 0;
try {
    $stmtTotalVid = $db->prepare("SELECT COUNT(*) as total FROM videos WHERE status = 'Aktif' AND (akses_jabatan LIKE :jabatan OR akses_jabatan LIKE '%Semua Jabatan%')");
    $stmtTotalVid->bindValue(':jabatan', '%' . $jabatanUser . '%');
    $stmtTotalVid->execute();
    $rowTotal = $stmtTotalVid->fetch(PDO::FETCH_ASSOC);
    $totalModulTersedia = intval($rowTotal['total'] ?? 0);
} catch (Exception $e) {
    $totalModulTersedia = 0;
}

// 2. Hitung modul selesai berdasarkan catatan yang disetujui HRD
$lessonCompleted = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT video_id) as total FROM catatan_karyawan WHERE id_user = :uid AND status_hrd = 'Disetujui'");
    $stmt->bindParam(':uid', $userId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $lessonCompleted = intval($row['total'] ?? 0);
} catch (Exception $e) {
    $lessonCompleted = 0;
}

// 3. Hitung rata-rata persentase progres dari tabel video_progress
$overallProgress = 0;
try {
    $stmtProg = $db->prepare("SELECT AVG(persentase_progres) as rata_prog FROM video_progress WHERE id_user = :uid");
    $stmtProg->bindParam(':uid', $userId);
    $stmtProg->execute();
    $rowProg = $stmtProg->fetch(PDO::FETCH_ASSOC);
    $overallProgress = $rowProg['rata_prog'] !== null ? round($rowProg['rata_prog']) : 0;
} catch (Exception $e) {
    $overallProgress = 0;
}

// Hitung Nilai Rata-rata Ujian
$rataRataNilai = 0;
try {
    $stmtNilai = $db->prepare("SELECT AVG(nilai) as rata_rata FROM nilai_kuis WHERE id_user = :uid");
    $stmtNilai->bindParam(':uid', $userId);
    $stmtNilai->execute();
    $rowNilai = $stmtNilai->fetch(PDO::FETCH_ASSOC);
    $rataRataNilai = $rowNilai['rata_rata'] !== null ? round($rowNilai['rata_rata']) : 0;
} catch (Exception $e) {
    $rataRataNilai = 0;
}

// Ambil rekomendasi video sesuai jabatan (lebih fleksibel dengan LOWER)
$rekomendasiVideo = null;
try {
    $stmtRec = $db->prepare("SELECT * FROM videos WHERE status = 'Aktif' AND (LOWER(akses_jabatan) LIKE LOWER(:jabatan) OR akses_jabatan LIKE '%Semua Jabatan%') LIMIT 1");
    $stmtRec->bindValue(':jabatan', "%" . trim($jabatanUser) . "%");
    $stmtRec->execute();
    $rekomendasiVideo = $stmtRec->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rekomendasiVideo = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 min-h-screen text-gray-800 font-sans pb-12 flex flex-col items-center">

    <div class="w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
        
        <!-- Header Sapaan & Profil dengan Dropdown Logout -->
        <div class="flex justify-between items-center mb-6 pt-2 relative">
            <div>
                <p class="text-xs text-gray-500 font-medium tracking-wide uppercase">Selamat Datang,</p>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900"><?= htmlspecialchars($namaKaryawan); ?></h1>
            </div>
            
            <div class="relative">
                <button onclick="toggleDropdown()" class="w-12 h-12 rounded-full overflow-hidden border-2 border-white shadow-md bg-indigo-600 flex items-center justify-center text-white font-bold text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                    <?php 
                    $words = explode(' ', $namaKaryawan);
                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    echo $initials;
                    ?>
                </button>

                <!-- Menu Dropdown Logout -->
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-44 bg-white rounded-2xl shadow-xl border border-gray-100 py-1 z-50">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-[10px] text-gray-400">Masuk sebagai</p>
                        <p class="text-xs font-bold text-gray-800 truncate"><?= htmlspecialchars($namaKaryawan); ?></p>
                    </div>
                    <a href="logout.php" class="block px-4 py-2.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition">
                        Keluar (Logout)
                    </a>
                </div>
            </div>
        </div>

        <?php if ($isManajemen): ?>
        <!-- Tombol Khusus Admin / Departemen Manajemen -->
        <div class="mb-6">
            <a href="admin_dashboard.php" class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-amber-500 text-white p-5 rounded-3xl shadow-md hover:from-orange-600 hover:to-amber-600 transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center font-bold">⚙️</div>
                    <div>
                        <h4 class="font-bold text-sm">Panel Kontrol Admin & HRD</h4>
                        <p class="text-xs text-orange-100">Kelola video, paket ujian, dan evaluasi karyawan</p>
                    </div>
                </div>
                <span class="text-xs font-bold bg-white text-orange-600 px-4 py-2 rounded-xl shadow-sm">Buka Panel ➔</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- DASHBOARD PENCAPAIAN BELAJAR -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-6 shadow-sm border border-white/40 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-gray-800">Pencapaian Belajar</h3>
                <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">Aktif</span>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-5">
                <div class="bg-amber-100/70 rounded-2xl p-4 flex flex-col justify-between">
                    <span class="text-xs font-medium text-amber-700">Rata-rata Ujian</span>
                    <div class="flex items-baseline space-x-1 mt-2">
                        <span class="text-2xl font-extrabold text-amber-900"><?= $rataRataNilai; ?></span>
                        <span class="text-xs text-amber-700">Skor</span>
                    </div>
                </div>
                <div class="bg-emerald-100/70 rounded-2xl p-4 flex flex-col justify-between">
                    <span class="text-xs font-medium text-emerald-700">Modul Selesai</span>
                    <div class="flex items-baseline space-x-1 mt-2">
                        <span class="text-2xl font-extrabold text-emerald-900"><?= $lessonCompleted; ?></span>
                        <span class="text-xs text-emerald-700">/ <?= $totalModulTersedia; ?> Pelajaran</span>
                    </div>
                </div>
            </div>

            <div>
                <div class="flex justify-between text-xs font-medium text-gray-600 mb-1.5">
                    <span>Progres Menonton Video</span>
                    <span class="font-bold text-gray-800"><?= $overallProgress; ?>%</span>
                </div>
                <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                    <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" style="width: <?= $overallProgress; ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- MENU NAVIGASI UTAMA -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <a href="video_list.php" class="bg-cyan-500 text-white rounded-3xl p-6 shadow-md shadow-cyan-500/20 hover:bg-cyan-600 transition flex flex-col justify-between h-32">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h4 class="font-bold text-base">Daftar Video</h4>
                    <p class="text-xs text-cyan-100 mt-0.5">Materi Pembelajaran</p>
                </div>
            </a>

            <a href="exam.php" class="bg-amber-500 text-white rounded-3xl p-6 shadow-md shadow-amber-500/20 hover:bg-amber-600 transition flex flex-col justify-between h-32">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012-2m-6 9l2 2 4-4"></path></svg>
                </div>
                <div>
                    <h4 class="font-bold text-base">Daftar Asesmen</h4>
                    <p class="text-xs text-amber-100 mt-0.5">Ujian & Evaluasi</p>
                </div>
            </a>
        </div>

        <!-- MODUL REKOMENDASI -->
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-gray-800">Modul Rekomendasi</h3>
            <a href="video_list.php" class="text-xs font-semibold text-indigo-600 hover:underline">Lihat Semua</a>
        </div>

        <?php if ($rekomendasiVideo): ?>
        <div class="space-y-3">
            <a href="watch.php?id=<?= htmlspecialchars($rekomendasiVideo['id_video']); ?>" class="block bg-cyan-100/80 rounded-3xl p-5 shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-start mb-2">
                    <span class="bg-white/70 text-cyan-800 text-[10px] font-bold px-3 py-1 rounded-full"><?= htmlspecialchars($rekomendasiVideo['kategori']); ?></span>
                    <span class="text-xs font-semibold text-cyan-900">Durasi: <?= htmlspecialchars($rekomendasiVideo['durasi_menit']); ?> Menit</span>
                </div>
                <h4 class="font-bold text-cyan-900 text-base mb-1"><?= htmlspecialchars($rekomendasiVideo['judul_video']); ?></h4>
                <p class="text-xs text-cyan-700">Akses Jabatan: <?= htmlspecialchars($rekomendasiVideo['akses_jabatan']); ?></p>
            </a>
        </div>
        <?php else: ?>
        <div class="bg-white/80 p-6 rounded-3xl text-center text-xs text-gray-500">
            Tidak ada modul rekomendasi saat ini.
        </div>
        <?php endif; ?>

    </div>

    <!-- Script Dropdown -->
    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('hidden');
        }

        window.onclick = function(event) {
            if (!event.target.closest('button')) {
                var dropdown = document.getElementById('profileDropdown');
                if (dropdown && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            }
        }
    </script>
</body>
</html>