<?php
require_once 'config/db_connection.php';

for ($i = 6; $i <= 26; $i++) {
    $emp_id = 'ARC' . str_pad($i, 4, '0', STR_PAD_LEFT);
    $hashed_password = password_hash($emp_id, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE odd_employee SET password = ? WHERE id = ?");
    $stmt->bind_param('ss', $hashed_password, $emp_id);
    $stmt->execute();

    echo "Updated password for $emp_id<br>";
}

echo "✅ Passwords updated successfully.";
?>
