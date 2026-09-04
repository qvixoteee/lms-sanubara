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

// Ambil jabatan pengguna
$stmtUser = $db->prepare("SELECT jabatan FROM users WHERE id_user = :id LIMIT 1");
$stmtUser->bindParam(':id', $userId);
$stmtUser->execute();
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$jabatanUser = $userData['jabatan'] ?? 'Staff';

// Ambil daftar ujian aktif sesuai jabatan
$queryExams = "SELECT * FROM exams WHERE status = 'Aktif' AND (akses_jabatan LIKE :jabatan OR akses_jabatan LIKE '%Semua Jabatan%')";
$stmtExams = $db->prepare($queryExams);
$paramJabatan = "%" . $jabatanUser . "%";
$stmtExams->bindParam(':jabatan', $paramJabatan);
$stmtExams->execute();
$daftarAsesmen = $stmtExams->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Asesmen - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function konfirmasiMulai(url, namaUjian, passingGrade, durasi) {
            let pesan = `PERINGATAN UJIAN!\n\nAnda akan memulai ujian: "${namaUjian}".\n- Passing Grade: ${passingGrade}\n- Durasi: ${durasi} Menit\n\nSetelah dimulai, timer akan berjalan dan jika halaman ditutup paksa, jawaban yang terisi akan otomatis terkirim. Yakin ingin mulai sekarang?`;
            if (confirm(pesan)) {
                window.location.href = url;
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 font-sans antialiased min-h-screen flex flex-col">
    
    <main class="flex-grow max-w-5xl mx-auto px-4 py-8 w-full space-y-6">
        
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

        <div class="flex justify-between items-center">
            <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 px-1">Daftar Asesmen & Ujian</h1>
        </div>

        <div class="space-y-5">
            <?php if (count($daftarAsesmen) > 0): ?>
                <?php foreach ($daftarAsesmen as $exam): ?>
                    <?php 
                    $examId = $exam['id_ujian']; // Didefinisikan di sini agar tidak undefined
                    $prereqVideoId = $exam['prerequisite_video_id'];
                    $isPrereqMet = true;
                    $videoTitle = "";
                    $statusCatatan = "Belum diunggah";

                    // Cek syarat catatan HRD (Mendukung multiple video dengan pemisah koma)
                    if (!empty($prereqVideoId)) {
                        // Pecah string ID video berdasarkan koma (contoh: "VID-002, VID-001" menjadi array)
                        $arrayPrereq = array_map('trim', explode(',', $prereqVideoId));
                        $listJudulVideoUnapproved = [];
                        $listStatusUnapproved = []; // Tambahan array untuk menampung status

                        foreach ($arrayPrereq as $vidSingle) {
                            // Cek status persetujuan HRD untuk masing-masing video
                            $stmtNote = $db->prepare("SELECT status_hrd FROM catatan_karyawan WHERE id_user = :uid AND video_id = :vid LIMIT 1");
                            $stmtNote->execute([':uid' => $userId, ':vid' => $vidSingle]);
                            $noteData = $stmtNote->fetch(PDO::FETCH_ASSOC);

                            $statusCek = $noteData['status_hrd'] ?? 'Belum diunggah';

                            // Jika ada salah satu video yang belum disetujui
                            if ($statusCek !== 'Disetujui') {
                                $isPrereqMet = false;
                                
                                // PERBAIKAN: Tangkap status aktual dari database
                                $listStatusUnapproved[] = $statusCek;
                                
                                // Ambil judul video untuk informasi pesan
                                $stmtVid = $db->prepare("SELECT judul_video FROM videos WHERE id_video = :vid LIMIT 1");
                                $stmtVid->execute([':vid' => $vidSingle]);
                                $vidData = $stmtVid->fetch(PDO::FETCH_ASSOC);
                                
                                $listJudulVideoUnapproved[] = $vidData['judul_video'] ?? $vidSingle;
                            }
                        }

                        // Gabungkan nama-nama video yang belum disetujui untuk ditampilkan pada pesan error
                        if (!empty($listJudulVideoUnapproved)) {
                            $videoTitle = implode(', ', $listJudulVideoUnapproved);
                            // PERBAIKAN: Perbarui variabel $statusCatatan agar tidak stuck di "Belum diunggah"
                            $statusCatatan = implode(', ', array_unique($listStatusUnapproved));
                        }
                    }

                    // Cek riwayat nilai kuis terakhir berdasarkan attempt
                    $stmtNilai = $db->prepare("SELECT nilai, status_kelulusan FROM nilai_kuis WHERE id_user = :uid AND id_ujian = :examId ORDER BY id DESC LIMIT 1");
                    $stmtNilai->execute([':uid' => $userId, ':examId' => $examId]);
                    $riwayatNilai = $stmtNilai->fetch(PDO::FETCH_ASSOC);
                    
                    $sudahDikerjakan = ($riwayatNilai !== false);
                    $statusTerakhir = $riwayatNilai['status_kelulusan'] ?? '';
                    $nilaiKaryawan = $sudahDikerjakan ? intval($riwayatNilai['nilai']) : 0;

                    $isLulus = ($statusTerakhir === 'Lulus');
                    $isRemedial = ($statusTerakhir === 'Remedial');
                    $isIzinRemedial = ($statusTerakhir === 'Izin Remedial');
                    ?>

                    <!-- Kartu Ujian dengan Tata Letak Blok yang Rapi & Tidak Menumpuk -->
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl p-5 sm:p-6 shadow-sm hover:shadow-md transition border border-white/40 flex flex-col gap-4">
                        
                        <!-- Badge Passing Grade & Durasi di Atas -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full">
                                Passing Grade: <?= htmlspecialchars($exam['passing_grade']); ?>
                            </span>
                            <span class="bg-gray-100 text-gray-700 text-xs font-semibold px-3 py-1 rounded-full">
                                Durasi: <?= htmlspecialchars($exam['durasi_menit'] ?? 'Bebas'); ?> Menit
                            </span>
                        </div>
                        
                        <!-- Judul dan Kotak Status Teks -->
                        <div>
                            <h2 class="font-extrabold text-base sm:text-lg text-gray-900 mb-2"><?= htmlspecialchars($exam['nama_ujian']); ?></h2>
                            
                            <?php if ($isLulus): ?>
                                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm p-3 rounded-2xl font-medium flex items-center gap-2">
                                    <span>🎉</span> <span>Status: Lulus (Nilai: <strong><?= $nilaiKaryawan; ?></strong>)</span>
                                </div>
                            <?php elseif ($isRemedial): ?>
                                <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm p-3 rounded-2xl font-medium flex items-start gap-2">
                                    <span class="mt-0.5">❌</span> 
                                    <span>Status: Belum Lulus / Remedial (Nilai: <strong><?= $nilaiKaryawan; ?></strong>). Menunggu persetujuan ulang HRD.</span>
                                </div>
                            <?php elseif ($isIzinRemedial): ?>
                                <div class="bg-blue-50 border border-blue-200 text-blue-800 text-xs sm:text-sm p-3 rounded-2xl font-medium flex items-center gap-2">
                                    <span>⚡</span> <span>Akses Remedial Diberikan! Silakan mulai ujian ulang.</span>
                                </div>
                            <?php elseif (!empty($prereqVideoId) && !$isPrereqMet): ?>
                                <div class="bg-amber-50 border border-amber-200 text-amber-900 text-xs sm:text-sm p-3 rounded-2xl font-medium flex items-start gap-2">
                                    <span class="mt-0.5">🔒</span> 
                                    <span>Syarat: Catatan untuk video "<strong><?= htmlspecialchars($videoTitle); ?></strong>" harus disetujui HRD (Status: <em><?= $statusCatatan; ?></em>)</span>
                                </div>
                            <?php else: ?>
                                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm p-3 rounded-2xl font-medium flex items-center gap-2">
                                    <span>✅</span> <span>Prasyarat terpenuhi, siap dikerjakan</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tombol Aksi di Bawah (Lebar penuh di HP, rapi di PC) -->
                        <div class="pt-2 border-t border-gray-100 flex justify-end">
                            <?php if ($isLulus): ?>
                                <div class="w-full sm:w-auto text-center bg-emerald-50 text-emerald-700 text-xs sm:text-sm font-bold px-6 py-3 rounded-2xl">
                                    Selesai (Lulus)
                                </div>
                            <?php elseif ($isRemedial): ?>
                                <div class="w-full sm:w-auto text-center bg-rose-50 text-rose-600 text-xs sm:text-sm font-bold px-6 py-3 rounded-2xl">
                                    Terkunci (Menunggu HRD)
                                </div>
                            <?php elseif ($isIzinRemedial || (!$sudahDikerjakan && $isPrereqMet)): ?>
                                <button type="button" onclick="konfirmasiMulai('take_exam.php?id=<?= htmlspecialchars($examId); ?>', '<?= htmlspecialchars($exam['nama_ujian'], ENT_QUOTES); ?>', '<?= $exam['passing_grade']; ?>', '<?= $exam['durasi_menit'] ?? 0; ?>')" class="w-full sm:w-auto text-center bg-amber-500 hover:bg-amber-600 text-white text-xs sm:text-sm font-bold px-6 py-3 rounded-2xl shadow-md transition cursor-pointer">
                                    <?= $isIzinRemedial ? 'Mulai Ujian Ulang' : 'Mulai Ujian'; ?>
                                </button>
                            <?php else: ?>
                                <button disabled class="w-full sm:w-auto text-center bg-gray-200 text-gray-400 text-xs sm:text-sm font-bold px-6 py-3 rounded-2xl cursor-not-allowed">
                                    Terkunci
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white/80 backdrop-blur-md p-8 rounded-3xl text-center text-xs sm:text-sm text-gray-500 shadow-sm border border-white/40">
                    Belum ada asesmen atau ujian yang tersedia untuk jabatan Anda.
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>