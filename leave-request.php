<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$success = '';
$error = '';

// Handle leave request approval/denial
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_remarks = $_POST['admin_remarks'] ?? '';
    $admin_id = $_SESSION['admin_id'];

    // Get the leave request details with employee first and last name
    $stmt = $conn->prepare("SELECT lr.*, e.first_name, e.last_name 
                           FROM leave_requests lr
                           JOIN odd_employee e ON lr.employee_id = e.id
                           WHERE lr.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $leave_request = $result->fetch_assoc();
    $stmt->close();

    if ($leave_request) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // 1. Update the leave_requests table
            $stmt = $conn->prepare("UPDATE leave_requests 
                                  SET status = ?, admin_remarks = ?, is_seen = 1, updated_at = NOW() 
                                  WHERE id = ?");
            $status = ($action == 'approve') ? 'approved' : 'rejected';
            $stmt->bind_param("ssi", $status, $admin_remarks, $request_id);
            $stmt->execute();
            $stmt->close();

            // 2. If approved, update the attendance table for each day of leave
            if ($action == 'approve') {
                $start_date = new DateTime($leave_request['start_date']);
                $end_date = new DateTime($leave_request['end_date']);
                $end_date->modify('+1 day'); // To include the end date in the period
                
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start_date, $interval, $end_date);
                
                // Determine status and leave type based on leave type
                $status_value = '';
                $leave_type_value = '';
                
                switch ($leave_request['leave_type']) {
                    case 'medical':
                        $status_value = 'Medical Leave';
                        $leave_type_value = 'medical';
                        break;
                    case 'casual':
                        $status_value = 'Casual Leave';
                        $leave_type_value = 'casual';
                        break;
                    case 'half_day':
                        $status_value = 'Half Day';
                        $leave_type_value = 'half_day';
                        break;
                    default:
                        $status_value = 'Leave';
                        $leave_type_value = 'other';
                }
                
                    // Prepare insert statement for attendance records
                    $stmt = $conn->prepare("INSERT INTO attendance 
                                          (employee_id, employee_name, date, check_in, check_out, 
                                          total_hours, counted_hours, status, leave_type, 
                                          comments, approved_by, created_at, updated_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                                          ON DUPLICATE KEY UPDATE 
                                          employee_name = VALUES(employee_name),
                                          status = VALUES(status), 
                                          leave_type = VALUES(leave_type), 
                                          comments = VALUES(comments), 
                                          approved_by = VALUES(approved_by), 
                                          updated_at = NOW()");

                    // Create variables for binding
                    $nullTime = null;
                    $zeroHours = 0.00;
                    $employee_full_name = $leave_request['first_name'] . ' ' . $leave_request['last_name'];

                    foreach ($period as $date) {
                        $date_str = $date->format('Y-m-d');
                        // Correct parameter binding - 11 parameters total
                        $stmt->bind_param("ssssddsssss", 
                            $leave_request['employee_id'],  // s
                            $employee_full_name,            // s (employee_name)
                            $date_str,                      // s
                            $nullTime,                      // s (check_in as NULL)
                            $nullTime,                      // s (check_out as NULL)
                            $zeroHours,                     // d (total_hours)
                            $zeroHours,                     // d (counted_hours)
                            $status_value,                  // s
                            $leave_type_value,              // s
                            $leave_request['reason'],       // s
                            $admin_id                       // s
                        );
                        $stmt->execute();
                    }
                    $stmt->close();
            }

            // Commit transaction
            $conn->commit();
            $success = "Leave request has been " . $status . " successfully!";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error processing leave request: " . $e->getMessage();
        }
    } else {
        $error = "Leave request not found!";
    }
}

// Get pending leave requests with employee names
$stmt = $conn->prepare("SELECT lr.*, e.first_name, e.last_name 
                       FROM leave_requests lr
                       JOIN odd_employee e ON lr.employee_id = e.id
                       WHERE lr.status = 'pending'
                       ORDER BY lr.created_at DESC");
$stmt->execute();
$pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get processed leave requests (for history)
$stmt = $conn->prepare("SELECT lr.*, e.first_name, e.last_name 
                       FROM leave_requests lr
                       JOIN odd_employee e ON lr.employee_id = e.id
                       WHERE lr.status != 'pending'
                       ORDER BY lr.updated_at DESC
                       LIMIT 20");
$stmt->execute();
$processed_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Status badge classes
$status_classes = [
    'pending' => 'badge-warning',
    'approved' => 'badge-success',
    'rejected' => 'badge-danger'
];
?>

<!-- REST OF YOUR HTML REMAINS EXACTLY THE SAME -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leave Requests | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
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
    .badge-pending {
      background-color: #ffc107;
      color: #212529;
    }
    .badge-approved {
      background-color: #28a745;
      color: white;
    }
    .badge-rejected {
      background-color: #dc3545;
      color: white;
    }
    .action-buttons .btn {
      margin: 0 5px;
    }
    .nav-tabs .nav-link.active {
      font-weight: bold;
      border-bottom: 3px solid #343a40;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="admin-dashboard.php">
        <img src="assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <div>
        <span class="mr-3">Admin Dashboard</span>
        <a href="logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mt-5 pt-5">
    <div class="row">
      <div class="col-12">
        <h2><i class="fas fa-clipboard-list mr-2"></i>Leave Requests</h2>
        <hr>
        
        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Tabs for Pending/Processed Requests -->
        <ul class="nav nav-tabs" id="leaveTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
              Pending Requests
              <?php if (count($pending_requests) > 0): ?>
                <span class="badge badge-danger"><?php echo count($pending_requests); ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="processed-tab" data-toggle="tab" href="#processed" role="tab">
              Processed Requests
            </a>
          </li>
        </ul>
        
        <div class="tab-content" id="leaveTabsContent">
          <!-- Pending Requests Tab -->
          <div class="tab-pane fade show active" id="pending" role="tabpanel">
            <?php if (empty($pending_requests)): ?>
              <div class="alert alert-info mt-3">No pending leave requests</div>
            <?php else: ?>
              <div class="table-responsive mt-3">
                <table class="table table-bordered table-hover">
                  <thead class="thead-dark">
                    <tr>
                      <th>Employee</th>
                      <th>Leave Type</th>
                      <th>Dates</th>
                      <th>Days</th>
                      <th>Reason</th>
                      <th>Applied On</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pending_requests as $request): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                        <td><?php echo ucfirst($request['leave_type']); ?></td>
                        <td>
                          <?php echo date('d M Y', strtotime($request['start_date'])); ?> - 
                          <?php echo date('d M Y', strtotime($request['end_date'])); ?>
                        </td>
                        <td>
                          <?php 
                            $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1;
                            echo $days;
                          ?>
                        </td>
                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                        <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                        <td class="action-buttons">
                          <form method="POST" action="leave-request.php" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <div class="input-group mb-2">
                              <input type="text" class="form-control form-control-sm" name="admin_remarks" placeholder="Remarks (optional)">
                              <div class="input-group-append">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                              </div>
                            </div>
                          </form>
                          <form method="POST" action="leave-request.php" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <input type="hidden" name="action" value="deny">
                            <div class="input-group">
                              <input type="text" class="form-control form-control-sm" name="admin_remarks" placeholder="Reason for denial" required>
                              <div class="input-group-append">
                                <button type="submit" class="btn btn-danger btn-sm">Deny</button>
                              </div>
                            </div>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Processed Requests Tab -->
          <div class="tab-pane fade" id="processed" role="tabpanel">
            <div class="table-responsive mt-3">
              <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                  <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Admin Remarks</th>
                    <th>Processed On</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($processed_requests)): ?>
                    <tr>
                      <td colspan="8" class="text-center">No processed leave requests</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($processed_requests as $request): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                        <td><?php echo ucfirst($request['leave_type']); ?></td>
                        <td>
                          <?php echo date('d M Y', strtotime($request['start_date'])); ?> - 
                          <?php echo date('d M Y', strtotime($request['end_date'])); ?>
                        </td>
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
                        <td><?php echo date('d M Y', strtotime($request['updated_at'])); ?></td>
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
</body>
</html>