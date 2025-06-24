<?php 
class Post {
    // Database
    private $conn;
    private $table = 'posts'; // Đảm bảo tên bảng là 'posts' như trong database

    // Post Properties
    public $id;
    public $category_id;
    public $category_name; // Để lưu tên danh mục từ bảng categories
    public $title;
    public $content;
    public $author;
    public $created_at;
    public $updated_at;
    public $user_id; // Thêm thuộc tính user_id
    // public $status; // Thêm nếu bạn có cột status trong bảng posts
    // public $views; // Thêm nếu bạn có cột views trong bảng posts

    // Constructor with DB
    public function __construct($db) {
        $this->conn = $db;
    }

    // Get Posts (Đọc tất cả hoặc theo danh mục hoặc theo tên danh mục)
    public function read() {
        // Tạo truy vấn cơ bản
        $query = 'SELECT
                    c.name as category_name,
                    p.id, 
                    p.title, 
                    p.content, 
                    p.author, 
                    p.created_at, 
                    p.updated_at,
                    p.user_id,
                    p.category_id
                    FROM
                    ' . $this->table . ' p
                  LEFT JOIN
                    categories c ON p.category_id = c.id';

        // Nếu có category_name được truyền vào (từ GetPosts.php?category_name=X)
        if (!empty($this->category_name)) {
            $query .= ' WHERE c.name = :category_name';
        }

        $query .= ' ORDER BY p.created_at DESC';
        // Thêm LIMIT nếu bạn muốn phân trang hoặc giới hạn số lượng bài viết trên trang chủ
        // Ví dụ: $query .= ' LIMIT 0, 10'; // Lấy 10 bài viết đầu tiên

        // Chuẩn bị câu lệnh
        $stmt = $this->conn->prepare($query);

        // Bind tham số nếu có category_name
        if (!empty($this->category_name)) {
            // Đảm bảo kiểu dữ liệu là string
            $stmt->bindParam(':category_name', $this->category_name, PDO::PARAM_STR);
        }

        // Thực thi truy vấn
        if ($stmt->execute()) {
            return $stmt;
        } else {
            // In lỗi nếu có
            printf("Error in Post::read(): %s.\n", $stmt->errorInfo()[2]);
            return false;
        }
    }

    // Get Single Post (Đọc một bài viết cụ thể theo ID)
    public function readSingle() {
        // Tạo truy vấn
        $query = 'SELECT
                    c.name as category_name,
                    p.id, 
                    p.title, 
                    p.content, 
                    p.author, 
                    p.created_at, 
                    p.updated_at,
                    p.user_id,
                    p.category_id
                    FROM
                    ' . $this->table . ' p
                  LEFT JOIN
                    categories c ON p.category_id = c.id
                  WHERE
                    p.id = :id
                  LIMIT 0,1'; // Giới hạn 1 kết quả cho bài viết đơn

        // Chuẩn bị câu lệnh
        $stmt = $this->conn->prepare($query);

        // Bind ID (Đảm bảo ID là kiểu số nguyên)
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        // Thực thi truy vấn
        if ($stmt->execute()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Set properties
                $this->title = $row['title'];
                $this->content = $row['content'];
                $this->author = $row['author'];
                $this->created_at = $row['created_at'];
                $this->updated_at = $row['updated_at'];
                $this->category_id = $row['category_id']; // Lấy category_id
                $this->category_name = $row['category_name']; // Lấy category_name
                $this->user_id = $row['user_id']; // Lấy user_id
                return true;
            }
            return false;
        } else {
            // In lỗi nếu có
            printf("Error in Post::readSingle(): %s.\n", $stmt->errorInfo()[2]);
            return false;
        }
    }

    // Create Post
    public function create() {
        // Create query
        $query = 'INSERT INTO ' . $this->table . '
                  SET
                    title = :title,
                    content = :content,
                    author = :author,
                    category_id = :category_id,
                    user_id = :user_id';

        // Prepare statement
        $stmt = $this->conn->prepare($query);

        // Clean data (Sanitize)
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));

        // Bind data
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':author', $this->author);
        $stmt->bindParam(':category_id', $this->category_id);
        $stmt->bindParam(':user_id', $this->user_id);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        // Print error if something goes wrong
        printf("Error in Post::create(): %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Update Post
    public function update() {
        // Create query
        $query = 'UPDATE ' . $this->table . '
                  SET
                    title = :title,
                    content = :content,
                    author = :author,
                    category_id = :category_id,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE
                    id = :id';

        // Prepare statement
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind data
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':author', $this->author);
        $stmt->bindParam(':category_id', $this->category_id);
        $stmt->bindParam(':id', $this->id);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        // Print error if something goes wrong
        printf("Error in Post::update(): %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Delete Post
    public function delete() {
        // Create query
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id';

        // Prepare statement
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind data
        $stmt->bindParam(':id', $this->id);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        // Print error if something goes wrong
        printf("Error in Post::delete(): %s.\n", $stmt->errorInfo()[2]);
        return false;
    }
}