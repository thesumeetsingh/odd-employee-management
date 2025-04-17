<?php
require_once '../config.php'; // Database configuration

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create holidays table with day count support
    $sql = "CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_name VARCHAR(100) NOT NULL,
        from_date DATE NOT NULL,
        to_date DATE NOT NULL,
        day_count INT NOT NULL,
        financial_year YEAR NOT NULL,
        comments TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_financial_year (financial_year),
        INDEX idx_date_range (from_date, to_date)
    )";

    $conn->exec($sql);

    echo "Holidays table with day count created successfully";

} catch(PDOException $e) {
    die("Error creating holidays table: " . $e->getMessage());
}

$conn = null;
?>