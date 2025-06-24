<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\posts\AddPost.php

// Đường dẫn từ AddPost.php (trong api/posts/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ AddPost.php (trong api/posts/) đến Database_test.php (trong config/database/)
// >>> DÙNG CHO MÔI TRƯỜM CỤC BỘ (XAMPP/MySQL) <<<
include_once __DIR__ . '/../../config/Database/Database.php'; // Đổi từ Database_test.php sang Database.php
include_once __DIR__ . '/../../models/Post.php';
include_once __DIR__ . '/../../models/Category.php';
$database = new Database();
//$db = $database->getConnection(); // Lấy đối tượng PDO đã kết nối
$db = $database->connect();
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
// Chỉ admin và manager mới được phép thêm bài viết
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
            error_log("Lỗi truy vấn vai trò người dùng từ token giả (AddPost): " . $e->getMessage());
        }
    }
    return null; // Token không hợp lệ hoặc không có vai trò
}

function decodeJwtAndGetUserId($token, $db_pdo_connection) {
    // Hàm GIẢ ĐỊNH để lấy user_id từ token.
    // Trong thực tế: giải mã JWT và lấy user_id từ payload.
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
            error_log("Lỗi truy vấn user_id từ token giả (AddPost): " . $e->getMessage());
        }
    }
    return null;
}


$currentUserRole = decodeJwtAndGetUserRole($token, $db);
$currentUserId = decodeJwtAndGetUserId($token, $db); // Lấy ID của người dùng hiện tại


// BƯỚC 3: KIỂM TRA VAI TRÒ VÀ ID NGƯỜI DÙNG
// Chỉ admin và manager mới có quyền thêm bài viết
if (empty($token) || $currentUserId === null || ($currentUserRole !== ROLE_ADMIN && $currentUserRole !== ROLE_MANAGER)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to add posts. Only admins and managers can perform this action.'], JSON_UNESCAPED_UNICODE);
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
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['title']) || !isset($data['body']) || !isset($data['category'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu title, body hoặc category.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lọc và làm sạch dữ liệu đầu vào
$title = trim($data['title']);
$body = $data['body']; // Nội dung bài viết có thể chứa HTML, nên không trim
$category = trim($data['category']);
$status = $data['status'] ?? 'draft'; // Mặc định là 'draft' nếu không gửi lên
// user_id sẽ được lấy từ token (đã có $currentUserId)


// Hàm tạo slug từ tiêu đề
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string); // Loại bỏ ký tự không phải chữ cái, số, khoảng trắng, gạch ngang
    $string = preg_replace('/\s+/', '-', $string);      // Thay thế khoảng trắng bằng dấu gạch ngang
    $string = preg_replace('/-+/', '-', $string);       // Thay thế nhiều dấu gạch ngang bằng một dấu
    return $string;
}

$slug = createSlug($title); // Tạo slug từ tiêu đề


// Kiểm tra dữ liệu đầu vào rỗng
if (empty($title) || empty($body) || empty($category)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tiêu đề, nội dung và danh mục không được để trống.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Kiểm tra trạng thái hợp lệ
$validStatuses = ['draft', 'published', 'archived'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Trạng thái bài viết không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}


try {
    // Bắt đầu transaction
    $db->beginTransaction();

    // 1. Kiểm tra xem slug đã tồn tại chưa để tránh trùng lặp URL
    $check_slug_sql = "SELECT id FROM posts WHERE slug = :slug";
    $stmt_check_slug = $db->prepare($check_slug_sql);
    $stmt_check_slug->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt_check_slug->execute();

    if ($stmt_check_slug->rowCount() > 0) {
        // Nếu slug đã tồn tại, thêm số vào cuối slug
        $originalSlug = $slug;
        $counter = 1;
        do {
            $slug = $originalSlug . '-' . $counter++;
            $stmt_check_slug->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt_check_slug->execute();
        } while ($stmt_check_slug->rowCount() > 0);
    }
    
    // 2. Chèn bài viết mới vào database
    // Đảm bảo các cột trong bảng `posts` khớp với các tham số này
    $insert_sql = "INSERT INTO posts (title, body, slug, category, user_id, status) 
                   VALUES (:title, :body, :slug, :category, :user_id, :status)";
    $stmt_insert = $db->prepare($insert_sql);

    // Sử dụng htmlspecialchars để lưu trữ nội dung có thể có HTML an toàn hơn
    // Hoặc sử dụng một thư viện HTML Purifier nếu nội dung cho phép HTML đầy đủ từ người dùng
    $cleaned_body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); 

    $stmt_insert->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt_insert->bindParam(':body', $cleaned_body, PDO::PARAM_STR);
    $stmt_insert->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt_insert->bindParam(':category', $category, PDO::PARAM_STR);
    $stmt_insert->bindParam(':user_id', $currentUserId, PDO::PARAM_INT); // Lấy user_id từ token
    $stmt_insert->bindParam(':status', $status, PDO::PARAM_STR);

    if ($stmt_insert->execute()) {
        $db->commit(); // Xác nhận transaction
        http_response_code(201); // Created
        echo json_encode(['success' => true, 'message' => 'Bài viết đã được thêm thành công.', 'post_id' => $db->lastInsertId(), 'slug' => $slug], JSON_UNESCAPED_UNICODE);
    } else {
        $db->rollBack(); // Hoàn tác transaction
        http_response_code(500); // Internal Server Error
        error_log("Lỗi khi thêm bài viết: " . json_encode($stmt_insert->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm bài viết: ' . $stmt_insert->errorInfo()[2]], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack(); // Hoàn tác transaction
    http_response_code(500);
    error_log("Lỗi PDO khi thêm bài viết: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ nội bộ khi thêm bài viết.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>