<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\comments\UpdateComment.php

// Đường dẫn từ UpdateComment.php (trong api/comments/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ UpdateComment.php (trong api/comments/) đến Database_test.php (trong config/Database/)
// >>> DÙNG CHO MÔI TRƯỜM CỤC BỘ (XAMPP/MySQL) <<<
include_once __DIR__ . '/../../config/Database/Database.php'; // Đổi từ Database_test.php sang Database.php
include_once __DIR__ . '/../../models/Post.php';
include_once __DIR__ . '/../../models/Category.php';
$database = new Database();
$database->connect(); // Lấy đối tượng PDO đã kết nối

// >>> KHI DEPLOY LÊN RENDER (PostgreSQL) HÃY ĐỔI SANG DÒNG DƯỚI VÀ COMMENT DÒNG TRÊN <<<
// require_once '../../config/Database/Database.php';
// $database = new Database();
// $db = $database->connect();

// Định nghĩa các hằng số vai trò (nên được đặt ở một file config chung)
if (!defined('ROLE_USER')) define('ROLE_USER', 'user');
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin');
if (!defined('ROLE_MANAGER')) define('ROLE_MANAGER', 'manager');

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========================================================================
// === XÁC THỰC VÀ PHÂN QUYỀN (AUTHORIZATION) ===
// Chỉ chủ sở hữu bình luận, admin, hoặc manager mới được phép cập nhật.
// ========================================================================

// BƯỚC 1: Lấy JWT token từ Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

// BƯỚC 2: Hàm GIẢ ĐỊNH để giải mã token và lấy user_id và role
// (Sử dụng hàm này để test cục bộ, thay thế bằng JWT library thật khi production)
function decodeJwtAndGetUserAuthInfo($token, $db_pdo_connection) {
    if (strpos($token, 'user_auth_token_for_') === 0) {
        $parts = explode('_', $token);
        $username = $parts[count($parts) - 2]; // Giả định username ở vị trí thứ 2 từ cuối
        try {
            $stmt = $db_pdo_connection->prepare("SELECT id, role FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return ['user_id' => $result['id'], 'role' => $result['role']];
            }
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn thông tin người dùng từ token giả (UpdateComment): " . $e->getMessage());
        }
    }
    return null; // Token không hợp lệ hoặc không có thông tin
}

$currentUserAuth = decodeJwtAndGetUserAuthInfo($token, $db);
$currentUserId = $currentUserAuth['user_id'] ?? null;
$currentUserRole = $currentUserAuth['role'] ?? null;


// BƯỚC 3: KIỂM TRA NGƯỜI DÙNG ĐÃ ĐĂNG NHẬP CHƯA
if (empty($token) || $currentUserId === null) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in to update a comment.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========================================================================
// === KẾT THÚC PHẦN XÁC THỰC VÀ PHÂN QUYỀN ===
// ========================================================================


// Đảm bảo chỉ xử lý request PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức PUT.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy dữ liệu JSON từ request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Kiểm tra lỗi parsing JSON hoặc thiếu ID bình luận
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu ID bình luận.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$commentId = filter_var($data['id'], FILTER_VALIDATE_INT);
$newContent = isset($data['content']) ? trim($data['content']) : null;

// Kiểm tra ID và nội dung có hợp lệ không
if ($commentId === false || ($newContent === null && !isset($data['content']))) { // content không được null nếu có mặt
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID bình luận không hợp lệ hoặc thiếu nội dung cập nhật.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Nội dung không được rỗng sau khi trim
if ($newContent !== null && empty($newContent)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nội dung bình luận không được để trống.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Bắt đầu transaction
    $db->beginTransaction();

    // 1. Lấy thông tin bình luận hiện tại và kiểm tra quyền
    $fetch_comment_sql = "SELECT user_id FROM comments WHERE id = :comment_id";
    $stmt_fetch_comment = $db->prepare($fetch_comment_sql);
    $stmt_fetch_comment->bindParam(':comment_id', $commentId, PDO::PARAM_INT);
    $stmt_fetch_comment->execute();
    $existingComment = $stmt_fetch_comment->fetch(PDO::FETCH_ASSOC);

    if (!$existingComment) {
        $db->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bình luận để cập nhật.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Kiểm tra phân quyền: Chỉ chủ sở hữu, admin, hoặc manager mới được sửa
    if ($currentUserRole !== ROLE_ADMIN && $currentUserRole !== ROLE_MANAGER && $currentUserId !== (int)$existingComment['user_id']) {
        $db->rollBack();
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to update this comment.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // 2. Cập nhật bình luận vào database
    $update_sql = "UPDATE comments SET content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt_update = $db->prepare($update_sql);

    // Sử dụng htmlspecialchars để lưu trữ nội dung an toàn
    $cleaned_content = htmlspecialchars($newContent, ENT_QUOTES, 'UTF-8'); 

    $stmt_update->bindParam(':content', $cleaned_content, PDO::PARAM_STR);
    $stmt_update->bindParam(':id', $commentId, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        $affectedRows = $stmt_update->rowCount();
        if ($affectedRows > 0) {
            $db->commit(); // Xác nhận transaction
            http_response_code(200); // OK
            echo json_encode(['success' => true, 'message' => 'Bình luận đã được cập nhật thành công.', 'comment_id' => $commentId], JSON_UNESCAPED_UNICODE);
        } else {
            $db->rollBack(); // Không có hàng nào bị ảnh hưởng (có thể nội dung không đổi)
            http_response_code(200); // Vẫn OK, nhưng thông báo không có thay đổi
            echo json_encode(['success' => false, 'message' => 'Không có thay đổi nào được thực hiện đối với bình luận hoặc ID không tồn tại.'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        $db->rollBack(); // Hoàn tác transaction
        http_response_code(500); // Internal Server Error
        error_log("Lỗi khi cập nhật bình luận: " . json_encode($stmt_update->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật bình luận: ' . $stmt_update->errorInfo()[2]], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack(); // Hoàn tác transaction
    http_response_code(500);
    error_log("Lỗi PDO khi cập nhật bình luận: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ nội bộ khi cập nhật bình luận.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>