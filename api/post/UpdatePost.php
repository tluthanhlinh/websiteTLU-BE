<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\posts\UpdatePost.php

// Đường dẫn từ UpdatePost.php (trong api/posts/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ UpdatePost.php (trong api/posts/) đến Database_test.php (trong config/database/)
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
// Chỉ admin và manager mới được phép cập nhật bài viết
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
            error_log("Lỗi truy vấn vai trò người dùng từ token giả (UpdatePost): " . $e->getMessage());
        }
    }
    return null; // Token không hợp lệ hoặc không có vai trò
}

function decodeJwtAndGetUserId($token, $db_pdo_connection) {
    // Hàm GIẢ ĐỊNH để lấy user_id từ token.
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
            error_log("Lỗi truy vấn user_id từ token giả (UpdatePost): " . $e->getMessage());
        }
    }
    return null;
}

$currentUserRole = decodeJwtAndGetUserRole($token, $db);
$currentUserId = decodeJwtAndGetUserId($token, $db); // Lấy ID của người dùng hiện tại


// BƯỚC 3: KIỂM TRA VAI TRÒ VÀ ID NGƯỜI DÙNG
// Chỉ admin và manager mới có quyền cập nhật bài viết
// Hoặc, nếu người dùng là tác giả của bài viết, họ cũng có thể cập nhật.
// Để đơn giản, hiện tại chúng ta chỉ cho phép admin/manager. Bạn có thể mở rộng sau.
if (empty($token) || $currentUserId === null || ($currentUserRole !== ROLE_ADMIN && $currentUserRole !== ROLE_MANAGER)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to update posts. Only admins and managers can perform this action.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========================================================================
// === KẾT THÚC PHẦN XÁC THỰC VÀ PHÂN QUYỀN ===
// ========================================================================


// Đảm bảo chỉ xử lý request PUT (hoặc POST nếu frontend không hỗ trợ PUT)
// RESTful API thường dùng PUT cho update. Nếu frontend chỉ dùng POST, bạn có thể đổi.
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức PUT.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy dữ liệu JSON từ request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Kiểm tra lỗi parsing JSON hoặc thiếu dữ liệu cần thiết (id của bài viết)
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu ID bài viết.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$postId = filter_var($data['id'], FILTER_VALIDATE_INT);
if ($postId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID bài viết không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy các trường dữ liệu để cập nhật. Sử dụng toán tử null coalescing để gán null nếu không tồn tại.
$title = isset($data['title']) ? trim($data['title']) : null;
$body = $data['body'] ?? null;
$category = isset($data['category']) ? trim($data['category']) : null;
$status = isset($data['status']) ? trim($data['status']) : null;

// Hàm tạo slug từ tiêu đề (cần được tái sử dụng từ AddPost.php hoặc đưa vào một utility file)
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/\s+/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return $string;
}

$slug = null;
if ($title !== null) { // Chỉ tạo slug mới nếu tiêu đề được cung cấp để cập nhật
    $slug = createSlug($title);
}

// Kiểm tra trạng thái hợp lệ nếu được cung cấp
$validStatuses = ['draft', 'published', 'archived'];
if ($status !== null && !in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Trạng thái bài viết không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Bắt đầu transaction
    $db->beginTransaction();

    // 1. Lấy thông tin bài viết hiện tại để kiểm tra tác giả (nếu cần) hoặc slug
    $fetch_post_sql = "SELECT user_id, slug FROM posts WHERE id = :id";
    $stmt_fetch_post = $db->prepare($fetch_post_sql);
    $stmt_fetch_post->bindParam(':id', $postId, PDO::PARAM_INT);
    $stmt_fetch_post->execute();
    $existingPost = $stmt_fetch_post->fetch(PDO::FETCH_ASSOC);

    if (!$existingPost) {
        $db->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài viết để cập nhật.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // (Tùy chọn) Nếu bạn muốn chỉ tác giả hoặc admin/manager mới được sửa bài viết của họ:
    // if ($currentUserRole !== ROLE_ADMIN && $currentUserRole !== ROLE_MANAGER && $currentUserId !== $existingPost['user_id']) {
    //     $db->rollBack();
    //     http_response_code(403);
    //     echo json_encode(['success' => false, 'message' => 'Forbidden: You do not own this post or have sufficient permissions to update it.'], JSON_UNESCAPED_UNICODE);
    //     exit();
    // }

    // Xây dựng câu truy vấn UPDATE động
    $update_fields = [];
    $update_params = [':id' => $postId];

    if ($title !== null) {
        // Nếu tiêu đề được cập nhật, cần xử lý slug mới và kiểm tra trùng lặp
        // Chỉ tạo slug mới nếu nó khác với slug hiện tại
        $newSlug = createSlug($title);
        if ($newSlug !== $existingPost['slug']) {
            $originalSlug = $newSlug;
            $counter = 1;
            $tempSlug = $newSlug; // Biến tạm để kiểm tra trùng lặp
            do {
                $check_slug_sql = "SELECT id FROM posts WHERE slug = :check_slug AND id != :current_id";
                $stmt_check_slug = $db->prepare($check_slug_sql);
                $stmt_check_slug->bindParam(':check_slug', $tempSlug, PDO::PARAM_STR);
                $stmt_check_slug->bindParam(':current_id', $postId, PDO::PARAM_INT);
                $stmt_check_slug->execute();

                if ($stmt_check_slug->rowCount() > 0) {
                    $tempSlug = $originalSlug . '-' . $counter++;
                } else {
                    $slug = $tempSlug; // Slug mới đã được xác định
                    break;
                }
            } while (true);
        } else {
            $slug = $existingPost['slug']; // Slug không đổi
        }
        $update_fields[] = 'title = :title';
        $update_params[':title'] = $title;
        $update_fields[] = 'slug = :slug';
        $update_params[':slug'] = $slug;
    }
    if ($body !== null) {
        $update_fields[] = 'body = :body';
        $update_params[':body'] = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
    }
    if ($category !== null) {
        $update_fields[] = 'category = :category';
        $update_params[':category'] = $category;
    }
    if ($status !== null) {
        $update_fields[] = 'status = :status';
        $update_params[':status'] = $status;
    }
    
    // Nếu không có trường nào để cập nhật
    if (empty($update_fields)) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Không có dữ liệu để cập nhật.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $update_sql = "UPDATE posts SET " . implode(', ', $update_fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt_update = $db->prepare($update_sql);

    // Bind các tham số
    foreach ($update_params as $key => $value) {
        $stmt_update->bindValue($key, $value);
    }

    if ($stmt_update->execute()) {
        $affectedRows = $stmt_update->rowCount();
        if ($affectedRows > 0) {
            $db->commit(); // Xác nhận transaction
            http_response_code(200); // OK
            echo json_encode(['success' => true, 'message' => 'Bài viết đã được cập nhật thành công.', 'post_id' => $postId, 'new_slug' => $slug], JSON_UNESCAPED_UNICODE);
        } else {
            $db->rollBack(); // Không có hàng nào bị ảnh hưởng
            http_response_code(200); // Vẫn có thể là OK nếu dữ liệu không đổi
            echo json_encode(['success' => false, 'message' => 'Không có thay đổi nào được thực hiện đối với bài viết hoặc ID không tồn tại.'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        $db->rollBack(); // Hoàn tác transaction
        http_response_code(500); // Internal Server Error
        error_log("Lỗi khi cập nhật bài viết: " . json_encode($stmt_update->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật bài viết: ' . $stmt_update->errorInfo()[2]], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack(); // Hoàn tác transaction
    http_response_code(500);
    error_log("Lỗi PDO khi cập nhật bài viết: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ nội bộ khi cập nhật bài viết.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>