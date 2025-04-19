<?php
session_start();
require_once '../config/db_connection.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get employee details
$employee_id = $_SESSION['employee_id'];
$stmt = $conn->prepare("SELECT first_name, last_name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employee Dashboard | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .dashboard-tile {
      border: 1px solid #dee2e6;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      text-align: center;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      height: 100%;
    }
    .dashboard-tile:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .dashboard-tile i {
      font-size: 2.5rem;
      margin-bottom: 15px;
      color: #343a40;
    }
    .employee-info {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
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
        <span class="mr-3">Welcome, <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></span>
        <span class="mr-3 badge badge-secondary">ID: <?php echo htmlspecialchars($employee_id); ?></span>
        <a href="../logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Dashboard Content -->
  <div class="container mt-5 pt-5">
    <div class="row mb-4">
      <div class="col-12">
        <div class="employee-info">
          <h2>Employee Dashboard</h2>
          <p class="mb-0">Welcome back to your workspace</p>
        </div>
      </div>
    </div>
    
    <div class="row">
      <!-- Manage Profile Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="employee/myprofile.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-user-edit"></i>
            <h5>Manage Profile</h5>
            <p class="text-muted small">Update your personal details</p>
          </div>
        </a>
      </div>
      
      <!-- Attendance Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="my-attendance.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-calendar-check"></i>
            <h5>Attendance</h5>
            <p class="text-muted small">View your attendance records</p>
          </div>
        </a>
      </div>
      
      <!-- Apply for Leave Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="apply-leave.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-clipboard-list"></i>
            <h5>Apply for Leave</h5>
            <p class="text-muted small">Submit leave applications</p>
          </div>
        </a>
      </div>
      
      <!-- Overtime Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="employee/overtime.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-clock"></i>
            <h5>Overtime</h5>
            <p class="text-muted small">Record overtime hours</p>
          </div>
        </a>
      </div>

      <!-- Additional Suggested Tiles -->
      
      <!-- Documents Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="employee/documents.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-file-alt"></i>
            <h5>My Documents</h5>
            <p class="text-muted small">Access your documents</p>
          </div>
        </a>
      </div>
      
      <!-- Payslips Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="employee/payslips.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-file-invoice-dollar"></i>
            <h5>Payslips</h5>
            <p class="text-muted small">View your salary details</p>
          </div>
        </a>
      </div>
      
      <!-- Holidays Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="employee/holidays.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-umbrella-beach"></i>
            <h5>Holidays</h5>
            <p class="text-muted small">View company holidays</p>
          </div>
        </a>
      </div>
      
      <!-- Notices Tile -->
      <div class="col-md-3 col-sm-6 mb-4">
        <a href="employee/notices.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-bullhorn"></i>
            <h5>Notices</h5>
            <p class="text-muted small">Company announcements</p>
          </div>
        </a>
      </div>
    </div>
  </div>



  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>