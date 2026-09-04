<?php
session_start();
include_once '../config/Database.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $noteId = $_GET['id'];
    $action = $_GET['action'];

    // Tentukan status berdasarkan aksi
    $statusHrd = ($action === 'approve') ? 'Disetujui' : 'Ditolak';

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("UPDATE catatan_karyawan SET status_hrd = :status WHERE id = :id");
    $stmt->bindParam(':status', $statusHrd);
    $stmt->bindParam(':id', $noteId);
    
    if ($stmt->execute()) {
        header("Location: ../admin_dashboard.php?status=success_approval");
        exit;
    }
}

header("Location: ../admin_dashboard.php?status=error_approval");
exit;
?>