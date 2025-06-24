<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\config\Database\Database.php

class Database {
    // DB Params
    private $host = 'localhost';
    private $db_name = 'webxudoan_local_db'; // <<< Đảm bảo đây là tên CSDL CHÍNH XÁC của bạn
    private $username = 'root'; // <<< Đảm bảo đây là username CSDL CHÍNH XÁC của bạn
    private $password = ''; // <<< Đảm bảo đây là password CSDL CHÍNH XÁC của bạn
    private $conn; // Biến lưu trữ kết nối PDO (khởi tạo là null)

    // Phương thức chính để thiết lập và trả về kết nối PDO.
    // Nó sẽ chỉ tạo kết nối nếu chưa có, hoặc trả về kết nối hiện có.
    public function connect() {
        // Nếu kết nối đã tồn tại, trả về kết nối đó ngay lập tức (Singleton-like behavior)
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // Chuỗi DSN (Data Source Name)
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name;

            // Tạo đối tượng PDO
            $this->conn = new PDO($dsn, $this->username, $this->password);

            // Thiết lập chế độ báo lỗi (rất quan trọng để debug)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Thiết lập charset để hỗ trợ tiếng Việt
            $this->conn->exec("set names utf8");

        } catch(PDOException $e) {
            // Ghi lỗi vào log server để debug
            error_log('Connection Error in Database.php: ' . $e->getMessage()); 
            // KHÔNG echo hoặc die() ở đây. Ném ngoại lệ để hàm gọi nó xử lý
            throw new PDOException("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage(), (int)$e->getCode());
        }

        return $this->conn; // Trả về đối tượng kết nối PDO
    }

    // Phương thức getConnection() sẽ đơn giản chỉ gọi connect()
    // Giữ lại để tương thích nếu bạn đã quen dùng getConnection()
    public function getConnection() {
        return $this->connect();
    }
}
