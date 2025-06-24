<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\users\UpdateUserRole.php

// Đường dẫn từ UpdateUserRole.php (trong api/users/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php';

// Đường dẫn từ UpdateUserRole.php (trong api/users/) đến Database_test.php (trong config/Database/)
// >>> DÙNG CHO MÔI TRƯỜNG CỤC BỘ (XAMPP/MySQL) <<<
require_once '../../config/Database/Database_test.php';
include_once __DIR__ . '/../../config/Database/Database.php'; // Đổi từ Database_test.php sang Database.php
include_once __DIR__ . '/../../models/Post.php';
include_once __DIR__ . '/../../models/Category.php';
$database = new Database();
$database->connect(); // Lấy đối tượng PDO đã kết nối

// >>> KHI DEPLOY LÊN RENDER (PostgreSQL) HÃY ĐỔI SANG DÒNG DƯỚI VÀ COMMENT DÒNG TRÊN <<<
// require_once '../../config/Database/Database.php';
// $database = new Database();
// $db = $database->connect();

// Định nghĩa các hằng số vai trò (nên được đặt ở một file config chung nếu có nhiều nơi sử dụng)
// Bạn có thể đặt chúng ở đầu file Database_test.php hoặc một file constants.php riêng
if (!defined('ROLE_USER')) define('ROLE_USER', 'user');
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin');
if (!defined('ROLE_MANAGER')) define('ROLE_MANAGER', 'manager');

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========================================================================
// === PHẦN QUAN TRỌNG NHẤT: XÁC THỰC VÀ PHÂN QUYỀN (SERVER-SIDE) ===
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

// --- LOGIC XÁC THỰC VÀ LẤY VAI TRÒ THỰC TẾ ---
// (Bạn sẽ cần một thư viện JWT và logic giải mã ở đây)
// Giả định: hàm `decodeJwtAndGetUserRole` của bạn sẽ giải mã token và trả về vai trò của người dùng.
// Nếu bạn chưa có JWT library, bạn có thể tạo một hàm giả định ĐỂ TEST NỘI BỘ.
// TUYỆT ĐỐI KHÔNG ĐỂ HÀM GIẢ ĐỊNH NÀY KHI TRIỂN KHAI THẬT LÊN SERVER CÔNG KHAI!

function decodeJwtAndGetUserRole($token, $db_pdo_connection) {
    // Đây là HÀM GIẢ ĐỊNH cho mục đích test cục bộ.
    // Trong thực tế:
    // 1. Giải mã JWT token.
    // 2. Kiểm tra chữ ký (signature) của token.
    // 3. Kiểm tra thời hạn (expiration) của token.
    // 4. Lấy thông tin payload (bao gồm user_id và role).
    // 5. Kiểm tra user_id/role có hợp lệ trong database không (tùy thuộc vào thiết kế).
    // Nếu token hợp lệ, trả về vai trò. Ngược lại, trả về null.

    // Để test nhanh, chúng ta sẽ làm một kiểm tra đơn giản với token giả từ Login.php:
    if (strpos($token, 'user_auth_token_for_') === 0) {
        // Tách username từ token giả
        $parts = explode('_', $token);
        $username = $parts[count($parts) - 2]; // Lấy phần username

        // Truy vấn database để lấy vai trò thật của username này
        try {
            $stmt = $db_pdo_connection->prepare("SELECT role FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['role'];
            }
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn vai trò người dùng từ token giả: " . $e->getMessage());
        }
    }
    return null; // Token không hợp lệ hoặc không có vai trò
}


$currentUserRole = decodeJwtAndGetUserRole($token, $db);


// BƯỚC 3: KIỂM TRA VAI TRÒ CỦA NGƯỜI ĐANG GỬI REQUEST
// Chỉ admin được thay đổi vai trò người dùng (bao gồm manager và admin)
if (empty($token) || $currentUserRole !== ROLE_ADMIN) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to update user roles. Only admins can perform this action.'], JSON_UNESCAPED_UNICODE);
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
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id']) || !isset($data['role'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu ID người dùng hoặc vai trò mới.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = filter_var($data['id'], FILTER_VALIDATE_INT); // Lọc và validate ID
$newRole = trim($data['role']); // Loại bỏ khoảng trắng và validate vai trò

// Kiểm tra dữ liệu đầu vào đã được lọc
if ($userId === false || empty($newRole)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID người dùng hoặc vai trò mới không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Kiểm tra vai trò mới có hợp lệ không (chỉ cho phép 'user', 'admin', 'manager')
$validRoles = [ROLE_USER, ROLE_ADMIN, ROLE_MANAGER];
if (!in_array($newRole, $validRoles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vai trò không hợp lệ. Vai trò cho phép là: user, admin, manager.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Không cho phép admin hạ cấp vai trò của chính mình
// (Cần thêm logic để lấy ID của người dùng hiện tại từ token nếu bạn đã triển khai JWT đầy đủ)
// Ví dụ: if ($userId == $currentLoggedInUserId && $newRole !== ROLE_ADMIN) { ... }


try {
    // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
    $db->beginTransaction();

    // 1. Kiểm tra xem người dùng có tồn tại không
    $check_user_sql = "SELECT id, role FROM users WHERE id = :id";
    $stmt_check = $db->prepare($check_user_sql);
    $stmt_check->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt_check->execute();
    $targetUser = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        $db->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng để cập nhật.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Không cho phép admin hạ cấp vai trò của một admin khác hoặc của chính mình
    // (Nếu bạn muốn admin không thể thay đổi vai trò của admin khác)
    if ($targetUser['role'] === ROLE_ADMIN && $newRole !== ROLE_ADMIN) {
        // Nếu người dùng mục tiêu là admin và vai trò mới không phải admin
        // Và người thực hiện hành động cũng là admin (được xác định bởi $currentUserRole)
        // Đây là một quy tắc kinh doanh, bạn có thể thay đổi
        $db->rollBack();
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Không thể hạ cấp vai trò của một quản trị viên khác.'], JSON_UNESCAPED_UNICODE);
        exit();
    }


    // 3. Cập nhật vai trò người dùng
    $update_sql = "UPDATE users SET role = :new_role WHERE id = :id";
    $stmt_update = $db->prepare($update_sql);
    $stmt_update->bindParam(':new_role', $newRole, PDO::PARAM_STR);
    $stmt_update->bindParam(':id', $userId, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        $affectedRows = $stmt_update->rowCount();
        if ($affectedRows > 0) {
            $db->commit(); // Xác nhận transaction
            http_response_code(200); // OK
            echo json_encode(['success' => true, 'message' => 'Cập nhật vai trò thành công.'], JSON_UNESCAPED_UNICODE);
        } else {
            // Không có hàng nào bị ảnh hưởng (có thể vai trò đã giống hoặc ID không tồn tại nhưng đã kiểm tra ở trên)
            $db->rollBack();
            http_response_code(200); // Vẫn có thể là OK nếu vai trò không đổi, hoặc 404 nếu thực sự không tìm thấy
            echo json_encode(['success' => false, 'message' => 'Vai trò người dùng không thay đổi hoặc không tìm thấy người dùng.', 'details' => 'Có thể vai trò đã được đặt.'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Lỗi không mong muốn trong quá trình thực thi truy vấn
        $db->rollBack(); // Hoàn tác transaction
        http_response_code(500); // Internal Server Error
        error_log("Lỗi khi cập nhật vai trò: " . json_encode($stmt_update->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật vai trò người dùng: ' . $stmt_update->errorInfo()[2]], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack(); // Hoàn tác transaction trong trường hợp có exception
    http_response_code(500);
    error_log("Lỗi PDO khi cập nhật vai trò: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ nội bộ khi cập nhật vai trò.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>