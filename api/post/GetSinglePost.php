<?php
// D:\App\PHP\xampp\htdocs\webxudoan\backend\api\posts\GetPosts.php

// Đường dẫn từ GetPosts.php (trong api/posts/) đến cors.php (trong src/utils/)
require_once '../../src/utils/cors.php'; 

// Đường dẫn từ GetPosts.php (trong api/posts/) đến Database_test.php (trong config/database/)
// >>> DÙNG CHO MÔI TRƯỜNG CỤC BỘ (XAMPP/MySQL) <<<
include_once __DIR__ . '/../../config/Database/Database.php'; // Đổi từ Database_test.php sang Database.php
include_once __DIR__ . '/../../models/Post.php';
include_once __DIR__ . '/../../models/Category.php';
$database = new Database();
$database->connect(); // Lấy đối tượng PDO đã kết nối

// >>> KHI DEPLOY LÊN RENDER (PostgreSQL) HÃY ĐỔI SANG DÒNG DƯỚI VÀ COMMENT DÒNG TRÊN <<<
// require_once '../../config/database/Database.php'; 
// $database = new Database(); 



// Logic để lấy bài viết
try {
    // Sửa đổi truy vấn SQL để lấy 'author' từ bảng 'users' thông qua JOIN
    // Giả sử cột 'author' trong bảng 'posts' của bạn đã được bỏ đi
    // Hoặc nếu bạn muốn 'author' là một cột riêng trong 'posts', thì vẫn giữ nguyên truy vấn cũ.
    // Dưới đây là ví dụ nếu bạn muốn lấy tên tác giả từ bảng 'users'
    $stmt = $db->query("SELECT 
                            p.id, 
                            p.title, 
                            p.body, 
                            u.full_name AS author, -- Lấy full_name từ bảng users làm author
                            p.created_at 
                        FROM 
                            posts p
                        JOIN 
                            users u ON p.user_id = u.id 
                        ORDER BY p.created_at DESC"); 
    
    $posts_arr = array();
    $posts_arr['data'] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Không cần extract() nếu bạn truy cập trực tiếp bằng $row['column_name']
        $post_item = array(
            'id' => $row['id'],
            'title' => $row['title'],
            'body' => html_entity_decode($row['body']), 
            'author' => $row['author'], // Đã lấy từ JOIN
            'created_at' => $row['created_at']
        );
        array_push($posts_arr['data'], $post_item);
    }

    echo json_encode($posts_arr);

} catch (PDOException $e) {
    error_log("Lỗi khi lấy dữ liệu bài viết: " . $e->getMessage());
    http_response_code(500); 
    echo json_encode(array('message' => 'Đã xảy ra lỗi khi tải bài viết: ' . $e->getMessage()));
}
?>