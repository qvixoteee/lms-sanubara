<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include_once 'config/Database.php';
$database = new Database();
$db = $database->getConnection();

$currentUser = $_SESSION['user'];
$userId = $currentUser['id'];

// Ambil jabatan pengguna dari database
$stmtUser = $db->prepare("SELECT jabatan FROM users WHERE id_user = :id LIMIT 1");
$stmtUser->bindParam(':id', $userId);
$stmtUser->execute();
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$jabatanUser = $userData['jabatan'] ?? 'Staff';

// Ambil video yang statusnya Aktif dan sesuai dengan jabatan user
$queryVideos = "SELECT * FROM videos WHERE status = 'Aktif' AND (akses_jabatan LIKE :jabatan OR akses_jabatan LIKE '%Semua Jabatan%')";
$stmtVideos = $db->prepare($queryVideos);
$paramJabatan = "%" . $jabatanUser . "%";
$stmtVideos->bindParam(':jabatan', $paramJabatan);
$stmtVideos->execute();
$videos = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

// KELOMPOKKAN VIDEO BERDASARKAN KATEGORI
$groupedVideos = [];
foreach ($videos as $video) {
    $kategori = !empty($video['kategori']) ? $video['kategori'] : 'Tanpa Kategori';
    $groupedVideos[$kategori][] = $video;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Video - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Sembunyikan ikon panah bawaan browser pada tag summary */
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 font-sans antialiased min-h-screen flex flex-col">

    <main class="flex-grow max-w-5xl mx-auto px-4 py-8 w-full space-y-6">
        <!-- HEADER BARU: Kotak Rounded & Badge Jabatan yang Selaras dengan Dashboard -->
        <div class="flex justify-between items-center mb-6 bg-white/60 backdrop-blur-md p-4 rounded-3xl shadow-sm border border-white/40">
            <!-- Tombol Kembali dengan Ikon Kotak Rounded -->
            <a href="index.php" class="inline-flex items-center gap-2.5 bg-white hover:bg-gray-50 text-indigo-600 px-4 py-2.5 rounded-2xl shadow-sm border border-gray-100 text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                </svg>
                Kembali
            </a>

            <!-- Badge Informasi Jabatan Pengguna -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-indigo-700 bg-indigo-50 px-3.5 py-2 rounded-2xl shadow-sm border border-indigo-100/50">
                    Jabatan: <?= htmlspecialchars($jabatanUser); ?>
                </span>
            </div>
        </div>

        <!-- Judul Daftar Video -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-bold text-gray-900">Daftar Modul Video</h1>
        </div>

        <div class="space-y-4">
            <?php if (count($groupedVideos) > 0): ?>
                <?php foreach ($groupedVideos as $kategori => $vids): ?>
                    
                    <!-- Accordion Kategori Menggunakan Tag Details -->
                    <details class="group bg-white/80 backdrop-blur-md rounded-3xl shadow-sm border border-white/40 overflow-hidden" close>
                        
                        <!-- Header Dropdown -->
                        <summary class="flex justify-between items-center p-5 cursor-pointer font-bold text-gray-900 hover:bg-white/50 transition">
                            <div class="flex items-center gap-3">
                                <span class="bg-indigo-100 text-indigo-800 text-xs font-bold px-3 py-1 rounded-full"><?= htmlspecialchars($kategori); ?></span>
                                <span class="text-xs text-gray-500 font-medium"><?= count($vids); ?> Modul</span>
                            </div>
                            <!-- Ikon Panah (berputar saat dropdown terbuka) -->
                            <span class="transition duration-300 group-open:-rotate-180">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </span>
                        </summary>

                        <!-- Isi Dropdown (Daftar Video di Kategori Tersebut) -->
                        <div class="p-4 pt-0 space-y-3 bg-transparent">
                            <?php foreach ($vids as $video): ?>
                                <?php 
                                // Ambil persentase progres user untuk video ini
                                $vidId = $video['id_video'];
                                $stmtProg = $db->prepare("SELECT persentase_progres FROM video_progress WHERE id_user = :uid AND video_id = :vid LIMIT 1");
                                $stmtProg->bindParam(':uid', $userId);
                                $stmtProg->bindParam(':vid', $vidId);
                                $stmtProg->execute();
                                $progData = $stmtProg->fetch(PDO::FETCH_ASSOC);
                                $persen = $progData ? intval($progData['persentase_progres']) : 0;
                                ?>

                                <a href="watch.php?id=<?= htmlspecialchars($vidId); ?>" class="block bg-white rounded-2xl p-4 shadow-sm hover:shadow-md transition border border-gray-100">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Modul Video</span>
                                        <span class="text-xs font-bold text-gray-600"><?= htmlspecialchars($video['durasi_menit']); ?> Menit</span>
                                    </div>
                                    <h3 class="font-bold text-sm text-gray-900 mb-1"><?= htmlspecialchars($video['judul_video']); ?></h3>
                                    <p class="text-[10px] text-gray-500 mb-3">Akses: <?= htmlspecialchars($video['akses_jabatan']); ?></p>

                                    <!-- Indikator Progres Menonton -->
                                    <div>
                                        <div class="flex justify-between text-[10px] font-medium text-gray-600 mb-1">
                                            <span>Progres Menonton</span>
                                            <span class="font-bold <?= $persen >= 95 ? 'text-emerald-600' : 'text-cyan-600'; ?>"><?= $persen; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="<?= $persen >= 95 ? 'bg-emerald-500' : 'bg-cyan-500'; ?> h-full rounded-full transition-all duration-300" style="width: <?= $persen; ?>%;"></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white/80 p-8 rounded-3xl text-center text-xs text-gray-500 shadow-sm border border-white/40">
                    Belum ada modul video yang tersedia untuk jabatan Anda.
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>