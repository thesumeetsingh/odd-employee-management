<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin-login.php");
    exit();
}

$success = '';
$error = '';

// Function to generate the next employee ID
function generateNextEmployeeID($conn) {
    $query = "SELECT id FROM odd_employee WHERE id LIKE 'ARC%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['id'];
        $number = (int) substr($last_id, 3) + 1;
        return 'ARC' . str_pad($number, 4, '0', STR_PAD_LEFT);
    } else {
        return 'ARC0001';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate employee ID
    $id = generateNextEmployeeID($conn);
    
    // Create employee folder
    $folder_location = 'employees/' . $id;
    if (!file_exists($folder_location)) {
        mkdir($folder_location, 0777, true);
    }
    
    // Handle file upload
    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
        $resume_name = basename($_FILES['resume']['name']);
        $resume_path = $folder_location . '/' . $resume_name;
        move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path);
    }
    
    // Prepare data - convert empty strings to NULL
    $first_name = !empty($_POST['first_name']) ? $_POST['first_name'] : null;
    $last_name = !empty($_POST['last_name']) ? $_POST['last_name'] : null;
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $date_of_joining = !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null;
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $phone_number = !empty($_POST['phone_number']) ? $_POST['phone_number'] : null;
    $address = !empty($_POST['address']) ? $_POST['address'] : null;
    
    // Set password as employee ID (hashed)
    $password = password_hash($id, PASSWORD_DEFAULT);
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO odd_employee (
        id, password, first_name, last_name, date_of_birth, 
        date_of_joining, email, phone_number, address, folder_location
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "ssssssssss", 
        $id, $password, $first_name, $last_name, $date_of_birth,
        $date_of_joining, $email, $phone_number, $address, $folder_location
    );
    
    if ($stmt->execute()) {
        $success = "Employee added successfully!<br>Employee ID: <strong>$id</strong><br>Default Password: <strong>$id</strong> (same as Employee ID)";
    } else {
        $error = "Error adding employee: " . $conn->error;
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
  <title>Add Employee | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .form-container {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      padding: 30px;
      margin-top: 30px;
    }
    .form-title {
      text-align: center;
      margin-bottom: 30px;
      color: #343a40;
    }
    .required-field::after {
      content: " *";
      color: red;
    }
    .password-note {
      font-size: 0.9rem;
      color: #6c757d;
      font-style: italic;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="../index.html">
        <img src="../assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <div>
        <a href="admin-dashboard.php" class="btn btn-outline-secondary mr-2">Back to Dashboard</a>
        <a href="logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Add Employee Form -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-10">
        <div class="form-container">
          <h2 class="form-title">Add New Employee</h2>
          
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
          <?php endif; ?>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>
          
          <form method="POST" action="add-employee.php" enctype="multipart/form-data">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="first_name" class="required-field">First Name</label>
                  <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="last_name" class="required-field">Last Name</label>
                  <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="date_of_birth">Date of Birth</label>
                  <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="date_of_joining">Date of Joining</label>
                  <input type="date" class="form-control" id="date_of_joining" name="date_of_joining">
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="email">Email</label>
                  <input type="email" class="form-control" id="email" name="email">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="phone_number">Phone Number</label>
                  <input type="tel" class="form-control" id="phone_number" name="phone_number">
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <label for="address">Address</label>
              <textarea class="form-control" id="address" name="address" rows="3"></textarea>
            </div>
            
            <div class="form-group">
              <label for="resume">Resume/CV (Optional)</label>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="resume" name="resume">
                <label class="custom-file-label" for="resume">Choose file</label>
              </div>
            </div>
            
            <div class="alert alert-info mt-4">
              <strong>Note:</strong> The employee ID will be automatically generated (ARC0001 format). 
              The default password will be the same as the employee ID (stored securely as a hash).
            </div>
            
            <button type="submit" class="btn btn-dark btn-block mt-3">Add Employee</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="contact-section fixed-bottom text-center py-3">
    <div class="container">
      <p>9907415948 | 6262023330</p>
      <p>oddbhilai@gmail.com</p>
    </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    // Show the selected file name
    $('.custom-file-input').on('change', function() {
      let fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
  </script>
</body>
</html>