<?php
// backend_railway/UpdateUserRole.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'Database.php'; // Đảm bảo đã định nghĩa ROLE_USER, ROLE_ADMIN, ROLE_MANAGER

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========================================================================
// === PHẦN QUAN TRỌNG NHẤT: KIỂM TRA QUYỀN TRUY CẬP (SERVER-SIDE) ===
// ========================================================================
// Trong ứng dụng thực tế, bạn PHẢI xác thực người dùng đang gửi request.
// Đây là nơi bạn sẽ kiểm tra xem người dùng đó CÓ VAI TRÒ 'admin' hay 'manager' (nếu cho phép) hay không.
// Ví dụ: Sử dụng JWT (JSON Web Tokens).

// BƯỚC 1: Lấy JWT token từ Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

// BƯỚC 2: Kiểm tra token và giải mã để lấy vai trò của người dùng hiện tại
// (Bạn sẽ cần một thư viện JWT và logic giải mã ở đây)
// Giả định: hàm `decodeJwtAndGetUserRole` của bạn sẽ trả về vai trò của người dùng đã xác thực.
// Nếu token không hợp lệ hoặc không có quyền truy cập:
// Ví dụ (chưa có hàm giải mã thực tế):
$currentUserRole = null; // Mặc định là không có vai trò cho người gửi request
// Thực tế, bạn sẽ gọi một hàm như: $currentUserRole = decodeJwtAndGetUserRole($token);

// GIẢ ĐỊNH TẠM THỜI ĐỂ TEST (BẠN CẦN THAY THẾ BẰNG CƠ CHẾ XÁC THỰC THẬT)
// Hiện tại, nếu không có token, hoặc token không xác định được vai trò, thì không cho phép.
// Đây là một placeholder, bạn cần thay thế nó bằng logic JWT thật sự.
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: No authentication token provided.']);
    http_response_code(401); // Unauthorized
    exit;
}

// Vì chúng ta chưa triển khai JWT đầy đủ, bạn CÓ THỂ tạm thời bỏ qua kiểm tra này
// KHI CHỈ ĐANG PHÁT TRIỂN NỘI BỘ và CHỈ CẦN TEST.
// TUYỆT ĐỐI KHÔNG ĐỂ NHƯ VẬY KHI TRIỂN KHAI THẬT LÊN SERVER CÔNG KHAI!
// Để code chạy được với token giả (như từ Login.php), bạn sẽ cần một hàm giả định
// hoặc tạm thời bỏ qua kiểm tra này và CHỈ DỰA VÀO CHECK VAI TRÒ DƯỚI.
// Hoặc đơn giản là giả định người dùng này là admin nếu token tồn tại:
// (CHỈ CHO MỤC ĐÍCH TEST NỘI BỘ, KHÔNG DÙNG CHO PRODUCTION)
// if (!empty($token)) {
//     // Đây là GIẢ ĐỊNH. Trong thực tế, bạn phải giải mã token để lấy vai trò
//     // Nếu bạn muốn test mà không có JWT library, bạn có thể tự set $currentUserRole = ROLE_ADMIN;
//     // (RẤT NGUY HIỂM KHI ĐƯA LÊN PRODUCTION!)
//     $currentUserRole = ROLE_ADMIN; // Giả định người có token là ADMIN để test
// }


// KIỂM TRA VAI TRÒ CỦA NGƯỜI ĐANG GỬI REQUEST
// Chỉ admin được thay đổi vai trò người dùng (bao gồm manager và admin)
// Thay $currentUserRole bằng vai trò LẤY ĐƯỢC THỰC TẾ TỪ TOKEN
if ($currentUserRole !== ROLE_ADMIN) { 
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to update user roles. Only admins can perform this action.']);
    http_response_code(403); // Forbidden
    exit;
}

// ========================================================================
// === KẾT THÚC PHẦN KIỂM TRA QUYỀN TRUY CẬP ===
// ========================================================================


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

$userId = $data['id'] ?? null;
$newRole = $data['role'] ?? '';

// Kiểm tra dữ liệu đầu vào
if (empty($userId) || empty($newRole)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu ID người dùng hoặc vai trò mới.']);
    exit;
}

// Kiểm tra vai trò mới có hợp lệ không (ví dụ: chỉ cho phép 'user', 'admin', 'manager')
// Sử dụng các hằng số đã định nghĩa trong Database.php
$validRoles = [ROLE_USER, ROLE_ADMIN, ROLE_MANAGER]; 
if (!in_array($newRole, $validRoles)) {
    echo json_encode(['success' => false, 'message' => 'Vai trò không hợp lệ.']);
    exit;
}

$conn = pg_connect(DB_CONNECTION_STRING);

if (!$conn) {
    error_log("Lỗi kết nối PostgreSQL: " . pg_last_error());
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.']);
    exit;
}

// Cập nhật vai trò người dùng
$sql = "UPDATE users SET role = $1 WHERE id = $2";
$result = pg_query_params($conn, $sql, [$newRole, $userId]);

if ($result) {
    if (pg_affected_rows($result) > 0) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật vai trò thành công.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng để cập nhật hoặc vai trò không thay đổi.']);
    }
} else {
    error_log("Lỗi khi cập nhật vai trò: " . pg_last_error($conn));
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật vai trò người dùng.']);
}

pg_close($conn);
?>
