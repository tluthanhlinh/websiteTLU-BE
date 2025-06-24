<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\users\Register.php

// === HEADERS ĐỂ XỬ LÝ CORS VÀ ĐỊNH DẠNG PHẢN HỒI ===
header('Access-Control-Allow-Origin: http://localhost:3000'); 
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === CÁC DÒNG INCLUDE CẦN THIẾT VÀ ĐÚNG ĐƯỜNG DẪN ===
// Đảm bảo đường dẫn này đúng từ Register.php (trong api/users/) đến Database.php (trong config/Database/)
require_once '../../config/Database/Database.php';

// Đảm bảo đường dẫn này đúng từ Register.php (trong api/users/) đến User.php (trong models/)
require_once '../../models/User.php';

// === XÓA BỎ HOẶC COMMENT CÁC DÒNG INCLUDE KHÔNG CẦN THIẾT HOẶC SAI ĐƯỜNG DẪN: ===
// require_once '../../src/utils/cors.php'; // Đã xử lý CORS trực tiếp ở trên
// include_once __DIR__ . '/../../config/Database/Database.php'; // Dư thừa
// require_once '../../config/Database/Database_test.php'; // Gây lỗi "Failed to open stream"
// include_once __DIR__ . '/../../models/Post.php';
// include_once __DIR__ . '/../../models/Category.php';

// === KHỞI TẠO ĐỐI TƯỢNG DATABASE VÀ LẤY KẾT NỐI PDO ===
$database = new Database();
$db = $database->connect(); // Gán đối tượng PDO đã kết nối vào biến $db

// === KHỞI TẠO ĐỐI TƯỢNG USER VỚI KẾT NỐI DATABASE ===
$user = new User($db);

// === LẤY DỮ LIỆU TỪ REQUEST BODY ===
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu JSON không hợp lệ.']);
    exit();
}

$user->username = $data['username'] ?? null;
$user->email = $data['email'] ?? null;
$user->password = $data['password'] ?? null;
$user->full_name = $data['full_name'] ?? null;
$user->role = $data['role'] ?? 'user';

if (empty($user->username) || empty($user->password) || empty($user->email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ tên đăng nhập, email và mật khẩu.']);
    exit();
}

if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Địa chỉ email không hợp lệ.']);
    exit();
}

if (strlen($user->username) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tên đăng nhập phải có ít nhất 3 ký tự.']);
    exit();
}

if (strlen($user->password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.']);
    exit();
}

if ($user->register()) {
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Đăng ký tài khoản thành công!']);
} else {
    if ($user->usernameExists()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.']);
    } elseif ($user->emailExists()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email đã được sử dụng.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Đăng ký thất bại. Vui lòng thử lại.']);
    }
}
?>