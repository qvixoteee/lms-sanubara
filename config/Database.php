<?php
class Database {
    private $host = "localhost";
    private $db_name = "u1112455_lms";
    private $username = "u1112455_lms";
    private $password = "miedzohir123";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Koneksi database error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>