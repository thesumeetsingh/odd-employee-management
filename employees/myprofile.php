<?php
session_start();
require_once '../config/db_connection.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$success = '';
$error = '';

// Get employee details
$stmt = $conn->prepare("SELECT * FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update profile information
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    
    // Handle resume upload
    $resume_uploaded = false;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
        $resume = $_FILES['resume'];
        
        // Validate file type
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($resume['type'], $allowed_types)) {
            $error = "Only PDF and Word documents are allowed for resumes.";
        } else {
            // Create directory if it doesn't exist
            $upload_dir = "../employees/" . $employee_id . "/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate filename
            $filename = strtolower(str_replace(' ', '', $first_name . $last_name)) . "Resume" . strrchr($resume['name'], '.');
            $target_file = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($resume['tmp_name'], $target_file)) {
                $resume_uploaded = true;
            } else {
                $error = "Error uploading resume file.";
            }
        }
    }
    
    // Update database if no errors
    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE odd_employee 
                              SET first_name = ?, last_name = ?, date_of_birth = ?, 
                                  email = ?, phone_number = ?, address = ?, 
                                  updated_at = NOW()
                              WHERE id = ?");
        $stmt->bind_param("sssssss", 
            $first_name, $last_name, $date_of_birth,
            $email, $phone_number, $address,
            $employee_id
        );
        
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh employee data
            $refresh_stmt = $conn->prepare("SELECT * FROM odd_employee WHERE id = ?");
            $refresh_stmt->bind_param("s", $employee_id);
            $refresh_stmt->execute();
            $result = $refresh_stmt->get_result();
            $employee = $result->fetch_assoc();
            $refresh_stmt->close();
        } else {
            $error = "Error updating profile: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .profile-card {
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      padding: 30px;
      margin-top: 20px;
    }
    .profile-header {
      border-bottom: 1px solid #dee2e6;
      padding-bottom: 20px;
      margin-bottom: 30px;
      text-align: center;
    }
    .employee-name {
      font-size: 2rem;
      font-weight: 500;
      margin-bottom: 5px;
    }
    .employee-details {
      color: #6c757d;
      font-size: 1.1rem;
    }
    .form-group.required label:after {
      content: " *";
      color: red;
    }
    .resume-preview {
      border: 1px dashed #ccc;
      padding: 15px;
      border-radius: 5px;
      margin-top: 10px;
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
        <a href="../employee-dashboard.php" class="btn btn-outline-dark mr-2">
          <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
        <a href="../logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="profile-card">
          <!-- Profile Header with Employee Name -->
          <div class="profile-header">
            <h1 class="employee-name"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h1>
            <div class="employee-details">
              <?php echo htmlspecialchars($employee['role']); ?> • ID: <?php echo htmlspecialchars($employee_id); ?>
            </div>
          </div>
          
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
          <?php endif; ?>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>
          
          <form method="POST" action="myprofile.php" enctype="multipart/form-data">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="first_name">First Name</label>
                  <input type="text" class="form-control" id="first_name" name="first_name" 
                         value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="last_name">Last Name</label>
                  <input type="text" class="form-control" id="last_name" name="last_name" 
                         value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="employee_id">Employee ID</label>
                  <input type="text" class="form-control" id="employee_id" 
                         value="<?php echo htmlspecialchars($employee['id']); ?>" readonly>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="date_of_birth">Date of Birth</label>
                  <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                         value="<?php echo htmlspecialchars($employee['date_of_birth']); ?>" required>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="email">Email</label>
                  <input type="email" class="form-control" id="email" name="email" 
                         value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="phone_number">Phone Number</label>
                  <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                         value="<?php echo htmlspecialchars($employee['phone_number']); ?>" required>
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <label for="address">Address</label>
              <textarea class="form-control" id="address" name="address" rows="3"><?php 
                echo htmlspecialchars($employee['address']); 
              ?></textarea>
            </div>
            
            <div class="form-group">
              <label for="resume">Upload Resume (PDF or Word)</label>
              <input type="file" class="form-control-file" id="resume" name="resume" accept=".pdf,.doc,.docx">
              <small class="form-text text-muted">
                Max file size: 5MB. Allowed formats: PDF, DOC, DOCX
              </small>
              
              <?php 
              $resume_path = glob("../employees/" . $employee_id . "/*Resume.*");
              if (!empty($resume_path)): ?>
                <div class="resume-preview mt-2">
                  <h6>Current Resume:</h6>
                  <a href="<?php echo str_replace('../', '', $resume_path[0]); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-download mr-1"></i> Download Current Resume
                  </a>
                </div>
              <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-dark btn-block">
              <i class="fas fa-save mr-2"></i> Save Changes
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>