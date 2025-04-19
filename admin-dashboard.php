<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Get admin details
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT first_name, last_name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Count pending leave requests
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM leave_requests WHERE status = 'pending' AND is_seen = FALSE");
$stmt->execute();
$result = $stmt->get_result();
$pending_requests = $result->fetch_assoc()['pending_count'];
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
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
    .badge-notification {
      position: absolute;
      top: -10px;
      right: -10px;
    }
    .tile-container {
      position: relative;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="index.html">
        <img src="assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <div>
        <span class="mr-3">Welcome, <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></span>
        <a href="logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Dashboard Content -->
  <div class="container mt-5 pt-5">
    <div class="row mb-4">
      <div class="col-12">
        <h2>Admin Dashboard</h2>
        <hr>
      </div>
    </div>
    
    <div class="row">
      <!-- Add Employee Tile -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="add-employee.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-user-plus"></i>
            <h5>Add Employee</h5>
          </div>
        </a>
      </div>
      
      <!-- Attendance Tile -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="attendance.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-calendar-check"></i>
            <h5>Attendance</h5>
          </div>
        </a>
      </div>
      
      <!-- Leave Requests Tile with Notification Badge -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="leave-request.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <div class="tile-container">
              <i class="fas fa-clipboard-list"></i>
              <?php if ($pending_requests > 0): ?>
                <span class="badge badge-danger badge-notification"><?php echo $pending_requests; ?></span>
              <?php endif; ?>
            </div>
            <h5>Leave Requests</h5>
          </div>
        </a>
      </div>
      
      <!-- Employee Profile Tile -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="employee/employee-profile.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-id-card"></i>
            <h5>Employee Profiles</h5>
          </div>
        </a>
      </div>
      
      <!-- Salary Tile -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="employee/employee-salary.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-money-bill-wave"></i>
            <h5>Salary Management</h5>
          </div>
        </a>
      </div>
      
      <!-- Add Holiday Tile -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="add-holiday.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-umbrella-beach"></i>
            <h5>Add Holidays</h5>
          </div>
        </a>
      </div>
      
      <!-- Reports Tile -->
      <div class="col-md-4 col-sm-6 mb-4">
        <a href="reports.php" class="text-decoration-none">
          <div class="dashboard-tile">
            <i class="fas fa-chart-bar"></i>
            <h5>Reports</h5>
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