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

// Data Ringkasan Karyawan untuk Tab 1
$stmtUsers = $db->prepare("SELECT id_user, nama_lengkap, jabatan, pabrik FROM users ORDER BY nama_lengkap ASC");
$stmtUsers->execute();
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$rekapKaryawan = [];
foreach ($allUsers as $usr) {
    $uid = $usr['id_user'];
    $role = $usr['jabatan'];

    $stmtVidCount = $db->prepare("SELECT COUNT(*) as total FROM videos WHERE status = 'Aktif' AND (akses_jabatan LIKE :jabatan OR akses_jabatan LIKE '%Semua Jabatan%')");
    $stmtVidCount->bindValue(':jabatan', "%" . $role . "%");
    $stmtVidCount->execute();
    $totalVidTersedia = $stmtVidCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmtSelesai = $db->prepare("
        SELECT COUNT(DISTINCT vp.video_id) as selesai 
        FROM video_progress vp 
        JOIN videos v ON vp.video_id = v.id_video 
        WHERE vp.id_user = :uid AND vp.persentase_progres >= 95 
        AND v.status = 'Aktif' AND (v.akses_jabatan LIKE :jabatan OR v.akses_jabatan LIKE '%Semua Jabatan%')
    ");
    $stmtSelesai->bindValue(':uid', $uid);
    $stmtSelesai->bindValue(':jabatan', "%" . $role . "%");
    $stmtSelesai->execute();
    $totalVidSelesai = $stmtSelesai->fetch(PDO::FETCH_ASSOC)['selesai'] ?? 0;

    $persentase = $totalVidTersedia > 0 ? round(($totalVidSelesai / $totalVidTersedia) * 100) : 0;

    $rekapKaryawan[] = [
        'id_user' => $uid,
        'nama' => $usr['nama_lengkap'],
        'jabatan' => $role,
        'pabrik' => $usr['pabrik'] ?? '-',
        'selesai' => $totalVidSelesai,
        'tersedia' => $totalVidTersedia,
        'persentase' => $persentase
    ];
}

// Data Catatan Menunggu (Tab 2 - Atas)
$stmtNotes = $db->prepare("SELECT c.*, u.nama_lengkap as nama_karyawan, v.judul_video FROM catatan_karyawan c JOIN users u ON c.id_user = u.id_user JOIN videos v ON c.video_id = v.id_video WHERE c.status_hrd = 'Menunggu' ORDER BY c.id DESC");
$stmtNotes->execute();
$pendingNotes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

// Data Riwayat Catatan yang Sudah Diproses (Tab 2 - Bawah)
$stmtHistoryNotes = $db->prepare("SELECT c.*, u.nama_lengkap as nama_karyawan, v.judul_video FROM catatan_karyawan c JOIN users u ON c.id_user = u.id_user JOIN videos v ON c.video_id = v.id_video WHERE c.status_hrd != 'Menunggu' ORDER BY c.id DESC LIMIT 20");
$stmtHistoryNotes->execute();
$historyNotes = $stmtHistoryNotes->fetchAll(PDO::FETCH_ASSOC);

// Data Ujian (Tab 3)
$stmtExams = $db->prepare("SELECT n.*, u.nama_lengkap as nama_karyawan, e.nama_ujian, e.passing_grade FROM nilai_kuis n JOIN users u ON n.id_user = u.id_user JOIN exams e ON n.id_ujian = e.id_ujian ORDER BY n.id DESC");
$stmtExams->execute();
$examResults = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['user']['name'] ?? 'HRD Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard HRD - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    function switchTab(tabId, btnElement) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.tab-nav-link').forEach(el => {
            el.classList.remove('bg-white/25', 'font-bold', 'text-white');
            el.classList.add('text-orange-200', 'font-medium');
        });

        document.getElementById(tabId).classList.remove('hidden');
        btnElement.classList.remove('text-orange-200', 'font-medium');
        btnElement.classList.add('bg-white/25', 'font-bold', 'text-white');
    }
    </script>
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
                <p class="text-[10px] font-bold text-orange-200 uppercase tracking-wider mb-2 px-1">Pantau & Kontrol</p>
                <nav class="space-y-1.5 text-xs">
                    <button onclick="switchTab('tab-ringkasan', this)" class="tab-nav-link w-full text-left px-4 py-3 rounded-2xl bg-white/25 font-bold text-white transition flex items-center gap-3">
                        <svg class="w-5 h-5 text-white-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Ringkasan Progres
                    </button>
                    <button onclick="switchTab('tab-catatan', this)" class="tab-nav-link w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        <svg class="w-5 h-5 text-white-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                            Persetujuan Catatan (<?= count($pendingNotes); ?>)
                    </button>
                    <button onclick="switchTab('tab-ujian', this)" class="tab-nav-link w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        <svg class="w-5 h-5 text-white-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                            Evaluasi & Remediasi
                    </button>
                </nav>
            </div>

            <div>
                <p class="text-[10px] font-bold text-orange-200 uppercase tracking-wider mb-2 px-1">Manajemen Sistem</p>
                <nav class="space-y-1.5 text-xs">
                    <a href="admin_videos.php" class="w-full text-left px-4 py-3 rounded-2xl text-orange-200 hover:bg-white/10 font-medium transition flex items-center gap-3">
                        <svg class="w-5 h-5 text-white-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                            Kelola Data LMS
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
    <main class="flex-1 p-4 sm:p-8 overflow-y-auto">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900">Dashboard HRD</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola dan pantau progres pembelajaran seluruh karyawan.</p>
            </div>
            <div class="md:hidden flex items-center gap-2">
                <a href="admin_videos.php" class="bg-orange-500 text-white text-xs font-bold px-3 py-2 rounded-xl">Video</a>
                <a href="admin_exams.php" class="bg-orange-500 text-white text-xs font-bold px-3 py-2 rounded-xl">Ujian</a>
                <a href="logout.php" class="bg-rose-50 text-rose-600 text-xs font-bold px-3 py-2 rounded-xl">Keluar</a>
            </div>
        </div>

        <!-- TAB 1: RINGKASAN PROGRES KARYAWAN -->
        <div id="tab-ringkasan" class="tab-content bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-bold text-gray-900">Rekapitulasi Progres Karyawan</h2>
                <span class="text-[11px] text-gray-400">Klik baris untuk melihat detail</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                            <th class="py-3 px-4">No</th>
                            <th class="py-3 px-4">Nama Karyawan</th>
                            <th class="py-3 px-4">Jabatan</th>
                            <th class="py-3 px-4">Pabrik</th>
                            <th class="py-3 px-4">Modul Selesai</th>
                            <th class="py-3 px-4">Progres Keseluruhan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($rekapKaryawan) > 0): ?>
                            <?php $no = 1; foreach ($rekapKaryawan as $rk): ?>
                            <tr onclick="window.location='admin_user_detail.php?id=<?= $rk['id_user']; ?>'" class="hover:bg-orange-50/40 transition cursor-pointer">
                                <td class="py-3.5 px-4 font-semibold text-gray-400"><?= $no++; ?></td>
                                <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($rk['nama']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($rk['jabatan']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($rk['pabrik']); ?></td>
                                <td class="py-3.5 px-4 font-semibold text-orange-600"><?= $rk['selesai']; ?> / <?= $rk['tersedia']; ?></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-32 bg-gray-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-orange-600 h-full rounded-full" style="width: <?= $rk['persentase']; ?>%;"></div>
                                        </div>
                                        <span class="font-bold text-gray-700"><?= $rk['persentase']; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400">Belum ada data karyawan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: PERSETUJUAN CATATAN -->
        <div id="tab-catatan" class="tab-content hidden space-y-6">
            
            <!-- Tabel Menunggu Konfirmasi -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 mb-2">Persetujuan Catatan Belajar (Menunggu Konfirmasi)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Karyawan</th>
                                <th class="py-3 px-4">Modul Video</th>
                                <th class="py-3 px-4">Berkas Catatan</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (count($pendingNotes) > 0): ?>
                                <?php foreach ($pendingNotes as $note): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($note['nama_karyawan']); ?></td>
                                    <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($note['judul_video']); ?></td>
                                    <td class="py-3.5 px-4">
                                        <a href="<?= htmlspecialchars($note['url_foto_catatan']); ?>" target="_blank" class="text-orange-600 hover:underline font-bold">
                                            Lihat Berkas PDF ↗
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-center space-x-2">
                                        <a href="api/process_approval.php?id=<?= $note['id']; ?>&action=approve" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-3 py-1.5 rounded-xl transition inline-block">Setujui</a>
                                        <a href="api/process_approval.php?id=<?= $note['id']; ?>&action=reject" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-3 py-1.5 rounded-xl transition inline-block">Tolak</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-400">Tidak ada catatan yang menunggu konfirmasi.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Riwayat Catatan (Sudah Disetujui / Ditolak) -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 mb-2">Riwayat Keputusan Catatan (Disetujui / Ditolak)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Karyawan</th>
                                <th class="py-3 px-4">Modul Video</th>
                                <th class="py-3 px-4">Berkas Catatan</th>
                                <th class="py-3 px-4">Status Keputusan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (count($historyNotes) > 0): ?>
                                <?php foreach ($historyNotes as $hist): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($hist['nama_karyawan']); ?></td>
                                    <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($hist['judul_video']); ?></td>
                                    <td class="py-3.5 px-4">
                                        <a href="<?= htmlspecialchars($hist['url_foto_catatan']); ?>" target="_blank" class="text-orange-600 hover:underline font-bold">
                                            Lihat Berkas PDF ↗
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if ($hist['status_hrd'] === 'Disetujui'): ?>
                                            <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full font-bold">Disetujui</span>
                                        <?php else: ?>
                                            <span class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full font-bold">Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-400">Belum ada riwayat keputusan catatan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- TAB 3: EVALUASI & REMEDIASI -->
        <div id="tab-ujian" class="tab-content hidden bg-white rounded-3xl p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 mb-2">Hasil Ujian & Remediasi Karyawan</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Karyawan</th>
                            <th class="py-3 px-4">Ujian</th>
                            <th class="py-3 px-4">Nilai</th>
                            <th class="py-3 px-4">Passing Grade</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-center">Aksi Remediasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($examResults) > 0): ?>
                            <?php foreach ($examResults as $res): ?>
                            <?php $isRemediasi = $res['nilai'] < $res['passing_grade']; ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3.5 px-4 font-bold text-gray-900"><?= htmlspecialchars($res['nama_karyawan']); ?></td>
                                <td class="py-3.5 px-4 text-gray-600"><?= htmlspecialchars($res['nama_ujian']); ?></td>
                                <td class="py-3.5 px-4 font-extrabold <?= $isRemediasi ? 'text-rose-600' : 'text-emerald-600'; ?>"><?= htmlspecialchars($res['nilai']); ?></td>
                                <td class="py-3.5 px-4 text-gray-500"><?= htmlspecialchars($res['passing_grade']); ?></td>
                                <td class="py-3.5 px-4">
                                    <?php if ($isRemediasi): ?>
                                        <span class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full font-bold">Remediasi</span>
                                    <?php else: ?>
                                        <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full font-bold">Lulus</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($res['status_kelulusan'] === 'Remedial'): ?>
                                        <a href="api/unlock_remedial.php?id_user=<?= $res['id_user']; ?>&id_ujian=<?= $res['id_ujian']; ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-xl transition inline-block">
                                            Buka Kesempatan Ulang
                                        </a>
                                    <?php elseif ($res['status_kelulusan'] === 'Izin Remedial'): ?>
                                        <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full font-bold text-[11px]">Menunggu Ujian Ulang</span>
                                    <?php elseif ($res['status_kelulusan'] === 'Lulus'): ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400">Belum ada data hasil ujian.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>