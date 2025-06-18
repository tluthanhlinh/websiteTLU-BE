<?php
// backend_railway/config/Database.php (theo cấu trúc mới)
require_once 'cors.php';
// Bước 1: Tải các biến môi trường từ file .env (nếu chạy cục bộ)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

class Database {
    private static $pdo;

    public static function connect() {
        if (self::$pdo === null) {
            $dbHost = getenv('PGHOST') ?: 'shuttle.proxy.rlwy.net'; // SỬ DỤNG THÔNG TIN THẬT TỪ RAILWAY CHO DEV CỤC BỘ
            $dbPort = getenv('PGPORT') ?: '33423';   // SỬ DỤNG CỔNG THẬT TỪ RAILWAY CHO DEV CỤC BỘ
            $dbName = getenv('PGDATABASE') ?: 'railway'; // Tên database thật
            $dbUser = getenv('PGUSER') ?: 'postgres';   // User thật
            $dbPassword = getenv('PGPASSWORD') ?: 'MLQLRaNbOaGAncZWZTBOcgscvGplnQpe'; // Password thật

            try {
                // Chuỗi DSN cho PDO (PostgreSQL) phải bắt đầu bằng 'pgsql:'
                $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};user={$dbUser};password={$dbPassword}";
                self::$pdo = new PDO($dsn);

                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                die("Lỗi kết nối database: " . $e->getMessage() . "<br>Vui lòng kiểm tra cấu hình database của bạn.");
            }
        }
        return self::$pdo;
    }
}

// === CẤU HÌNH HEADERS CHO AJAX (CORS) ===
// Các headers này nên được đặt ở ĐẦU MỖI file API endpoint.
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization"); 
header("Content-Type: application/json; charset=UTF-8"); 

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); 
    exit(); 
}
?>