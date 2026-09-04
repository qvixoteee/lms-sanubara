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

$idUjian = $_GET['id_ujian'] ?? null;
if (!$idUjian) {
    header("Location: admin_exams.php");
    exit;
}

// Ambil informasi ujian
$stmtExam = $db->prepare("SELECT * FROM exams WHERE id_ujian = :id LIMIT 1");
$stmtExam->bindParam(':id', $idUjian);
$stmtExam->execute();
$exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo "Paket ujian tidak ditemukan.";
    exit;
}

$successMsg = '';
$errorMsg = '';

// Proses tambah 1 soal manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_soal'])) {
    $pertanyaan = $_POST['pertanyaan'] ?? '';
    $pilihanBenar = $_POST['pilihan_benar'] ?? '';
    $pilihanLain1 = $_POST['pilihan_lain_1'] ?? '';
    $pilihanLain2 = $_POST['pilihan_lain_2'] ?? '';
    $pilihanLain3 = $_POST['pilihan_lain_3'] ?? '';
    $idVideo = !empty($_POST['id_video']) ? $_POST['id_video'] : null;

    $stmtInsert = $db->prepare("INSERT INTO bank_soal (id_ujian, id_video, pertanyaan, pilihan_benar, pilihan_lain_1, pilihan_lain_2, pilihan_lain_3) VALUES (:id_ujian, :id_video, :pertanyaan, :pilihan_benar, :pilihan_lain_1, :pilihan_lain_2, :pilihan_lain_3)");
    $stmtInsert->execute([
        ':id_ujian' => $idUjian,
        ':id_video' => $idVideo,
        ':pertanyaan' => $pertanyaan,
        ':pilihan_benar' => $pilihanBenar,
        ':pilihan_lain_1' => $pilihanLain1,
        ':pilihan_lain_2' => $pilihanLain2,
        ':pilihan_lain_3' => $pilihanLain3
    ]);
    $successMsg = "Soal berhasil ditambahkan ke bank soal!";
}

// Proses import batch via file Excel / CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['file_csv']['tmp_name'];
        $fileName = $_FILES['file_csv']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'csv' || $fileExtension === 'txt') {
            if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                // Lewati baris header pertama
                fgetcsv($handle);

                $countImport = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 6) {
                        // Jika kolom pertama di CSV kosong atau 'EXAM_CONTOH', kita timpa dengan $idUjian halaman ini agar aman
                        $targetIdUjian = (!empty($data[0]) && $data[0] !== 'EXAM_CONTOH') ? $data[0] : $idUjian;
                        $idVideo = !empty($data[1]) ? $data[1] : null;
                        $pertanyaan = $data[2];
                        $pilihanBenar = $data[3];
                        $pilihanLain1 = $data[4];
                        $pilihanLain2 = $data[5];
                        $pilihanLain3 = $data[6] ?? '';

                        $stmtImport = $db->prepare("INSERT INTO bank_soal (id_ujian, id_video, pertanyaan, pilihan_benar, pilihan_lain_1, pilihan_lain_2, pilihan_lain_3) VALUES (:id_ujian, :id_video, :pertanyaan, :pilihan_benar, :pilihan_lain_1, :pilihan_lain_2, :pilihan_lain_3)");
                        $stmtImport->execute([
                            ':id_ujian' => $targetIdUjian,
                            ':id_video' => $idVideo,
                            ':pertanyaan' => $pertanyaan,
                            ':pilihan_benar' => $pilihanBenar,
                            ':pilihan_lain_1' => $pilihanLain1,
                            ':pilihan_lain_2' => $pilihanLain2,
                            ':pilihan_lain_3' => $pilihanLain3
                        ]);
                        $countImport++;
                    }
                }
                fclose($handle);
                $successMsg = "Berhasil mengimpor $countImport soal secara batch ke bank soal!";
            } else {
                $errorMsg = "Gagal membaca file yang diunggah.";
            }
        } else {
            $errorMsg = "Harap unggah file dengan format .csv (Excel CSV).";
        }
    } else {
        $errorMsg = "Silakan pilih file CSV terlebih dahulu.";
    }
}

// Ambil daftar video aktif
$stmtVid = $db->prepare("SELECT id_video, judul_video FROM videos WHERE status = 'Aktif' ORDER BY judul_video ASC");
$stmtVid->execute();
$listVideos = $stmtVid->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar soal untuk ujian ini dari bank_soal
$stmtQuestions = $db->prepare("SELECT * FROM bank_soal WHERE id_ujian = :id ORDER BY id_soal DESC");
$stmtQuestions->bindParam(':id', $idUjian);
$stmtQuestions->execute();
$questions = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['user']['name'] ?? 'HRD Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Bank Soal - Sanubara Learning Center</title>
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
                <p class="text-[10px] font-bold text-orange-200 uppercase tracking-wider mb-2 px-1">Navigasi</p>
                <nav class="space-y-1.5 text-xs">
                    <a href="admin_exams.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        ← Kembali ke Daftar Ujian
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
                <span class="text-xs font-bold text-orange-600 bg-orange-50 px-3 py-1 rounded-full">Paket Ujian: <?= htmlspecialchars($exam['nama_ujian']); ?> (ID: <?= htmlspecialchars($idUjian); ?>)</span>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900 mt-1">Kelola Bank Soal</h1>
            </div>
            <a href="admin_exams.php" class="md:hidden bg-white text-orange-600 border border-orange-200 text-xs font-bold px-3 py-2 rounded-xl">
                ← Kembali
            </a>
        </div>

        <?php if ($successMsg): ?>
            <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs p-4 rounded-2xl font-medium">
                ✅ <?= $successMsg; ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="bg-rose-50 text-rose-800 border border-rose-200 text-xs p-4 rounded-2xl font-medium">
                ❌ <?= $errorMsg; ?>
            </div>
        <?php endif; ?>

        <!-- FITUR IMPORT BATCH EXCEL (CSV) -->
        <div class="bg-gradient-to-r from-orange-500 to-amber-500 rounded-3xl p-6 text-white shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-sm font-black tracking-wide uppercase">⚡ Import Soal Massal (Batch Excel)</h2>
                    <p class="text-xs text-orange-100 mt-0.5">Unggah banyak soal sekaligus menggunakan format Excel (CSV).</p>
                </div>
                <a href="download_template.php" class="bg-white text-orange-600 hover:bg-orange-50 text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-sm inline-flex items-center gap-2">
                    📥 Unduh Template Excel (.CSV)
                </a>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="bg-white/10 p-4 rounded-2xl border border-white/20 flex flex-col sm:flex-row items-center gap-4 text-xs">
                <input type="file" name="file_csv" accept=".csv" required class="w-full text-white file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-white file:text-orange-600 hover:file:bg-orange-50 cursor-pointer">
                <button type="submit" name="import_excel" class="w-full sm:w-auto bg-gray-900 hover:bg-black text-white font-bold px-6 py-3 rounded-xl transition whitespace-nowrap shadow-sm">
                    Upload & Proses Import
                </button>
            </form>
        </div>

        <!-- Form Tambah Soal Manual -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-sm font-bold text-gray-900">Tambah 1 Soal Manual</h2>
            
            <form action="" method="POST" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Teks Pertanyaan / Soal</label>
                    <textarea name="pertanyaan" rows="3" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Tuliskan isi pertanyaan di sini..."></textarea>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kaitkan dengan Video (Opsional)</label>
                    <select name="id_video" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500">
                        <option value="">-- Umum / Tanpa Video Khusus --</option>
                        <?php foreach ($listVideos as $vid): ?>
                            <option value="<?= $vid['id_video']; ?>"><?= htmlspecialchars($vid['judul_video']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-emerald-700 mb-1">Pilihan Jawaban Benar</label>
                        <input type="text" name="pilihan_benar" required class="w-full p-3 bg-emerald-50/50 border border-emerald-200 rounded-xl focus:outline-emerald-500 font-medium" placeholder="Masukkan jawaban yang benar">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Pilihan Pengecoh 1</label>
                        <input type="text" name="pilihan_lain_1" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Jawaban salah 1">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Pilihan Pengecoh 2</label>
                        <input type="text" name="pilihan_lain_2" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Jawaban salah 2">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Pilihan Pengecoh 3</label>
                        <input type="text" name="pilihan_lain_3" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-orange-500" placeholder="Jawaban salah 3">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" name="tambah_soal" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-3 rounded-xl transition shadow-sm">
                        Simpan ke Bank Soal
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Soal dalam Bank Soal -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-sm font-bold text-gray-900">Daftar Soal dalam Ujian Ini (<?= count($questions); ?> Soal)</h2>
            
            <div class="space-y-4">
                <?php if (count($questions) > 0): ?>
                    <?php $no = 1; foreach ($questions as $q): ?>
                    <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-2">
                        <div class="flex justify-between items-start">
                            <p class="font-bold text-gray-900 text-xs">Soal <?= $no++; ?>: <?= htmlspecialchars($q['pertanyaan']); ?></p>
                            <span class="bg-orange-100 text-orange-700 px-2.5 py-0.5 rounded-lg text-[10px] font-extrabold">ID Soal: <?= $q['id_soal']; ?></span>
                        </div>
                        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-gray-600 pt-1">
                            <li class="font-bold text-emerald-600">✔ (Benar): <?= htmlspecialchars($q['pilihan_benar']); ?></li>
                            <li>• Pengecoh: <?= htmlspecialchars($q['pilihan_lain_1']); ?></li>
                            <li>• Pengecoh: <?= htmlspecialchars($q['pilihan_lain_2']); ?></li>
                            <li>• Pengecoh: <?= htmlspecialchars($q['pilihan_lain_3']); ?></li>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-gray-400 text-center py-6">Belum ada soal di bank soal untuk ujian ini.</p>
                <?php endif; ?>
            </div>
        </div>

    </main>

</body>
</html>