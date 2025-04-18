<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'oddbhilai';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
date_default_timezone_set('Asia/Kolkata'); 
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
?>