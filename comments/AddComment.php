<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\comments\AddComment.php

// Đường dẫn từ AddComment.php (trong api/comments/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php'; 

// Đường dẫn từ AddComment.php (trong api/comments/) đến Database_test.php (trong config/database/)
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
// Chỉ người dùng đã đăng nhập (có token hợp lệ) mới được phép thêm bình luận.
// Chúng ta không cần kiểm tra vai trò cụ thể ở đây nếu mọi người dùng đều có thể bình luận.
// ========================================================================

// BƯỚC 1: Lấy JWT token từ Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

// BƯỚC 2: Hàm GIẢ ĐỊNH để giải mã token và lấy user_id
// (Sử dụng hàm này để test cục bộ, thay thế bằng JWT library thật khi production)
function decodeJwtAndGetUserId($token, $db_pdo_connection) {
    // Hàm này giống như trong các file trước.
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
            error_log("Lỗi truy vấn user_id từ token giả (AddComment): " . $e->getMessage());
        }
    }
    return null;
}

$currentUserId = decodeJwtAndGetUserId($token, $db); // Lấy ID của người dùng hiện tại


// BƯỚC 3: KIỂM TRA NGƯỜI DÙNG ĐÃ ĐĂNG NHẬP CHƯA
// Nếu không có token hoặc không xác định được user_id, từ chối.
if (empty($token) || $currentUserId === null) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in to add a comment.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========================================================================
// === KẾT THÚC PHẦN XÁC THỰC VÀ PHÂN QUYỀN ===
// ========================================================================


// Đảm bảo chỉ xử lý request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy dữ liệu JSON từ request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Kiểm tra lỗi parsing JSON hoặc thiếu dữ liệu cần thiết
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['post_id']) || !isset($data['content'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu post_id hoặc content.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lọc và làm sạch dữ liệu đầu vào
$postId = filter_var($data['post_id'], FILTER_VALIDATE_INT);
$content = trim($data['content']);
$parentId = filter_var($data['parent_id'] ?? null, FILTER_VALIDATE_INT); // parent_id là tùy chọn cho bình luận phản hồi

// Kiểm tra dữ liệu đầu vào rỗng hoặc không hợp lệ
if ($postId === false || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID bài viết hoặc nội dung bình luận không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Nếu parentId được cung cấp nhưng không hợp lệ
if ($parentId !== false && $parentId !== null && $parentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID bình luận gốc không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}


try {
    // Bắt đầu transaction
    $db->beginTransaction();

    // 1. Kiểm tra xem bài viết có tồn tại không
    $check_post_sql = "SELECT id FROM posts WHERE id = :post_id";
    $stmt_check_post = $db->prepare($check_post_sql);
    $stmt_check_post->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt_check_post->execute();

    if ($stmt_check_post->rowCount() === 0) {
        $db->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Nếu có parent_id, kiểm tra xem bình luận gốc có tồn tại không
    if ($parentId !== null) {
        $check_parent_sql = "SELECT id FROM comments WHERE id = :parent_id AND post_id = :post_id";
        $stmt_check_parent = $db->prepare($check_parent_sql);
        $stmt_check_parent->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
        $stmt_check_parent->bindParam(':post_id', $postId, PDO::PARAM_INT);
        $stmt_check_parent->execute();

        if ($stmt_check_parent->rowCount() === 0) {
            $db->rollBack();
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Bình luận gốc không tồn tại hoặc không thuộc bài viết này.'], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    
    // 3. Chèn bình luận mới vào database
    $insert_sql = "INSERT INTO comments (post_id, user_id, parent_id, content) 
                   VALUES (:post_id, :user_id, :parent_id, :content)";
    $stmt_insert = $db->prepare($insert_sql);

    // Sử dụng htmlspecialchars để lưu trữ nội dung an toàn
    $cleaned_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); 

    $stmt_insert->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt_insert->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $stmt_insert->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
    $stmt_insert->bindParam(':content', $cleaned_content, PDO::PARAM_STR);

    if ($stmt_insert->execute()) {
        $db->commit(); // Xác nhận transaction
        http_response_code(201); // Created
        echo json_encode(['success' => true, 'message' => 'Bình luận đã được thêm thành công.', 'comment_id' => $db->lastInsertId()], JSON_UNESCAPED_UNICODE);
    } else {
        $db->rollBack(); // Hoàn tác transaction
        http_response_code(500); // Internal Server Error
        error_log("Lỗi khi thêm bình luận: " . json_encode($stmt_insert->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm bình luận: ' . $stmt_insert->errorInfo()[2]], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack(); // Hoàn tác transaction
    http_response_code(500);
    error_log("Lỗi PDO khi thêm bình luận: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ nội bộ khi thêm bình luận.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>