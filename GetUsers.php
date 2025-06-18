<?php
// backend_railway/GetUsers.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Cho phép CORS
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'Database.php';

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === KIỂM TRA QUYỀN TRUY CẬP (ĐƠN GIẢN, CẦN NÂNG CẤP) ===
// Trong ứng dụng thực tế, bạn sẽ xác thực người dùng dựa trên token hoặc session.
// Ở đây, chúng ta giả định rằng chỉ khi có một token nào đó (chưa kiểm tra valid) mới cho phép xem.
// Bạn nên thay thế bằng kiểm tra JWT token và vai trò 'admin' hoặc 'manager' thực sự.
// Ví dụ: Kiểm tra Authorization header và giải mã JWT để lấy role.
// For now, if no userToken in localStorage, frontend won't call this.
// If it's called, we just return all users. For real security, implement token validation.

$conn = pg_connect(DB_CONNECTION_STRING);

if (!$conn) {
    error_log("Lỗi kết nối PostgreSQL: " . pg_last_error());
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.']);
    exit;
}

$users = [];
// Lấy tất cả người dùng, TRỪ mật khẩu băm
$sql = "SELECT id, username, role, created_at FROM users ORDER BY username ASC";
$result = pg_query($conn, $sql);

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
} else {
    error_log("Lỗi truy vấn người dùng: " . pg_last_error($conn));
    echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách người dùng.']);
}

pg_close($conn);
?>
