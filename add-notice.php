<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Handle form submission
$success = $error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (!empty($title) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notices (title, message, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $title, $message);
        if ($stmt->execute()) {
            $success = "Notice sent successfully!";
        } else {
            $error = "Failed to send notice.";
        }
        $stmt->close();
    } else {
        $error = "Please fill out all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notify Employees | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
</head>
<body>
  <div class="container mt-5">
    <h2 class="mb-4"><i class="fas fa-bullhorn"></i> Notify Employees</h2>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="title">Notice Title</label>
        <input type="text" name="title" id="title" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="message">Notice Message</label>
        <textarea name="message" id="message" class="form-control" rows="5" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Notice</button>
    </form>
  </div>
</body>
</html>
