<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class Auth {
    private $secret_key;
    private $issuer;
    private $audience;
    private $expire_time; // Thời gian hết hạn của token (tính bằng giây)

    public function __construct() {
        // Bạn nên định nghĩa khóa bí mật này trong một file cấu hình an toàn hơn (ví dụ: .env)
        $this->secret_key = "YOUR_SUPER_SECRET_KEY_HERE_SHOULD_BE_LONG_AND_RANDOM"; 
        $this->issuer = "http://localhost/webxudoan"; // Issuer của token
        $this->audience = "http://localhost:3000"; // Đối tượng mà token này dành cho
        $this->expire_time = 3600; // Token hết hạn sau 1 giờ (3600 giây)
    }

    /**
     * Tạo JWT token
     * @param array $data Dữ liệu để mã hóa vào token (ví dụ: user_id, username, role)
     * @return string JWT token
     */
    public function generateToken(array $data) {
        $issued_at = time();
        $expiration_time = $issued_at + $this->expire_time; // token hết hạn sau 1 giờ

        $payload = array(
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "data" => $data // Dữ liệu của người dùng
        );

        // Mã hóa token
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');
        return $jwt;
    }

    /**
     * Xác thực JWT token
     * @param string $jwt Token cần xác thực
     * @return object|false Dữ liệu payload nếu token hợp lệ, ngược lại false
     */
    public function validateToken(string $jwt) {
        if (empty($jwt)) {
            return false;
        }

        try {
            // Giải mã token
            $decoded = JWT::decode($jwt, new Key($this->secret_key, 'HS256'));
            return $decoded->data; // Trả về phần 'data' của payload
        } catch (Exception $e) {
            // Log lỗi (ví dụ: Token đã hết hạn, chữ ký không hợp lệ, ...)
            // error_log("JWT validation error: " . $e->getMessage());
            return false;
        }
    }
}