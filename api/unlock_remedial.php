<?php
session_start();
include_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$idUser = $_GET['id_user'] ?? null;
$idUjian = $_GET['id_ujian'] ?? null;

if ($idUser && $idUjian) {
    // Hapus baris nilai ujian yang gagal/remedial terakhir milik user tersebut 
    // agar statusnya kembali bersih dan ujian terbuka untuk dikerjakan ulang.
    $stmtDelete = $db->prepare("DELETE FROM nilai_kuis WHERE id_user = :uid AND id_ujian = :ujian AND status_kelulusan = 'Remedial'");
    $stmtDelete->execute([
        ':uid' => $idUser,
        ':ujian' => $idUjian
    ]);
}

header("Location: ../admin_dashboard.php?status=success_unlock");
exit;
?>