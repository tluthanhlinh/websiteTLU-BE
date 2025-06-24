<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\users\Login.php

// === BẮT ĐẦU BỘ ĐỆM ĐẦU RA VÀ CẤU HÌNH XỬ LÝ LỖI SỚM NHẤT CÓ THỂ ===
// Điều này ngăn chặn mọi output sớm và cho phép chúng ta kiểm soát phản hồi JSON hoàn toàn.
ob_start();

// === KHỞI ĐỘNG PHP SESSION SỚM NHẤT CÓ THỂ ===
// Điều này phải được gọi trước mọi output.
session_start(); 

// Cấu hình báo cáo lỗi PHP. Trong môi trường production, bạn nên tắt display_errors.
error_reporting(E_ALL);
ini_set('display_errors', 1); // Rất hữu ích khi debug, nhưng có thể gây rò rỉ thông tin
                               // Khi triển khai, nên tắt: ini_set('display_errors', 0);

// Thiết lập một trình xử lý ngoại lệ tùy chỉnh để bắt mọi lỗi và trả về JSON
set_exception_handler(function ($exception) {
    // Xóa mọi output đã được đệm trước đó
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json'); // Đảm bảo header JSON được gửi
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi server không mong muốn: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit();
});

// === HEADERS ĐỂ XỬ LÝ CORS VÀ ĐỊNH DẠNG PHẢN HỒI ===
// Cho phép frontend từ localhost:3000 truy cập
header('Access-Control-Allow-Origin: http://localhost:3000'); 
header('Content-Type: application/json'); // Header này sẽ được gửi sau khi ob_start
header('Access-Control-Allow-Methods: POST, OPTIONS'); 
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true'); 

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean(); // Xóa bộ đệm và không gửi gì cả
    http_response_code(200);
    exit();
}

try {
    // === CÁC DÒNG INCLUDE CẦN THIẾT VÀ ĐÚNG ĐƯỜNG DẪN ===
    require_once '../../config/Database/Database.php';
    require_once '../../models/User.php';
    require_once '../../config/auth.php'; // Cho JWT

    // === KHỞI TẠO ĐỐI TƯỢNG DATABASE VÀ LẤY KẾT NỐI PDO ===
    $database = new Database();
    $db = $database->connect(); 

    // === KHỞI TẠO CÁC ĐỐI TƯỢNG MODEL ===
    $user = new User($db); 
    $auth = new Auth(); 

    // === LẤY DỮ LIỆU JSON TỪ REQUEST BODY ===
    $input = file_get_contents('php://input');
    // Ghi log nội dung input thô để debug
    error_log("Login API Raw Input: " . $input);
    $data = json_decode($input, true); 
    // Ghi log dữ liệu sau khi decode để debug
    error_log("Login API Decoded Data: " . print_r($data, true));

    // Kiểm tra lỗi parsing JSON hoặc thiếu dữ liệu cần thiết
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['username']) || !isset($data['password'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: Thiếu tên đăng nhập hoặc mật khẩu.']);
        exit();
    }

    // Gán dữ liệu nhận được vào thuộc tính của đối tượng User
    $user->username = trim($data['username']); 
    $user->password = $data['password']; 

    // Ghi log giá trị username và password đã nhận
    error_log("Login API Username: " . $user->username);
    error_log("Login API Password (hashed in User model): [HIDDEN]"); // Không log mật khẩu thô

    // === KIỂM TRA DỮ LIỆU ĐẦU VÀO TRÊN SERVER-SIDE ===
    if (empty($user->username) || empty($user->password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không được để trống.']);
        exit();
    }

    // === THỰC HIỆN ĐĂNG NHẬP THÔNG QUA USER MODEL ===
    // Đảm bảo phương thức login() trong User.php trả về true/false và gán các thuộc tính user
    if ($User->Login()) {
        http_response_code(200); // OK

        // === THIẾT LẬP PHP SESSION SAU KHI ĐĂNG NHẬP THÀNH CÔNG ===
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role'] = $user->role; // Lưu vai trò vào session

        // Ghi log thông tin session sau khi đăng nhập thành công
        error_log("Login API Session Data after successful login: " . print_r($_SESSION, true));

        // Tạo JWT Token với thông tin người dùng
        $token = $auth->generateToken([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'role' => $user->role
        ]);

        if ($token === false) {
             throw new Exception("Lỗi khi tạo mã thông báo JWT.");
        }

        // Trả về phản hồi thành công cùng với JWT và thông tin người dùng
        echo json_encode([
            'success' => true,
            'message' => 'Đăng nhập thành công!',
            'jwt' => $token,
            'user_info' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'full_name' => $user->full_name ?? null, 
                'role' => $user->role
            ]
        ]);
    } else {
        // Đăng nhập thất bại (Sai tên đăng nhập hoặc mật khẩu)
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Sai tên đăng nhập hoặc mật khẩu.']);
    }

} catch (Exception $e) {
    // Mọi ngoại lệ không được bắt trước đó sẽ được trình xử lý ngoại lệ tùy chỉnh bắt
    // và trả về dưới dạng JSON với mã 500.
    // Lỗi sẽ được ghi vào log server.
    error_log("Unhandled Login Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    // Trình xử lý ngoại lệ sẽ tự động gửi JSON và exit.
} finally {
    // Luôn luôn kết thúc bộ đệm đầu ra trong khối finally, 
    // đảm bảo không có gì được in ra sớm nếu không có exception nào xảy ra.
    if (ob_get_length()) {
        ob_end_flush(); // Gửi nội dung từ bộ đệm (nếu có)
    }
}
?>
