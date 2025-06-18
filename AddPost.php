<?php
// backend_railway/AddPost.php

require_once 'Database.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(["message" => "Chỉ chấp nhận phương thức POST."]);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true); 

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['title']) || !isset($data['content']) || !isset($data['category'])) {
    http_response_code(400); 
    echo json_encode(["message" => "Dữ liệu không hợp lệ: Thiếu title, content hoặc category."]);
    exit();
}

$title = $data['title'];
$content = $data['content'];
$category = $data['category'];
$slug = $data['slug'] ?? strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
$image_url = $data['image_url'] ?? null;
$author_name = $data['author_name'] ?? 'Admin';

$sql = "INSERT INTO articles (title, slug, content, image_url, category, author_name) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id";

$result = pg_query_params($conn, $sql, array($title, $slug, $content, $image_url, $category, $author_name));

if ($result) {
    $row = pg_fetch_assoc($result);
    http_response_code(201); 
    echo json_encode(["message" => "Bài viết đã được thêm thành công.", "id" => $row['id']]);
} else {
    http_response_code(500); 
    echo json_encode(["message" => "Lỗi khi thêm bài viết: " . pg_last_error($conn)]);
}

pg_close($conn);
?>