<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\models\User.php

// Giả định bạn có một thư viện JWT, ví dụ: firebase/php-jwt
// Nếu chưa có, bạn cần cài đặt bằng Composer: composer require firebase/php-jwt
// Đảm bảo đường dẫn đến autoload.php là chính xác tùy thuộc vào vị trí thư mục vendor
// Đường dẫn này là từ backend/models/User.php (trong /backend/models/)
// -> đi ra 2 cấp thư mục (lên /backend/, rồi lên /webxudoan/) -> vào vendor/autoload.php
require_once __DIR__ . '/../../vendor/autoload.php'; // ĐIỀU CHỈNH ĐƯỜNG DẪN TẠI ĐÂY
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User {
    private $conn;
    private $table = 'users'; // Tên bảng người dùng trong database

    // Thuộc tính người dùng (để gán dữ liệu từ form/DB)
    public $id;
    public $username;
    public $email;
    public $password; // Mật khẩu thô (cho đăng ký)
    public $password_hash; // Mật khẩu đã hash (lưu trong DB)
    public $role;
    public $full_name; // Thêm trường full_name
    public $created_at; 
    public $updated_at; 

    // JWT Secret Key - KHÔNG CÔNG KHAI TRONG SẢN PHẨM THỰC TẾ
    // Nên lấy từ biến môi trường hoặc file cấu hình an toàn
    // THAY ĐỔI KHÓA NÀY BẰNG MỘT CHUỖI MẠNH VÀ NGẪU NHIÊN!
    // Ví dụ: Bạn có thể tạo bằng PHP: bin2hex(random_bytes(32)) -> sẽ ra 64 ký tự hex
    private $jwt_secret = "YOUR_SUPER_SECRET_JWT_KEY_HERE_CHANGE_THIS"; 

    // Constructor với DB Connection
    public function __construct($db) {
        $this->conn = $db;
        // Đảm bảo JWT_SECRET được định nghĩa và đã được thay đổi từ giá trị mặc định ban đầu
        if (empty($this->jwt_secret) || $this->jwt_secret === "YOUR_SUPER_SECRET_JWT_KEY_HERE_CHANGE_THIS") { 
            error_log("CẢNH BÁO: JWT_SECRET chưa được thay đổi hoặc không được định nghĩa!");
            // Nếu bạn muốn yêu cầu JWT_SECRET, có thể ném exception ở đây
            // throw new Exception("JWT Secret Key is not configured.");
        }
    }

    // Phương thức đăng ký người dùng
    public function register() {
        // Kiểm tra xem username hoặc email đã tồn tại chưa
        if ($this->usernameExists()) {
            return false; // Trả về false nếu đã tồn tại
        }
        if ($this->emailExists()) {
            return false;
        }

        // Tạo password hash
        $this->password_hash = password_hash($this->password, PASSWORD_DEFAULT);

        // Chuẩn bị truy vấn INSERT
        $query = "INSERT INTO " . $this->table . "
                  SET
                      username = :username,
                      email = :email,
                      password_hash = :password_hash,
                      role = :role,
                      full_name = :full_name,
                      created_at = NOW(),
                      updated_at = NOW()"; 

        // Chuẩn bị statement
        $stmt = $this->conn->prepare($query);

        // Clean data (Sanitize)
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));

        // Bind data
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':full_name', $this->full_name);

        // Thực thi truy vấn
        if ($stmt->execute()) {
            return true;
        }

        // Ghi lỗi nếu có
        error_log("User registration error: " . $stmt->errorInfo()[2]);
        return false;
    }

    // Phương thức kiểm tra tên đăng nhập đã tồn tại chưa
    public function usernameExists() {
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $this->username = htmlspecialchars(strip_tags($this->username));
        $stmt->bindParam(':username', $this->username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Phương thức kiểm tra email đã tồn tại chưa
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $this->email = htmlspecialchars(strip_tags($this->email));
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Phương thức tìm người dùng bằng username hoặc email (QUAN TRỌNG CHO LOGIN)
    public function findByUsernameOrEmail($username_or_email) {
        $query = "SELECT id, username, email, password_hash, role, full_name
                  FROM " . $this->table . "
                  WHERE username = :username_or_email OR email = :username_or_email
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $username_or_email = htmlspecialchars(strip_tags($username_or_email));
        $stmt->bindParam(':username_or_email', $username_or_email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Gán các giá trị tìm được vào thuộc tính của đối tượng user (tùy chọn)
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];
            $this->full_name = $row['full_name'];
            return $row; // Trả về toàn bộ hàng dữ liệu
        }
        return false;
    }

    // Phương thức tạo JWT Token (QUAN TRỌNG CHO LOGIN)
    public function createJwtToken($user_id, $username, $role) {
        $issued_at = time();
        $expiration_time = $issued_at + (60 * 60); // Token có hiệu lực 1 giờ (60 giây * 60 phút)

        $token_payload = array(
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "data" => array(
                "id" => $user_id,
                "username" => $username,
                "role" => $role
            )
        );

        // Encode the token
        try {
            $jwt = JWT::encode($token_payload, $this->jwt_secret, 'HS256');
            return $jwt;
        } catch (Exception $e) {
            error_log("Error creating JWT: " . $e->getMessage());
            return false;
        }
    }

    // Phương thức xác minh mật khẩu (chỉ là wrapper cho password_verify)
    public function verifyPassword($plain_password, $hashed_password) {
        return password_verify($plain_password, $hashed_password);
    }

    // Phương thức đọc tất cả người dùng (ví dụ cho admin)
    public function read() {
        $query = 'SELECT id, username, email, full_name, role, created_at FROM ' . $this->table . ' ORDER BY created_at DESC';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Phương thức đọc một người dùng theo ID
    public function readSingle() {
        $query = 'SELECT id, username, email, full_name, role, created_at, updated_at
                  FROM ' . $this->table . '
                  WHERE id = :id LIMIT 0,1';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->full_name = $row['full_name'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at']; 
            return true;
        }
        return false;
    }

    // Phương thức cập nhật người dùng (ví dụ: đổi mật khẩu, email, full_name)
    public function update() {
        $query = 'UPDATE ' . $this->table . '
                  SET
                    username = :username,
                    email = :email,
                    full_name = :full_name,
                    updated_at = NOW()
                  WHERE
                    id = :id';
        $stmt = $this->conn->prepare($query);

        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        error_log("User update error: " . $stmt->errorInfo()[2]);
        return false;
    }

    // Phương thức cập nhật vai trò người dùng (chỉ admin có thể dùng)
    public function updateRole() {
        $query = 'UPDATE ' . $this->table . '
                  SET
                    role = :role,
                    updated_at = NOW()
                  WHERE
                    id = :id';
        $stmt = $this->conn->prepare($query);

        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        error_log("User role update error: " . $stmt->errorInfo()[2]);
        return false;
    }

    // Phương thức xóa người dùng
    public function delete() {
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        error_log("User delete error: " . $stmt->errorInfo()[2]);
        return false;
    }
}
