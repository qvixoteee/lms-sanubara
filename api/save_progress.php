<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once '../config/Database.php';
    
    $database = new Database();
    $db = $database->getConnection();

    $userId = $_SESSION['user']['id'];
    $videoId = $_POST['video_id'] ?? '';
    $persentase = intval($_POST['persentase'] ?? 0);
    $statusSelesai = ($persentase >= 95) ? 1 : 0;

    if (empty($videoId)) {
        echo json_encode(['success' => false, 'message' => 'Video ID tidak valid']);
        exit;
    }

    // Gunakan UPSERT (Insert atau Update jika data user & video sudah ada)
    $query = "INSERT INTO video_progress (id_user, video_id, persentase_progres, status_selesai) 
              VALUES (:id_user, :video_id, :persentase, :status_selesai)
              ON DUPLICATE KEY UPDATE 
              persentase_progres = GREATEST(persentase_progres, :persentase_update),
              status_selesai = GREATEST(status_selesai, :status_update)";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_user', $userId);
    $stmt->bindParam(':video_id', $videoId);
    $stmt->bindParam(':persentase', $persentase);
    $stmt->bindParam(':status_selesai', $statusSelesai);
    $stmt->bindParam(':persentase_update', $persentase);
    $stmt->bindParam(':status_update', $statusSelesai);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Progres tersimpan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan progres']);
    }
}
?>