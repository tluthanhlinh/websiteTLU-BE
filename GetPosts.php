<?php
//backend_railway/GetPosts.php (ví dụ)
require_once 'cors.php'; // Hoặc đường dẫn tương đối đúng nếu bạn đã sắp xếp thư mục
// Bất kỳ file nào khác cần thiết để include (ví dụ: Database.php)
require_once 'Database.php'; // Điều chỉnh đường dẫn nếu cần

// === CÁC HEADER CORS QUAN TRỌNG ===
header("Access-Control-Allow-Origin: *"); // Cho phép mọi domain truy cập.
                                         // TRONG PRODUCTION, NÊN THAY '*' BẰNG DOMAIN THẬT CỦA FRONTEND CỦA BẠN:
                                         // Ví dụ: header("Access-Control-Allow-Origin: http://localhost:3000"); cho dev
                                         //         header("Access-Control-Allow-Origin: https://your-frontend-app.vercel.app"); cho production

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Các phương thức HTTP được phép
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Các header mà frontend có thể gửi
header("Content-Type: application/json; charset=UTF-8"); // Đảm bảo response là JSON

// Xử lý Preflight OPTIONS request. Trình duyệt gửi request này trước khi gửi request chính
// để hỏi server xem có được phép gửi request đó không.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Trả về 200 OK để báo hiệu rằng được phép
    exit(); // Ngừng thực thi script sau khi gửi headers OPTIONS
}

// === Cốt lõi logic API của bạn (kết nối DB, truy vấn, trả về JSON) ===
try {
    $pdo = Database::connect(); // Lấy kết nối PDO
    // ... (code truy vấn database và trả về JSON) ...
    $stmt = $pdo->prepare("SELECT id, title, slug, content FROM articles ORDER BY id DESC");
    $stmt->execute();
    $articles = $stmt->fetchAll();
    echo json_encode($articles);

} catch (PDOException $e) {
    http_response_code(500); // Lỗi server
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    error_log("API Error in GetPosts.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
    error_log("API Error in GetPosts.php: " . $e->getMessage());
}
?>