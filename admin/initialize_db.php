<?php
require_once '../config.php'; // Database configuration

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create employees table
    $sql = "CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        dob DATE NULL,
        address TEXT NULL,
        date_of_joining DATE NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        designation VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $conn->exec($sql);

    // Create leave_balance table for financial year tracking
    $sql = "CREATE TABLE IF NOT EXISTS leave_balance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        financial_year YEAR NOT NULL,
        casual_leave INT DEFAULT 0,
        sick_leave INT DEFAULT 0,
        comp_off INT DEFAULT 0,
        total_working_days INT DEFAULT 0,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_employee_year (employee_id, financial_year)
    )";

    $conn->exec($sql);

    // Create holidays table
    $sql = "CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_name VARCHAR(100) NOT NULL,
        holiday_date DATE NOT NULL,
        financial_year YEAR NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_holiday_date (holiday_date, financial_year)
    )";

    $conn->exec($sql);

    // Create employee_attendance table
    $sql = "CREATE TABLE IF NOT EXISTS employee_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('present', 'absent', 'half_day', 'holiday', 'sunday', 'comp_off') NOT NULL,
        remarks TEXT NULL,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_employee_date (employee_id, attendance_date)
    )";

    $conn->exec($sql);

    echo "Database tables created successfully";
} catch(PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}

$conn = null;
?>