<?php
// backend_railway/Register.php
require_once 'cors.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Cho phép CORS
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'Database.php'; // Đảm bảo đường dẫn đến file Database.php là đúng

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

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.']);
    exit;
}

$conn = pg_connect(DB_CONNECTION_STRING);

if (!$conn) {
    error_log("Lỗi kết nối PostgreSQL: " . pg_last_error());
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.']);
    exit;
}

// 1. Kiểm tra xem username đã tồn tại chưa
$check_sql = "SELECT id FROM users WHERE username = $1";
$check_result = pg_query_params($conn, $check_sql, [$username]);

if (!$check_result) {
    error_log("Lỗi truy vấn kiểm tra username: " . pg_last_error($conn));
    echo json_encode(['success' => false, 'message' => 'Lỗi kiểm tra tên đăng nhập.']);
    pg_close($conn);
    exit;
}

if (pg_num_rows($check_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.']);
    pg_close($conn);
    exit;
}

// 2. Hash mật khẩu trước khi lưu vào database
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 3. Chèn người dùng mới vào database
// Mặc định cho vai trò (role) là 'user'
$insert_sql = "INSERT INTO users (username, password_hash, role) VALUES ($1, $2, 'user')"; // Giả định có cột 'role'
$insert_result = pg_query_params($conn, $insert_sql, [$username, $hashed_password]);

if ($insert_result) {
    echo json_encode(['success' => true, 'message' => 'Đăng ký thành công!']);
} else {
    error_log("Lỗi khi chèn người dùng: " . pg_last_error($conn));
    echo json_encode(['success' => false, 'message' => 'Lỗi khi đăng ký tài khoản.']);
}

pg_close($conn);
?>
