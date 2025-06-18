<?php
// backend_railway/Login.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Cho phép CORS
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'Database.php'; 

// Đảm bảo chỉ xử lý request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Lấy dữ liệu JSON từ request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Kiểm tra dữ liệu đầu vào
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Tên đăng nhập và mật khẩu không được để trống.']);
    exit;
}

$conn = pg_connect(DB_CONNECTION_STRING);

if (!$conn) {
    error_log("Lỗi kết nối PostgreSQL: " . pg_last_error());
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.']);
    exit;
}

// Tìm người dùng theo username
$sql = "SELECT id, username, password_hash, role FROM users WHERE username = $1";
$result = pg_query_params($conn, $sql, [$username]);

if (!$result) {
    error_log("Lỗi truy vấn tìm người dùng: " . pg_last_error($conn));
    echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn cơ sở dữ liệu.']);
    pg_close($conn);
    exit;
}

$user = pg_fetch_assoc($result);

if ($user) {
    // Xác minh mật khẩu: So sánh mật khẩu người dùng nhập với hash đã lưu
    if (password_verify($password, $user['password_hash'])) {
        // Mật khẩu khớp, đăng nhập thành công
        echo json_encode([
            'success' => true, 
            'message' => 'Đăng nhập thành công!',
            // QUAN TRỌNG: Trả về role và token (giả định)
            'token' => 'user_auth_token_for_' . $user['username'], 
            'role' => $user['role'], 
            'username' => $user['username'] 
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Mật khẩu không khớp
        echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng.'], JSON_UNESCAPED_UNICODE);
    }
} else {
    // Không tìm thấy người dùng
    echo json_encode(['success' => false, 'message' => 'Tên đăng nhập không tồn tại.'], JSON_UNESCAPED_UNICODE);
}

pg_close($conn);
?>
