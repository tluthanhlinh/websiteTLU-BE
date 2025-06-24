<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\posts\DeletePost.php

// Đường dẫn từ DeletePost.php (trong api/posts/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ DeletePost.php (trong api/posts/) đến Database_test.php (trong config/database/)
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
// Chỉ admin và manager mới được phép xóa bài viết
// ========================================================================

// BƯỚC 1: Lấy JWT token từ Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

// BƯỚC 2: Hàm GIẢ ĐỊNH để giải mã token và lấy vai trò
// (Sử dụng hàm này để test cục bộ, thay thế bằng JWT library thật khi production)
function decodeJwtAndGetUserRole($token, $db_pdo_connection) {
    // Hàm này giống như trong UpdatePost.php và AddPost.php
    if (strpos($token, 'user_auth_token_for_') === 0) {
        $parts = explode('_', $token);
        $username = $parts[count($parts) - 2];
        try {
            $stmt = $db_pdo_connection->prepare("SELECT role FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['role'];
            }
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn vai trò người dùng từ token giả (DeletePost): " . $e->getMessage());
        }
    }
    return null; // Token không hợp lệ hoặc không có vai trò
}

function decodeJwtAndGetUserId($token, $db_pdo_connection) {
    // Hàm này giống như trong UpdatePost.php và AddPost.php
    if (strpos($token, 'user_auth_token_for_') === 0) {
        $parts = explode('_', $token);
        $username = $parts[count($parts) - 2];
        try {
            $stmt = $db_pdo_connection->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['id'];
            }
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn user_id từ token giả (DeletePost): " . $e->getMessage());
        }
    }
    return null;
}

$currentUserRole = decodeJwtAndGetUserRole($token, $db);
$currentUserId = decodeJwtAndGetUserId($token, $db); // Lấy ID của người dùng hiện tại


// BƯỚC 3: KIỂM TRA VAI TRÒ
// Chỉ admin và manager mới có quyền xóa bài viết
if (empty($token) || $currentUserId === null || ($currentUserRole !== ROLE_ADMIN && $currentUserRole !== ROLE_MANAGER)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to delete posts. Only admins and managers can perform this action.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========================================================================
// === KẾT THÚC PHẦN XÁC THỰC VÀ PHÂN QUYỀN ===
// ========================================================================


// Đảm bảo chỉ xử lý request DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức DELETE.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy dữ liệu JSON từ request body (chứa ID bài viết cần xóa)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Kiểm tra lỗi parsing JSON hoặc thiếu ID
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu ID bài viết.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$postId = filter_var($data['id'], FILTER_VALIDATE_INT);

// Kiểm tra ID có hợp lệ không
if ($postId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID bài viết không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Bắt đầu transaction
    $db->beginTransaction();

    // (Tùy chọn) Kiểm tra quyền sở hữu bài viết nếu bạn muốn chỉ tác giả hoặc admin/manager được xóa
    // $check_ownership_sql = "SELECT user_id FROM posts WHERE id = :id";
    // $stmt_ownership = $db->prepare($check_ownership_sql);
    // $stmt_ownership->bindParam(':id', $postId, PDO::PARAM_INT);
    // $stmt_ownership->execute();
    // $postOwner = $stmt_ownership->fetch(PDO::FETCH_ASSOC);

    // if (!$postOwner) {
    //     $db->rollBack();
    //     http_response_code(404); // Not Found
    //     echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài viết để xóa.'], JSON_UNESCAPED_UNICODE);
    //     exit();
    // }

    // // Nếu người dùng không phải admin/manager và không phải chủ sở hữu bài viết
    // if ($currentUserRole !== ROLE_ADMIN && $currentUserRole !== ROLE_MANAGER && $currentUserId !== $postOwner['user_id']) {
    //     $db->rollBack();
    //     http_response_code(403);
    //     echo json_encode(['success' => false, 'message' => 'Forbidden: You do not own this post or have sufficient permissions to delete it.'], JSON_UNESCAPED_UNICODE);
    //     exit();
    // }

    // Xóa bài viết
    $delete_sql = "DELETE FROM posts WHERE id = :id";
    $stmt_delete = $db->prepare($delete_sql);
    $stmt_delete->bindParam(':id', $postId, PDO::PARAM_INT);

    if ($stmt_delete->execute()) {
        $affectedRows = $stmt_delete->rowCount();
        if ($affectedRows > 0) {
            $db->commit(); // Xác nhận transaction
            http_response_code(200); // OK
            echo json_encode(['success' => true, 'message' => 'Bài viết đã được xóa thành công.', 'post_id' => $postId], JSON_UNESCAPED_UNICODE);
        } else {
            $db->rollBack(); // Không có hàng nào bị ảnh hưởng
            http_response_code(404); // Not Found (hoặc 200 nếu bạn coi không tìm thấy là thành công "không cần làm gì")
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài viết để xóa hoặc bài viết đã bị xóa trước đó.'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        $db->rollBack(); // Hoàn tác transaction
        http_response_code(500); // Internal Server Error
        error_log("Lỗi khi xóa bài viết: " . json_encode($stmt_delete->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa bài viết: ' . $stmt_delete->errorInfo()[2]], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack(); // Hoàn tác transaction
    http_response_code(500);
    error_log("Lỗi PDO khi xóa bài viết: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ nội bộ khi xóa bài viết.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>