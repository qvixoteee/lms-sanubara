<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once '../config/Database.php';
    include_once '../classes/NoteManager.php';

    $database = new Database();
    $db = $database->getConnection();

    $userId = $_SESSION['user']['id'] ?? 1;
    $videoId = $_POST['video_id'] ?? '';
    
    if (isset($_FILES['file_catatan']) && $_FILES['file_catatan']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['file_catatan']['tmp_name'];
        $fileName = $_FILES['file_catatan']['name'];
        $fileSize = $_FILES['file_catatan']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validasi format harus PDF
        if ($fileExtension !== 'pdf') {
            header("Location: ../watch.php?id=" . $videoId . "&status=invalid_format");
            exit;
        }

        // Validasi ukuran maksimal 10MB (10485760 bytes)
        if ($fileSize > 10485760) {
            header("Location: ../watch.php?id=" . $videoId . "&status=too_large");
            exit;
        }

        // Direktori lokal server
        $uploadDir = '../uploads/catatan/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = 'catatan_user_' . $userId . '_' . $videoId . '_' . time() . '.pdf';
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $fileUrl = 'uploads/catatan/' . $newFileName;
            
            $noteManager = new NoteManager($db);
            $result = $noteManager->submitNote($userId, $videoId, $fileUrl);

            if ($result['success']) {
                header("Location: ../watch.php?id=" . $videoId . "&status=success");
                exit;
            }
        }
    }

    header("Location: ../watch.php?id=" . $videoId . "&status=invalid");
    exit;
}
?>