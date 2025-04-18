<?php
session_start();
require_once 'config/db_connection.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = trim($_POST['id']);
    $password = trim($_POST['password']);
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, password FROM odd_employee WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password (works for both hashed and plaintext - admin case)
        if (password_verify($password, $user['password']) || ($user['id'] == 'admin' && $password == 'admin')) {
            $_SESSION['employee_id'] = $user['id'];
            header("Location: employees/employee-dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Employee ID not found!";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ODD Employee Login</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
</head>
<body>
  <!-- Navbar (same as index page) -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="index.html">
        <img src="assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <a href="index.html" class="btn btn-dark">Back to Home</a>
    </div>
  </nav>

  <!-- Login Form -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">
        <div class="card shadow">
          <div class="card-body">
            <h3 class="card-title text-center mb-4">Employee Login</h3>
            
            <?php if (!empty($error)): ?>
              <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
              <div class="form-group">
                <label for="id">Employee ID</label>
                <input type="text" class="form-control" id="id" name="id" required>
              </div>
              <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
              <button type="submit" class="btn btn-dark btn-block">Login</button>
            </form>
            
            <div class="text-center mt-3">
              <a href="admin-login.php" class="text-muted">Login as Admin</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer (same as index page) -->
  <footer class="contact-section fixed-bottom text-center py-3">
    <div class="container">
      <p>9907415948 | 6262023330</p>
      <p>oddbhilai@gmail.com</p>
    </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>