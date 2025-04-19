<?php
session_start();
require_once '../config/db_connection.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit();
}
date_default_timezone_set('Asia/Kolkata'); // Set your local time zone (example: India)

$employee_id = $_SESSION['employee_id'];
$today = date('Y-m-d');
$success = '';
$error = '';

// Get employee name
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$employee_name = $employee['name'];
$stmt->close();

// Check today's attendance status
$attendance = null;
$stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->bind_param("ss", $employee_id, $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $attendance = $result->fetch_assoc();
}
$stmt->close();

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_in'])) {
    $check_in_time = date('H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, employee_name, date, check_in, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("ssss", $employee_id, $employee_name, $today, $check_in_time);
    
    if ($stmt->execute()) {
        $success = "Check-in recorded successfully at " . date('h:i A', strtotime($check_in_time));
        // Refresh attendance data
        $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->bind_param("ss", $employee_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error = "Error recording check-in: " . $conn->error;
    }
}

// Handle check-out
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_out'])) {
    $check_out_time = date('H:i:s');
    $comments = !empty($_POST['comments']) ? $_POST['comments'] : null;
    
    // Calculate hours worked
    $check_in = new DateTime($attendance['check_in']);
    $check_out = new DateTime($check_out_time);
    $diff = $check_out->diff($check_in);
    $total_hours = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
    
    // Determine counted hours and status
    if ($total_hours >= 8) {
        $counted_hours = 8;
        $status = 'present';
    } elseif ($total_hours >= 4) {
        $counted_hours = 4;
        $status = 'half_day';
    } else {
        $counted_hours = 0;
        $status = 'pending'; // Needs admin approval
    }
    
    $stmt = $conn->prepare("UPDATE attendance SET check_out = ?, status = ?, total_hours = ?, counted_hours = ?, comments = ? WHERE employee_id = ? AND date = ?");
    $stmt->bind_param("ssdssss", $check_out_time, $status, $total_hours, $counted_hours, $comments, $employee_id, $today);
    
    if ($stmt->execute()) {
        $success = "Check-out recorded successfully at " . date('h:i A', strtotime($check_out_time));
        // Refresh attendance data
        $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->bind_param("ss", $employee_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error = "Error recording check-out: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Attendance | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .attendance-card {
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      padding: 30px;
      margin-top: 20px;
    }
    .time-display {
      font-size: 1.2rem;
      font-weight: bold;
    }
    .status-badge {
      font-size: 1rem;
      padding: 8px 15px;
    }
    .btn-action {
      font-size: 1.1rem;
      padding: 10px 25px;
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
        <span class="mr-3"><?php echo htmlspecialchars($employee_name); ?></span>
        <a href="../logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Attendance Content -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="attendance-card">
          <h2 class="text-center mb-4">Today's Attendance</h2>
          <p class="text-center text-muted mb-4"><?php echo date('l, F j, Y'); ?></p>
          
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
          <?php endif; ?>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>
          
          <div class="row text-center mb-4">
            <div class="col-md-6 mb-3">
              <h5>Check In</h5>
              <div class="time-display">
                <?php echo isset($attendance['check_in']) ? date('h:i A', strtotime($attendance['check_in'])) : '--:-- --'; ?>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <h5>Check Out</h5>
              <div class="time-display">
                <?php echo isset($attendance['check_out']) ? date('h:i A', strtotime($attendance['check_out'])) : '--:-- --'; ?>
              </div>
            </div>
          </div>
          
          <div class="text-center mb-4">
            <h5>Status</h5>
            <?php if (!isset($attendance)): ?>
              <span class="badge badge-secondary status-badge">Not Checked In</span>
            <?php else: ?>
              <?php 
                $status_class = [
                  'pending' => 'badge-warning',
                  'present' => 'badge-success',
                  'half_day' => 'badge-info',
                  'leave' => 'badge-primary'
                ];
              ?>
              <span class="badge <?php echo $status_class[$attendance['status']] ?? 'badge-secondary'; ?> status-badge">
                <?php 
                  echo ucfirst(str_replace('_', ' ', $attendance['status']));
                  if ($attendance['status'] == 'half_day') echo ' (4 hrs)';
                ?>
              </span>
            <?php endif; ?>
          </div>
          
          <?php if (!isset($attendance)): ?>
            <!-- Check In Form -->
            <form method="POST" action="my-attendance.php">
              <button type="submit" name="check_in" class="btn btn-dark btn-action btn-block">
                <i class="fas fa-sign-in-alt mr-2"></i> Check In
              </button>
            </form>
          <?php elseif (!isset($attendance['check_out'])): ?>
            <!-- Check Out Form -->
            <form method="POST" action="my-attendance.php">
              <div class="form-group">
                <label for="comments">Comments (Optional)</label>
                <textarea class="form-control" id="comments" name="comments" rows="2"></textarea>
              </div>
              <button type="submit" name="check_out" class="btn btn-dark btn-action btn-block">
                <i class="fas fa-sign-out-alt mr-2"></i> Check Out
              </button>
            </form>
          <?php else: ?>
            <div class="alert alert-info text-center">
              Today's attendance has been completed. Check your <a href="attendance-history.php">attendance history</a> for details.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>



  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>