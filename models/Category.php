<?php
// backend/models/Category.php

class Category {
    // Database connection and table name
    private $conn;
    private $table_name = "categories";

    // Object properties
    public $id;
    public $name;
    public $created_at;

    // Constructor with $db as database connection
    public function __construct($db){
        $this->conn = $db;
    }

    // Read all categories
    public function read(){
        $query = "SELECT id, name, created_at FROM " . $this->table_name . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Used to get category details by ID
    public function readSingle(){
        $query = "SELECT id, name, created_at FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row){
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Method to read category_id by name
    public function readIdByName(){
        $query = "SELECT id FROM " . $this->table_name . " WHERE name = ? LIMIT 0,1";
        $stmt = $this->conn->prepare( $query );
        $stmt->bindParam(1, $this->name);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            return true;
        }
        return false;
    }
}
?>