<?php
class NoteManager {
    private $conn;
    private $table_name = "catatan_karyawan";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function submitNote($userId, $videoId, $urlFotoCatatan) {
        $query = "INSERT INTO " . $this->table_name . " (id_user, video_id, url_foto_catatan, status_hrd) 
                  VALUES (:id_user, :video_id, :url_foto_catatan, 'Menunggu')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_user", $userId);
        $stmt->bindParam(":video_id", $videoId);
        $stmt->bindParam(":url_foto_catatan", $urlFotoCatatan);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Foto catatan berhasil diunggah dan menunggu konfirmasi HRD.'];
        }
        return ['success' => false, 'message' => 'Gagal mengunggah catatan.'];
    }
}
?>