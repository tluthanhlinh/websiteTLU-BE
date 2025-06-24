<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\post\GetPosts.php

// === HEADERS ĐỂ XỬ LÝ CORS VÀ ĐỊNH DẠNG PHẢN HỒI ===
header('Access-Control-Allow-Origin: http://localhost:3000'); 
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS'); 
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');

// Xử lý Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0); 
}

// === CÁC DÒNG INCLUDE CẦN THIẾT ===
require_once '../../config/Database/Database.php';
require_once '../../models/Post.php';
require_once '../../models/Category.php'; 

// === KHỞI TẠO ĐỐI TƯỢNG DATABASE VÀ LẤY KẾT NỐI PDO ===
$database = new Database();
$db = $database->connect(); 

// === KHỞI TẠO CÁC ĐỐI TƯỢNG MODEL ===
$post = new Post($db);
$category = new Category($db); 

$stmt = null; 

try {
    // Kiểm tra xem có tham số 'id' không (để lấy một bài viết duy nhất)
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $post->id = $_GET['id'];
        if ($post->readSingle()) {
            $post_arr = array(
                'id' => $post->id,
                'title' => $post->title,
                'content' => html_entity_decode($post->content), 
                'author' => $post->author,
                'category_id' => $post->category_id,
                'category_name' => $post->category_name,
                'user_id' => $post->user_id,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
             
            );
            http_response_code(200);
            echo json_encode($post_arr);
            exit(); 
        } else {
            http_response_code(404);
            echo json_encode(array('message' => 'Bài viết không tìm thấy.'));
            exit();
        }
    }
    // Kiểm tra nếu có category_name (từ frontend: ?category_name=Tin%20t%C3%BCc)
    else if (isset($_GET['category_name']) && !empty($_GET['category_name'])) {
        $post->category_name = urldecode($_GET['category_name']);
        $stmt = $post->read(); 
    }
    // Kiểm tra nếu có category (từ frontend: ?category=news, events) - SỬ DỤNG ÁNH XẠ NÀY
    else if (isset($_GET['category']) && !empty($_GET['category'])) {
        $category_slug = $_GET['category']; 
        
        // Đảm bảo ánh xạ này khớp CHÍNH XÁC với tên trong bảng `categories`
        $category_mapping = [
            'news' => 'Tin tức', 
            'events' => 'Sự kiện', 
            'gioithieu' => 'Giới thiệu', 
            'hoatdong' => 'Hoạt động', 
            'thongbao' => 'Thông báo',
            'sinhoat' => 'Sinh hoạt',
            'doisong' => 'Đời sống'
            // THÊM VÀ THAY ĐỔI CÁC TÊN NÀY ĐỂ KHỚP VỚI DATABASE CỦA BẠN (VD: 'thongbao' => 'Thông báo')
        ];

        if (isset($category_mapping[$category_slug])) {
            $post->category_name = $category_mapping[$category_slug];
            $stmt = $post->read(); 
        } else {
            http_response_code(404);
            echo json_encode(array('message' => 'Danh mục không hợp lệ hoặc không tìm thấy.'));
            exit();
        }
    }
    // Lấy tất cả bài viết (nếu không có tham số nào)
    else {
        $stmt = $post->read(); 
    }

    // Nếu có kết quả từ $stmt->execute()
    if ($stmt && $stmt->rowCount() > 0) {
        $posts_arr = array();
        $posts_arr['data'] = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);

            $post_item = array(
                'id' => $id,
                'title' => $title,
                'content' => html_entity_decode($content), 
                'author' => $author,
                'category_id' => $category_id,
                'category_name' => $category_name,
                'user_id' => $user_id,
                'created_at' => $created_at,
                'updated_at' => $updated_at,
                'status' => $status ?? null, // Thêm nếu có
                'views' => $views ?? null // Thêm nếu có
            );

            array_push($posts_arr['data'], $post_item);
        }

        http_response_code(200);
        echo json_encode($posts_arr);
    } else {
        http_response_code(404); 
        echo json_encode(array('message' => 'Không tìm thấy bài viết nào.'));
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(array('message' => 'Đã xảy ra lỗi server: ' . $e->getMessage()));
    error_log('Error in GetPosts.php: ' . $e->getMessage());
}
