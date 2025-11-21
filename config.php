<?php
class Database {
    private $host = "localhost";
    private $db_name = "beach_resort";
    private $username = "root";  // Change if needed
    private $password = "";      // Change if needed
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Create database instance
$database = new Database();
$db = $database->getConnection();
?>