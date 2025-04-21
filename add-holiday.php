<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$holidays = [];
$days_count = 0;

// Get admin details
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT first_name, last_name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holiday_name = trim($_POST['holiday_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Validate inputs
    if (empty($holiday_name)) {
        $error = "Holiday name is required";
    } elseif (empty($start_date) || empty($end_date)) {
        $error = "Both start and end dates are required";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error = "End date must be after or equal to start date";
    } else {
        // Calculate days count
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day'); // Include end date in count
        $interval = $start->diff($end);
        $days_count = $interval->days;
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert into odd_holiday table
            $stmt = $conn->prepare("INSERT INTO odd_holiday (holiday_name, start_date, end_date, added_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $holiday_name, $start_date, $end_date, $admin_id);
            $stmt->execute();
            $stmt->close();
            
            // Get all employees
            $employees = $conn->query("SELECT id, first_name, last_name FROM odd_employee");
            
            // Generate dates between start and end date
            $current_date = $start_date;
            while (strtotime($current_date) <= strtotime($end_date)) {
                // For each date, insert holiday for all employees
                foreach ($employees as $employee) {
                    $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
                    
                    // First check if record already exists for this employee and date
                    $check_stmt = $conn->prepare("SELECT employee_id FROM attendance WHERE employee_id = ? AND DATE(created_at) = ?");
                    $check_stmt->bind_param("ss", $employee['id'], $current_date);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 0) {
                        // Only insert if record doesn't exist
                        $attendance_stmt = $conn->prepare("INSERT INTO attendance 
                            (employee_id, employee_name, check_in, check_out, total_hours, counted_hours, status, leave_type, comments, approved_by, created_at, updated_at) 
                            VALUES (?, ?, 0, 0, 0, 0, 'holiday', '', ?, ?, ?, NOW())");
                        
                        $created_at = $current_date . ' 00:00:00';
                        $attendance_stmt->bind_param("sssss", 
                            $employee['id'], 
                            $employee_name,
                            $holiday_name,
                            $admin_id,
                            $created_at
                        );
                        $attendance_stmt->execute();
                        $attendance_stmt->close();
                    } else {
                        // Update existing record if needed
                        $update_stmt = $conn->prepare("UPDATE attendance SET 
                            status = 'holiday',
                            comments = ?,
                            approved_by = ?,
                            updated_at = NOW()
                            WHERE employee_id = ? AND DATE(created_at) = ?");
                        $update_stmt->bind_param("ssss", 
                            $holiday_name,
                            $admin_id,
                            $employee['id'],
                            $current_date
                        );
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    $check_stmt->close();
                }
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            // Commit transaction
            $conn->commit();
            $success = "Holiday added successfully and attendance records updated!";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error adding holiday: " . $e->getMessage();
        }
    }
}

// Get existing holidays for display
$holidays_result = $conn->query("SELECT holiday_name, start_date, end_date, added_at 
                                FROM odd_holiday 
                                ORDER BY start_date DESC");
if ($holidays_result) {
    $holidays = $holidays_result->fetch_all(MYSQLI_ASSOC);
    $holidays_result->free();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Holiday | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
  <style>
    .holiday-form {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .holiday-table {
      margin-top: 30px;
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .table th {
      background-color: #343a40;
      color: white;
    }
    .btn-back {
      margin-bottom: 20px;
    }
    .days-count {
      font-size: 1.2rem;
      font-weight: bold;
      color: #343a40;
      margin-top: 10px;
    }
    .date-input-group {
      position: relative;
    }
    .date-info {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      font-size: 0.9rem;
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

  <!-- Main Content -->
  <div class="container mt-5 pt-5">
    <div class="row">
      <div class="col-12">
        <a href="admin-dashboard.php" class="btn btn-secondary btn-back">
          <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h2>Add Holiday</h2>
        <hr>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-6">
        <div class="holiday-form">
          <form method="POST" action="add-holiday.php" id="holidayForm">
            <div class="form-group">
              <label for="holiday_name">Holiday Name</label>
              <input type="text" class="form-control" id="holiday_name" name="holiday_name" required>
            </div>
            <div class="form-group date-input-group">
              <label for="start_date">Start Date</label>
              <input type="date" class="form-control" id="start_date" name="start_date" required>
              <span class="date-info">Start</span>
            </div>
            <div class="form-group date-input-group">
              <label for="end_date">End Date</label>
              <input type="date" class="form-control" id="end_date" name="end_date" required>
              <span class="date-info">End</span>
            </div>
            <div class="days-count" id="daysCountDisplay" style="display: none;">
              Holiday Duration: <span id="daysCount">0</span> day(s)
            </div>
            <button type="submit" class="btn btn-primary btn-block mt-3">Add Holiday</button>
          </form>
        </div>
      </div>
      <div class="col-md-6">
        <div class="holiday-table">
          <h4>Previously Added Holidays</h4>
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>Holiday Name</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Days</th>
                  <th>Added On</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($holidays)): ?>
                  <tr>
                    <td colspan="5" class="text-center">No holidays added yet</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($holidays as $holiday): ?>
                    <?php 
                      $start = new DateTime($holiday['start_date']);
                      $end = new DateTime($holiday['end_date']);
                      $end->modify('+1 day');
                      $interval = $start->diff($end);
                      $holiday_days = $interval->days;
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($holiday['holiday_name']); ?></td>
                      <td><?php echo date('M d, Y', strtotime($holiday['start_date'])); ?></td>
                      <td><?php echo date('M d, Y', strtotime($holiday['end_date'])); ?></td>
                      <td><?php echo $holiday_days; ?></td>
                      <td><?php echo date('M d, Y', strtotime($holiday['added_at'])); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    $(document).ready(function() {
      // Set today's date as default for date inputs
      const today = new Date().toISOString().split('T')[0];
      $('#start_date').val(today);
      $('#end_date').val(today);
      
      // Calculate and display days count when dates change
      function calculateDays() {
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        
        if (startDate && endDate && startDate <= endDate) {
          const timeDiff = endDate - startDate;
          const daysDiff = Math.floor(timeDiff / (1000 * 60 * 60 * 24)) + 1;
          
          $('#daysCount').text(daysDiff);
          $('#daysCountDisplay').show();
        } else {
          $('#daysCountDisplay').hide();
        }
      }
      
      // Initial calculation
      calculateDays();
      
      // Recalculate when dates change
      $('#start_date, #end_date').on('change', calculateDays);
    });
  </script>
</body>
</html>