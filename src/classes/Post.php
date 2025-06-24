<?php 
// D:\App\PHP\xampp\htdocs\webxudoan\backend\models\Post.php

class Post {
    // Thuộc tính database
    private $conn;
    private $table = 'posts'; 

    // Thuộc tính bài viết
    public $id;
    public $category_id;
    public $category_name; 
    public $title;
    public $content;
    public $author;
    public $created_at;
    public $updated_at;
    public $user_id; 
    public $status; // Nếu có cột status
    public $views; // Nếu có cột views

    // Constructor với DB Connection
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Đọc bài viết (tất cả hoặc theo tên danh mục)
     * @return PDOStatement|false Đối tượng statement chứa kết quả hoặc false nếu có lỗi
     */
    public function read() {
        // Tạo truy vấn cơ bản với JOIN để lấy tên danh mục
        $query = 'SELECT
                    c.name as category_name,
                    p.id, 
                    p.title, 
                    p.content, 
                    p.author, 
                    p.created_at, 
                    p.updated_at,
                    p.user_id,
                    p.status,
                    p.views,
                    p.category_id
                  FROM
                    ' . $this->table . ' p
                  LEFT JOIN
                    categories c ON p.category_id = c.id';

        // Nếu có category_name được gán vào thuộc tính $this->category_name, thêm điều kiện WHERE
        if (!empty($this->category_name)) {
            $query .= ' WHERE c.name = :category_name';
        }

        // Luôn sắp xếp bài viết theo ngày tạo giảm dần
        $query .= ' ORDER BY p.created_at DESC'; 

        $stmt = $this->conn->prepare($query);

        // Bind tham số nếu có category_name
        if (!empty($this->category_name)) {
            $stmt->bindParam(':category_name', $this->category_name, PDO::PARAM_STR);
        }

        if ($stmt->execute()) {
            return $stmt;
        } else {
            printf("Error in Post::read(): %s.\n", $stmt->errorInfo()[2]);
            return false;
        }
    }

    /**
     * Đọc một bài viết cụ thể theo ID
     * @return bool True nếu tìm thấy và gán thuộc tính, ngược lại false
     */
    public function readSingle() {
        $query = 'SELECT
                    c.name as category_name,
                    p.id, 
                    p.title, 
                    p.content, 
                    p.author, 
                    p.created_at, 
                    p.updated_at,
                    p.user_id,
                    p.status,
                    p.views,
                    p.category_id
                  FROM
                    ' . $this->table . ' p
                  LEFT JOIN
                    categories c ON p.category_id = c.id
                  WHERE
                    p.id = :id
                  LIMIT 1'; // Giới hạn 1 kết quả cho bài viết đơn

        $stmt = $this->conn->prepare($query);

        // Bind ID (Đảm bảo ID là kiểu số nguyên)
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Gán các thuộc tính của đối tượng Post từ dữ liệu DB
                $this->title = $row['title'];
                $this->content = $row['content'];
                $this->author = $row['author'];
                $this->created_at = $row['created_at'];
                $this->updated_at = $row['updated_at'];
                $this->category_id = $row['category_id']; 
                $this->category_name = $row['category_name']; 
                $this->user_id = $row['user_id']; 
                $this->status = $row['status'];
                $this->views = $row['views'];
                return true;
            }
            return false;
        } else {
            printf("Error in Post::readSingle(): %s.\n", $stmt->errorInfo()[2]);
            return false;
        }
    }

    // Phương thức tạo bài viết mới
    public function create() {
        $query = 'INSERT INTO ' . $this->table . '
                  SET
                    title = :title,
                    content = :content,
                    author = :author,
                    category_id = :category_id,
                    user_id = :user_id,
                    created_at = NOW(),
                    updated_at = NOW()';

        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));

        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':author', $this->author);
        $stmt->bindParam(':category_id', $this->category_id);
        $stmt->bindParam(':user_id', $this->user_id);

        if ($stmt->execute()) {
            return true;
        }

        printf("Error in Post::create(): %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Phương thức cập nhật bài viết
    public function update() {
        $query = 'UPDATE ' . $this->table . '
                  SET
                    title = :title,
                    content = :content,
                    author = :author,
                    category_id = :category_id,
                    updated_at = NOW()
                  WHERE
                    id = :id';

        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':author', $this->author);
        $stmt->bindParam(':category_id', $this->category_id);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }

        printf("Error in Post::update(): %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Phương thức xóa bài viết
    public function delete() {
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id';

        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }

        printf("Error in Post::delete(): %s.\n", $stmt->errorInfo()[2]);
        return false;
    }
}
