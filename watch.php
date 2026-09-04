<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$videoId = $_GET['id'] ?? null;
if (!$videoId) {
    header("Location: index.php");
    exit;
}

include_once 'config/Database.php';
$database = new Database();
$db = $database->getConnection();

$currentUser = $_SESSION['user'];

// Ambil detail video
$stmt = $db->prepare("SELECT * FROM videos WHERE id_video = :id LIMIT 1");
$stmt->bindParam(":id", $videoId);
$stmt->execute();
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    echo "Modul video tidak ditemukan.";
    exit;
}

// Tangkap parameter status dari URL
$status = $_GET['status'] ?? '';

// Ambil progres awal user untuk video ini dari database
$stmtProg = $db->prepare("SELECT persentase_progres FROM video_progress WHERE id_user = :uid AND video_id = :vid LIMIT 1");
$stmtProg->bindParam(":uid", $currentUser['id']);
$stmtProg->bindParam(":vid", $videoId);
$stmtProg->execute();
$progData = $stmtProg->fetch(PDO::FETCH_ASSOC);
$initialPercent = $progData ? intval($progData['persentase_progres']) : 0;

// Ekstrak YouTube ID
$ytId = "";
if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video['url_video'], $match)) {
    $ytId = $match[1];
}

// Cek status catatan yang sudah diunggah oleh user untuk video ini
$stmtNoteCheck = $db->prepare("SELECT status_hrd, url_foto_catatan FROM catatan_karyawan WHERE id_user = :uid AND video_id = :vid ORDER BY id DESC LIMIT 1");
$stmtNoteCheck->bindParam(':uid', $currentUser['id']);
$stmtNoteCheck->bindParam(':vid', $videoId);
$stmtNoteCheck->execute();
$noteData = $stmtNoteCheck->fetch(PDO::FETCH_ASSOC);

$statusHrd = $noteData ? $noteData['status_hrd'] : null;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menonton: <?= htmlspecialchars($video['judul_video']); ?> - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.youtube.com/iframe_api"></script>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 font-sans antialiased min-h-screen flex flex-col">

    <main class="flex-grow max-w-5xl mx-auto px-4 py-8 w-full space-y-6">
        
        <!-- HEADER BARU: Kotak Rounded & Badge Jabatan yang Selaras dengan Dashboard -->
        <div class="flex justify-between items-center mb-6 bg-white/60 backdrop-blur-md p-4 rounded-3xl shadow-sm border border-white/40">
            <!-- Tombol Kembali dengan Ikon Kotak Rounded -->
            <a href="video_list.php" class="inline-flex items-center gap-2.5 bg-white hover:bg-gray-50 text-indigo-600 px-4 py-2.5 rounded-2xl shadow-sm border border-gray-100 text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                </svg>
                Kembali
            </a>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <div>
                <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded font-medium uppercase"><?= htmlspecialchars($video['kategori']); ?></span>
                <h2 class="text-xl font-bold text-slate-800 mt-2"><?= htmlspecialchars($video['judul_video']); ?></h2>
                <p class="text-xs text-slate-500 mt-1">Durasi modul: <?= htmlspecialchars($video['durasi_menit']); ?> Menit</p>
            </div>

            <?php if ($ytId): ?>
                <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-black shadow-inner">
                    <div id="player" class="w-full h-full" data-ytid="<?= $ytId; ?>"></div>
                </div>
            <?php else: ?>
                <div class="p-6 bg-slate-100 text-center text-sm text-slate-600 rounded-xl">
                    Format video eksternal. <a href="<?= $video['url_video']; ?>" target="_blank" class="text-blue-600 underline font-semibold">Buka Tautan Video</a>
                </div>
            <?php endif; ?>

            <div class="space-y-1 pt-2">
                <div class="flex justify-between text-xs text-slate-600 font-medium">
                    <span>Progres Menonton Tersimpan:</span>
                    <span id="progress-text" class="text-blue-600"><?= $initialPercent; ?>%</span>
                </div>
                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div id="progress-bar" class="bg-blue-600 h-full transition-all duration-300" style="width: <?= $initialPercent; ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-6 shadow-sm border border-white/40 space-y-4">
        <h3 class="font-bold text-gray-900 text-base">Unggah Berkas Catatan Belajar</h3>
        
        <?php if ($statusHrd === 'Disetujui'): ?>
            <!-- Jika sudah disetujui HRD -->
            <!-- KODE BARU YANG RESPONSIF DI MOBILE -->
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl flex items-start gap-3 w-full">
                <!-- Icon Centang -->
                <div class="flex-shrink-0 mt-0.5">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs">✓</span>
                </div>
                
                <!-- Blok Teks Status (Mengalir rapi tanpa terpotong) -->
                <div class="text-xs sm:text-sm text-emerald-900 leading-relaxed">
                    Catatan telah dikonfirmasi dan <strong class="font-bold text-emerald-700">Disetujui HRD</strong>.
                </div>
            </div>
        <?php elseif ($statusHrd === 'Menunggu'): ?>
            <!-- Jika sedang menunggu konfirmasi -->
            <div class="bg-amber-50 text-amber-700 border border-amber-200 text-xs p-4 rounded-2xl font-medium flex items-center gap-2">
                ⏳ Catatan berhasil diunggah. Status: <b>Menunggu Konfirmasi HRD</b>.
            </div>
            <div class="p-4 bg-gray-50 rounded-2xl text-xs text-gray-500 text-center">
                Berkas PDF Anda sedang ditinjau oleh tim HRD. Silakan menunggu persetujuan untuk membuka akses ujian.
            </div>
        <?php elseif ($statusHrd === 'Ditolak'): ?>
            <!-- Jika ditolak, beri kesempatan unggah ulang -->
            <div class="bg-rose-50 text-rose-700 border border-rose-200 text-xs p-4 rounded-2xl font-medium">
                ❌ Catatan Anda ditolak oleh HRD. Silakan unggah ulang berkas PDF yang benar.
            </div>
            <form action="api/submit_note.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="video_id" value="<?= htmlspecialchars($video['id_video']); ?>">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Unggah Ulang Berkas PDF (Maks. 10MB)</label>
                    <input type="file" name="file_catatan" accept="application/pdf" required class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-2xl p-1 bg-white/50">
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-2xl text-xs shadow-md hover:bg-indigo-700 transition">
                    Kirim Ulang Catatan ke HRD
                </button>
            </form>
        <?php else: ?>
            <!-- Belum pernah unggah, ikuti syarat progres video -->
            <div id="lock-msg" class="text-xs p-4 rounded-2xl font-medium flex items-center gap-2 border <?= $initialPercent >= 95 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?>">
                <?= $initialPercent >= 95 ? '✅ Syarat menonton terpenuhi! Silakan unggah berkas catatan PDF Anda.' : '🔒 Form unggah catatan terkunci. Selesaikan menonton video hingga minimal 95% untuk membuka fitur ini.'; ?>
            </div>

            <form action="api/submit_note.php" method="POST" enctype="multipart/form-data" id="form-note" class="space-y-4 <?= $initialPercent >= 95 ? '' : 'opacity-50 pointer-events-none'; ?>">
                <input type="hidden" name="video_id" value="<?= htmlspecialchars($video['id_video']); ?>">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Unggah Berkas Catatan (Format PDF, Maks. 10MB)</label>
                    <input type="file" name="file_catatan" id="input-note" accept="application/pdf" <?= $initialPercent >= 95 ? '' : 'disabled'; ?> required class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-2xl p-1 bg-white/50">
                </div>

                <!-- Menampilkan Pesan Status Berdasarkan URL -->
                <?php if ($status === 'success'): ?>
                    <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs p-4 rounded-2xl font-medium mb-4 flex items-center gap-2 shadow-sm">
                        <span>✅ Catatan berhasil dikirim! Status Anda saat ini: <b>Menunggu Konfirmasi HRD</b>.</span>
                    </div>
                <?php elseif ($status === 'too_large'): ?>
                    <div class="bg-rose-50 text-rose-800 border border-rose-200 text-xs p-4 rounded-2xl font-medium mb-4 flex items-center gap-2 shadow-sm">
                        <span>❌ Ukuran berkas terlalu besar! Maksimal ukuran file adalah 10MB. Silakan kompres PDF Anda terlebih dahulu.</span>
                    </div>
                <?php elseif ($status === 'invalid_format'): ?>
                    <div class="bg-amber-50 text-amber-800 border border-amber-200 text-xs p-4 rounded-2xl font-medium mb-4 flex items-center gap-2 shadow-sm">
                        <span>⚠️ Format berkas tidak valid! Harap unggah berkas dengan format PDF.</span>
                    </div>
                <?php elseif ($status === 'invalid'): ?>
                    <div class="bg-rose-50 text-rose-800 border border-rose-200 text-xs p-4 rounded-2xl font-medium mb-4 flex items-center gap-2 shadow-sm">
                        <span>❌ Terjadi kesalahan saat mengunggah berkas. Silakan coba lagi.</span>
                    </div>
                <?php endif; ?>

                <button type="submit" id="btn-note" <?= $initialPercent >= 95 ? '' : 'disabled'; ?> class="w-full text-white font-bold py-2.5 rounded-2xl text-xs transition shadow-md <?= $initialPercent >= 95 ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'; ?>">
                    Kirim Catatan ke HRD
                </button>
            </form>
        <?php endif; ?>
    </div>
    </main>

    <script>
        var player;
        var trackingTimer;
        var currentSavedPercent = <?= $initialPercent; ?>;
        var videoId = "<?= $videoId; ?>";

        function onYouTubeIframeAPIReady() {
            var el = document.getElementById('player');
            if (el) {
                var ytid = el.getAttribute('data-ytid');
                player = new YT.Player('player', {
                    height: '100%',
                    width: '100%',
                    playerVars: {
                        'rel': 0 // Mencegah munculnya video rekomendasi dari channel lain di akhir pemutaran
                    },
                    videoId: ytid,
                    events: {
                        'onStateChange': onPlayerStateChange
                    }
                });
            }
        }

        function onPlayerStateChange(event) {
            if (event.data == YT.PlayerState.PLAYING) {
                if (!trackingTimer) {
                    trackingTimer = setInterval(function() {
                        if (player && typeof player.getCurrentTime === 'function') {
                            var currentTime = player.getCurrentTime();
                            var duration = player.getDuration();
                            
                            if (duration > 0) {
                                var percent = Math.floor((currentTime / duration) * 100);
                                if (percent > 100) percent = 100;

                                // Update UI jika persentase bertambah
                                if (percent > currentSavedPercent) {
                                    currentSavedPercent = percent;
                                    document.getElementById('progress-text').innerText = percent + '%';
                                    document.getElementById('progress-bar').style.width = percent + '%';

                                    // Kirim ke server via AJAX untuk disimpan permanen di tabel video_progress
                                    saveProgressToServer(percent);

                                    if (percent >= 95) {
                                        unlockForm();
                                        clearInterval(trackingTimer);
                                    }
                                }
                            }
                        }
                    }, 2000); // Cek tiap 2 detik
                }
            } else {
                if (trackingTimer) {
                    clearInterval(trackingTimer);
                    trackingTimer = null;
                }
            }
        }

        function saveProgressToServer(percent) {
            var formData = new FormData();
            formData.append('video_id', videoId);
            formData.append('persentase', percent);

            fetch('api/save_progress.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
              .then(data => {
                  console.log("Progres tersimpan:", data.message);
              }).catch(err => {
                  console.error("Gagal menyimpan progres:", err);
              });
        }

        function unlockForm() {
            var lockMsg = document.getElementById('lock-msg');
            var form = document.getElementById('form-note');
            var input = document.getElementById('input-note');
            var btn = document.getElementById('btn-note');

            if (lockMsg) {
                lockMsg.innerHTML = "✅ Syarat menonton terpenuhi! Silakan unggah catatan fisik Anda.";
                lockMsg.classList.remove('bg-amber-50', 'text-amber-700', 'border-amber-200');
                lockMsg.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
            }
            if (form) form.classList.remove('opacity-50', 'pointer-events-none');
            if (input) input.removeAttribute('disabled');
            if (btn) {
                btn.removeAttribute('disabled');
                btn.classList.remove('cursor-not-allowed');
                btn.classList.add('bg-blue-600');
            }
        }

        // Jika saat dimuat nilai awal sudah >= 95
        if (currentSavedPercent >= 95) {
            unlockForm();
        }
    </script>

    <footer class="text-center py-6 text-xs text-slate-400 border-t border-slate-200 mt-auto">
        &copy; 2026 Sanubara Learning Center. All rights reserved.
    </footer>
</body>
</html>