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
$idUjian = $_GET['id'] ?? null;

if (!$idUjian) {
    header("Location: exam.php");
    exit;
}

// Cek apakah sudah pernah mengerjakan sebelumnya
$stmtCek = $db->prepare("SELECT id FROM nilai_kuis WHERE id_user = :uid AND id_ujian = :examId LIMIT 1");
$stmtCek->execute([':uid' => $userId, ':examId' => $idUjian]);
if ($stmtCek->fetch()) {
    header("Location: exam.php");
    exit;
}

$stmtExam = $db->prepare("SELECT * FROM exams WHERE id_ujian = :id LIMIT 1");
$stmtExam->bindParam(':id', $idUjian);
$stmtExam->execute();
$exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo "Paket ujian tidak ditemukan.";
    exit;
}

$durasiMenit = !empty($exam['durasi_menit']) ? intval($exam['durasi_menit']) : 0;
$limitPerVideo = !empty($exam['jumlah_soal_per_video']) ? intval($exam['jumlah_soal_per_video']) : 5;

// Ambil video prasyarat yang terikat pada ujian ini
$prereqVideoRaw = $exam['prerequisite_video_id'] ?? '';
$arrayVideoPrereq = array_map('trim', explode(',', $prereqVideoRaw));

$soalList = [];

if (!empty($arrayVideoPrereq) && $arrayVideoPrereq[0] !== '') {
    // Ambil soal secara acak (RANDOM) dari masing-masing video sesuai kuota HRD
    foreach ($arrayVideoPrereq as $vidId) {
        $stmtSoalVid = $db->prepare("SELECT * FROM bank_soal WHERE id_ujian = :id_ujian AND id_video = :id_video ORDER BY RAND() LIMIT :limit");
        $stmtSoalVid->bindValue(':id_ujian', $idUjian);
        $stmtSoalVid->bindValue(':id_video', $vidId);
        $stmtSoalVid->bindValue(':limit', $limitPerVideo, PDO::PARAM_INT);
        $stmtSoalVid->execute();
        $fetchedSoal = $stmtSoalVid->fetchAll(PDO::FETCH_ASSOC);
        
        $soalList = array_merge($soalList, $fetchedSoal);
    }
    
    // Acak kembali urutan keseluruhan soal agar tercampur antar video
    shuffle($soalList);
} else {
    // Fallback: Jika tidak ada prasyarat video spesifik, ambil acak dari semua bank soal ujian
    $stmtSoal = $db->prepare("SELECT * FROM bank_soal WHERE id_ujian = :id ORDER BY RAND()");
    $stmtSoal->bindParam(':id', $idUjian);
    $stmtSoal->execute();
    $soalList = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);
}

$totalSoal = count($soalList);

// Fungsi pemrosesan nilai ujian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ujian'])) {
    $jawabanUser = $_POST['jawaban'] ?? []; 
    $benar = 0;

    // Cek setiap jawaban langsung ke database berdasarkan ID Soal yang dijawab
    if (!empty($jawabanUser)) {
        foreach ($jawabanUser as $idSoal => $pilihanKaryawan) {
            $stmtKunci = $db->prepare("SELECT pilihan_benar FROM bank_soal WHERE id_soal = :id LIMIT 1");
            $stmtKunci->bindParam(':id', $idSoal);
            $stmtKunci->execute();
            $kunciDB = $stmtKunci->fetch(PDO::FETCH_ASSOC);

            if ($kunciDB && trim($pilihanKaryawan) === trim($kunciDB['pilihan_benar'])) {
                $benar++;
            }
        }
    }

    // $totalSoal tetap bisa digunakan sebagai penyebut karena jumlah kuota (LIMIT) 
    // dari query sebelumnya selalu menghasilkan angka yang konsisten.
    $nilaiAkhir = $totalSoal > 0 ? round(($benar / $totalSoal) * 100) : 0;
    
    // Tentukan status kelulusan berdasarkan passing grade
    $passingGrade = intval($exam['passing_grade'] ?? 75);
    $statusKelulusan = ($nilaiAkhir >= $passingGrade) ? 'Lulus' : 'Remedial';

    // Simpan ke tabel nilai_kuis
    $stmtSave = $db->prepare("INSERT INTO nilai_kuis (id_user, id_ujian, nilai, status_kelulusan) VALUES (:uid, :ujian, :nilai, :status)");
    $stmtSave->execute([
        ':uid' => $userId,
        ':ujian' => $idUjian,
        ':nilai' => $nilaiAkhir,
        ':status' => $statusKelulusan
    ]);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        exit('OK');
    }

    header("Location: exam.php?status=success_exam");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian: <?= htmlspecialchars($exam['nama_ujian']); ?> - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let durasiMenit = <?= $durasiMenit; ?>;
            const formUjian = document.getElementById("form-ujian");
            let isSubmitted = false;

            // 1. BLOKIR TOMBOL KEYBOARD (F5, Ctrl+R, Ctrl+F5)
            document.addEventListener("keydown", function (e) {
                if ((e.key === 'F5') || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'R')) {
                    e.preventDefault();
                    alert("Refresh halaman dinonaktifkan selama ujian berlangsung.");
                }
            });

            // 2. TAMPILKAN PERINGATAN SAAT TUTUP/REFRESH TAB
            window.addEventListener("beforeunload", function (e) {
                if (!isSubmitted) {
                    // Memicu dialog peringatan bawaan browser (teks default dari browser)
                    e.preventDefault();
                    e.returnValue = ''; 

                    // (Fitur Anda yang sudah ada) Kirim jawaban di latar belakang jika mereka tetap memaksa keluar
                    let formData = new FormData(formUjian);
                    formData.append('submit_ujian', '1');
                    navigator.sendBeacon(window.location.href, formData);
                }
            });

            formUjian.addEventListener("submit", function() {
                // Jika user klik submit secara normal, matikan peringatan
                isSubmitted = true;
            });

            // Timer Hitung Mundur
            if (durasiMenit > 0) {
                let timeLeft = durasiMenit * 60;
                const timerDisplay = document.getElementById("timer-countdown");

                const countdown = setInterval(function () {
                    let minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;

                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;

                    timerDisplay.textContent = minutes + ":" + seconds;

                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        isSubmitted = true;
                        alert("Waktu ujian telah habis! Sistem mengirimkan jawaban Anda secara otomatis.");
                        
                        // TAMBAHAN: Buat input hidden agar $_POST['submit_ujian'] terbaca oleh PHP
                        let hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'submit_ujian';
                        hiddenInput.value = '1';
                        formUjian.appendChild(hiddenInput);

                        // Kirim form
                        formUjian.submit();
                    }
                    timeLeft--;
                }, 1000);
            }
        });
    </script>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 min-h-screen text-gray-800 overscroll-y-none font-sans p-5 pb-16" >
    
    <div class="max-w-4xl mx-auto space-y-6">
        
        <!-- Header Ujian & Timer -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white/90 backdrop-blur-md p-6 rounded-3xl shadow-sm border border-white/40 gap-4 sticky top-5 z-50">
            <div>
                <span class="text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1 rounded-full">
                    Passing Grade: <?= htmlspecialchars($exam['passing_grade']); ?>
                </span>
                <h1 class="text-lg sm:text-xl font-black text-gray-900 mt-2"><?= htmlspecialchars($exam['nama_ujian']); ?></h1>
                <p class="text-xs text-gray-500 mt-0.5">Total Soal: <?= $totalSoal; ?> Pertanyaan (Diambil acak dari modul video)</p>
            </div>
            
            <div class="flex items-center gap-4">
                <?php if ($durasiMenit > 0): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-2 rounded-2xl text-xs font-extrabold flex items-center gap-2 shadow-sm">
                        <span>⏳ Waktu Tersisa:</span>
                        <span id="timer-countdown" class="text-sm font-black">--:--</span>
                    </div>
                <?php else: ?>
                    <div class="bg-gray-100 text-gray-600 px-4 py-2 rounded-2xl text-xs font-bold">
                        Waktu Bebas
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Pengerjaan Soal -->
        <form id="form-ujian" action="" method="POST" class="space-y-6">
            <?php if ($totalSoal > 0): ?>
                <?php $no = 1; foreach ($soalList as $soal): ?>
                    <?php 
                    $pilihan = [
                        $soal['pilihan_benar'],
                        $soal['pilihan_lain_1'],
                        $soal['pilihan_lain_2'],
                        $soal['pilihan_lain_3']
                    ];
                    $pilihan = array_filter($pilihan, fn($value) => !is_null($value) && $value !== '');
                    shuffle($pilihan);
                    ?>

                    <div class="bg-white/90 backdrop-blur-md rounded-3xl p-6 shadow-sm border border-white/40 space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="w-7 h-7 rounded-full bg-amber-100 text-amber-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">
                                <?= $no++; ?>
                            </span>
                            <p class="font-bold text-gray-900 text-sm leading-relaxed">
                                <?= htmlspecialchars($soal['pertanyaan']); ?>
                            </p>
                        </div>

                        <div class="space-y-2 pl-10">
                            <?php foreach ($pilihan as $opsi): ?>
                                <label class="flex items-center gap-3 p-3 bg-gray-50/80 hover:bg-amber-50/50 rounded-2xl border border-gray-100 cursor-pointer transition text-xs font-medium text-gray-700">
                                    <input type="radio" name="jawaban[<?= $soal['id_soal']; ?>]" value="<?= htmlspecialchars($opsi); ?>" class="accent-amber-500 w-4 h-4">
                                    <span><?= htmlspecialchars($opsi); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="pt-4 text-center">
                    <button type="submit" name="submit_ujian" onclick="return confirm('Apakah Anda yakin ingin menyelesaikan dan mengirimkan jawaban ujian ini?')" class="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-sm px-8 py-4 rounded-2xl shadow-lg transition transform active:scale-95 cursor-pointer">
                        Selesai & Kirim Jawaban Ujian ➔
                    </button>
                </div>

            <?php else: ?>
                <div class="bg-white/90 p-10 rounded-3xl text-center text-xs text-gray-500 shadow-sm space-y-3">
                    <p class="font-bold text-gray-700">Belum ada soal tersedia atau kuota video prasyarat belum mencukupi.</p>
                    <a href="exam.php" class="inline-block bg-amber-500 text-white font-bold px-4 py-2 rounded-xl">Kembali ke Daftar Ujian</a>
                </div>
            <?php endif; ?>
        </form>

    </div>

</body>
</html>