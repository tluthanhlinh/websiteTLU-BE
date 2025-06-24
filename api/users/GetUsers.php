<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\users\GetUsers.php

// Đường dẫn từ GetUsers.php (trong api/users/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ GetUsers.php (trong api/users/) đến Database_test.php (trong config/Database/)
// >>> DÙNG CHO MÔI TRƯỜNG CỤC BỘ (XAMPP/MySQL) <<<
include_once __DIR__ . '/../../config/Database/Database.php'; // Đổi từ Database_test.php sang Database.php
include_once __DIR__ . '/../../models/Post.php';
include_once __DIR__ . '/../../models/Category.php';
$database = new Database();
$database->connect(); // Lấy đối tượng PDO đã kết nối

// >>> KHI DEPLOY LÊN RENDER (PostgreSQL) HÃY ĐỔI SANG DÒNG DƯỚI VÀ COMMENT DÒNG TRÊN <<<
// require_once '../../config/Database/Database.php';
// $database = new Database();
// $db = $database->connect();

// GetUsers.php thường sẽ yêu cầu xác thực và phân quyền
// Để đơn giản cho mục đích hiện tại, chúng ta sẽ bỏ qua bước này
// nhưng TRONG MỘT ỨNG DỤNG THỰC TẾ, bạn cần thêm logic xác thực token/session và kiểm tra quyền ở đây.
// Ví dụ:
/*
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized: Missing Authorization header.']);
    exit();
}
// Giả định có hàm validateToken và getUserRole
$token = explode(' ', $_SERVER['HTTP_AUTHORIZATION'])[1];
if (!validateToken($token)) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized: Invalid or expired token.']);
    exit();
}
if (getUserRole($token) !== 'admin') { // Chỉ admin mới được lấy danh sách người dùng
    http_response_code(403);
    echo json_encode(['message' => 'Forbidden: You do not have permission to access this resource.']);
    exit();
}
*/

// Đảm bảo chỉ chấp nhận phương thức GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức GET.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Logic để lấy danh sách người dùng
try {
    // Truy vấn SQL để lấy thông tin người dùng
    // Tránh lấy password_hash ra ngoài
    // Lấy tất cả các cột trừ password_hash
    $sql = "SELECT id, username, email, full_name, role, created_at, updated_at FROM users ORDER BY created_at DESC";
    $stmt = $db->query($sql); // Sử dụng query vì không có tham số đầu vào

    $users_arr = array();
    $users_arr['data'] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Không cần extract(), truy cập trực tiếp bằng $row['column_name']
        $user_item = array(
            'id' => $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'full_name' => $row['full_name'],
            'role' => $row['role'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        );
        array_push($users_arr['data'], $user_item);
    }

    // Trả về danh sách người dùng dưới dạng JSON
    http_response_code(200); // OK
    echo json_encode($users_arr, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Ghi log lỗi để debug
    error_log("Lỗi PDO khi lấy danh sách người dùng: " . $e->getMessage());
    // Trả về lỗi cho client
    http_response_code(500); // Internal Server Error
    echo json_encode(array('success' => false, 'message' => 'Đã xảy ra lỗi khi tải danh sách người dùng.', 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

// Không cần đóng kết nối PDO vì nó sẽ tự động đóng khi script kết thúc.
?>