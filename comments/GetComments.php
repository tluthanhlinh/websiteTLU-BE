<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\comments\GetComments.php

// Đường dẫn từ GetComments.php (trong api/comments/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ GetComments.php (trong api/comments/) đến Database_test.php (trong config/database/)
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

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Đảm bảo chỉ chấp nhận phương thức GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức GET.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy post_id từ tham số URL (query parameter)
$postId = $_GET['post_id'] ?? null;

// Kiểm tra xem post_id có tồn tại và hợp lệ không
if (empty($postId) || !filter_var($postId, FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Thiếu hoặc ID bài viết không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 1. Kiểm tra xem bài viết có tồn tại không
    $check_post_sql = "SELECT id FROM posts WHERE id = :post_id";
    $stmt_check_post = $db->prepare($check_post_sql);
    $stmt_check_post->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt_check_post->execute();

    if ($stmt_check_post->rowCount() === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Truy vấn SQL để lấy tất cả bình luận cho bài viết đó
    // Sử dụng JOIN để lấy full_name của người bình luận
    // Sắp xếp theo created_at để bình luận mới nhất hiển thị trước (hoặc cũ nhất tùy bạn)
    // Cột 'avatar_url' là tùy chọn, nếu bạn có trong bảng users
    $sql = "SELECT 
                c.id, 
                c.content, 
                c.parent_id, 
                c.created_at,
                u.id AS user_id,
                u.full_name AS author_name,
                u.username AS author_username,
                u.avatar_url AS author_avatar_url -- Nếu có cột avatar_url trong bảng users
            FROM 
                comments c
            JOIN 
                users u ON c.user_id = u.id 
            WHERE 
                c.post_id = :post_id
            ORDER BY 
                c.created_at ASC"; // ASC để bình luận gốc hiển thị trước, phản hồi theo sau

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt->execute();
    
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Xây dựng cấu trúc bình luận phân cấp (parent-child)
    $commentTree = [];
    $indexedComments = [];

    // Tạo một mảng các bình luận được index theo ID
    foreach ($comments as $comment) {
        $comment['content'] = html_entity_decode($comment['content']); // Giải mã HTML entities
        $comment['replies'] = []; // Thêm một mảng rỗng cho các phản hồi
        $indexedComments[$comment['id']] = $comment;
    }

    // Xây dựng cây bình luận
    foreach ($indexedComments as $id => $comment) {
        if ($comment['parent_id'] !== null && isset($indexedComments[$comment['parent_id']])) {
            // Nếu có parent_id và parent tồn tại, thêm vào replies của parent
            $indexedComments[$comment['parent_id']]['replies'][] = &$indexedComments[$id];
        } else {
            // Nếu không có parent_id (hoặc parent không tồn tại), đó là bình luận gốc
            $commentTree[] = &$indexedComments[$id];
        }
    }

    // Trả về danh sách bình luận dưới dạng JSON
    http_response_code(200); // OK
    echo json_encode(['success' => true, 'data' => $commentTree], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Ghi log lỗi để debug
    error_log("Lỗi PDO khi lấy danh sách bình luận: " . $e->getMessage());
    // Trả về lỗi cho client
    http_response_code(500); // Internal Server Error
    echo json_encode(array('success' => false, 'message' => 'Lỗi máy chủ nội bộ khi lấy bình luận.', 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

// Không cần đóng kết nối PDO vì nó sẽ tự động đóng khi script kết thúc.
?>