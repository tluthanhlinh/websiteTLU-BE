<?php
// backend_railway/GetSinglePost.php

// Bao gồm file cấu hình database để thiết lập kết nối và CORS headers
require_once 'Database.php'; 

// Thiết lập slug mặc định rỗng
$slug = null;

// Lấy slug từ tham số URL (query parameter)
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
}

$article = null; // Khởi tạo biến bài viết rỗng

if ($slug) {
    // Truy vấn SQL để lấy bài viết cụ thể theo slug
    // Sử dụng pg_prepare và pg_execute để tránh SQL Injection
    $query = "SELECT id, title, slug, content, image_url, category, author_name, created_at FROM articles WHERE slug = $1";
    
    // Chuẩn bị câu lệnh
    $prepare_name = "get_single_article_by_slug";
    $result_prepare = pg_prepare($conn, $prepare_name, $query);

    if ($result_prepare) {
        // Thực thi câu lệnh đã chuẩn bị với tham số slug
        $result_execute = pg_execute($conn, $prepare_name, array($slug));

        if ($result_execute) {
            // Lấy dữ liệu
            $article = pg_fetch_assoc($result_execute);
        } else {
            error_log("Lỗi thực thi truy vấn PostgreSQL: " . pg_last_error($conn));
        }
    } else {
        error_log("Lỗi chuẩn bị truy vấn PostgreSQL: " . pg_last_error($conn));
    }
}

// Trả về kết quả dưới dạng JSON. Nếu không tìm thấy, $article sẽ là null.
echo json_encode($article);

// Đóng kết nối database
pg_close($conn);
?>