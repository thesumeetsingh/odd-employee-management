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

// Get employee name
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$employee_name = $employee['name'];
$stmt->close();

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Validate dates
    if ($start_date > $end_date) {
        $error = "End date cannot be before start date";
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_requests 
                              (employee_id, leave_type, start_date, end_date, reason, status) 
                              VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssss", $employee_id, $leave_type, $start_date, $end_date, $reason);
        
        if ($stmt->execute()) {
            $success = "Leave application submitted successfully!";
        } else {
            $error = "Error submitting leave application: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get previous leave requests
$stmt = $conn->prepare("SELECT * FROM leave_requests 
                       WHERE employee_id = ? 
                       ORDER BY created_at DESC");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$leave_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Status badge classes
$status_classes = [
    'pending' => 'badge-warning',
    'approved' => 'badge-success',
    'rejected' => 'badge-danger'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Apply for Leave | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .leave-card {
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      padding: 30px;
      margin-top: 20px;
    }
    .table-responsive {
      margin-top: 30px;
    }
    .table th {
      background-color: #343a40;
      color: white;
    }
    .form-group.required label:after {
      content: " *";
      color: red;
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

  <!-- Leave Application Content -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-10">
        <div class="leave-card">
          <h2 class="text-center mb-4">Apply for Leave</h2>
          
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
          <?php endif; ?>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>
          
          <!-- Leave Application Form -->
          <form method="POST" action="apply-leave.php">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="leave_type">Leave Type</label>
                  <select class="form-control" id="leave_type" name="leave_type" required>
                    <option value="">Select Leave Type</option>
                    <option value="casual">Casual Leave</option>
                    <option value="medical">Medical Leave</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="start_date">Start Date</label>
                  <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="end_date">End Date</label>
                  <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group required">
                  <label for="reason">Reason</label>
                  <input type="text" class="form-control" id="reason" name="reason" required>
                </div>
              </div>
            </div>
            
            <button type="submit" name="apply_leave" class="btn btn-dark btn-block mt-3">
              <i class="fas fa-paper-plane mr-2"></i> Submit Leave Application
            </button>
          </form>
          
          <!-- Previous Leave Requests -->
          <div class="table-responsive">
            <h4 class="mt-5 mb-3">Your Previous Leave Requests</h4>
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Leave Type</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Days</th>
                  <th>Reason</th>
                  <th>Status</th>
                  <th>Admin Remarks</th>
                  <th>Applied On</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($leave_requests)): ?>
                  <tr>
                    <td colspan="8" class="text-center">No leave requests found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($leave_requests as $request): ?>
                    <tr>
                      <td><?php echo ucfirst($request['leave_type']); ?></td>
                      <td><?php echo date('d M Y', strtotime($request['start_date'])); ?></td>
                      <td><?php echo date('d M Y', strtotime($request['end_date'])); ?></td>
                      <td>
                        <?php 
                          $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1;
                          echo $days;
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars($request['reason']); ?></td>
                      <td>
                        <span class="badge <?php echo $status_classes[$request['status']] ?? 'badge-secondary'; ?>">
                          <?php echo ucfirst($request['status']); ?>
                        </span>
                      </td>
                      <td><?php echo $request['admin_remarks'] ? htmlspecialchars($request['admin_remarks']) : '--'; ?></td>
                      <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
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
    // Set minimum date for date pickers to today
    document.addEventListener('DOMContentLoaded', function() {
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('start_date').min = today;
      document.getElementById('end_date').min = today;
      
      // Update end date min when start date changes
      document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('end_date').min = this.value;
      });
    });
  </script>
</body>
</html>