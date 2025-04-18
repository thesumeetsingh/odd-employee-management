<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Constants for leave limits
define('MEDICAL_LEAVE_LIMIT', 6);
define('CASUAL_LEAVE_LIMIT', 12);
define('OTHER_LEAVE_LIMIT', 3);
define('COMPENSATORY_LEAVE_LIMIT', 0); // Adjust as needed

// Get filter parameters
$filter_employee = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Mark new requests as seen when page loads
$conn->query("UPDATE leave_requests SET is_seen = TRUE WHERE is_seen = FALSE");

// Get pending leave requests
$pending_query = "SELECT lr.*, e.first_name, e.last_name 
                 FROM leave_requests lr
                 JOIN odd_employee e ON lr.employee_id = e.id
                 WHERE lr.status = 'pending'
                 ORDER BY lr.created_at DESC";
$pending_requests = $conn->query($pending_query)->fetch_all(MYSQLI_ASSOC);

// Get leave history with filters
$history_query = "SELECT lr.*, e.first_name, e.last_name 
                 FROM leave_requests lr
                 JOIN odd_employee e ON lr.employee_id = e.id
                 WHERE 1=1";

$params = [];
$types = '';

if (!empty($filter_employee)) {
    $history_query .= " AND lr.employee_id = ?";
    $params[] = $filter_employee;
    $types .= 's';
}

if (!empty($filter_month) && !empty($filter_year)) {
    $history_query .= " AND MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?";
    $params[] = $filter_month;
    $params[] = $filter_year;
    $types .= 'ii';
}

if (!empty($filter_status)) {
    $history_query .= " AND lr.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$history_query .= " ORDER BY lr.created_at DESC";

$stmt = $conn->prepare($history_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$leave_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all employees for filter dropdown
$employees = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM odd_employee ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Handle leave status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $admin_remarks = !empty($_POST['admin_remarks']) ? $_POST['admin_remarks'] : null;
    
    // Get the leave request details
    $stmt = $conn->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Calculate leave days
    $start_date = new DateTime($request['start_date']);
    $end_date = new DateTime($request['end_date']);
    $leave_days = $end_date->diff($start_date)->days + 1;
    
    // Check leave balance if approving
    if ($status == 'approved') {
        // Get current leave balance
        $stmt = $conn->prepare("SELECT * FROM leave_balance WHERE employee_id = ?");
        $stmt->bind_param("s", $request['employee_id']);
        $stmt->execute();
        $balance = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$balance) {
            // Initialize balance if not exists
            $conn->query("INSERT INTO leave_balance (employee_id) VALUES ('{$request['employee_id']}')");
            $balance = [
                'medical_leave_taken' => 0,
                'casual_leave_taken' => 0,
                'compensatory_leave_taken' => 0,
                'other_leave_taken' => 0
            ];
        }
        
        // Check leave limits
        $leave_type = $request['leave_type'];
        $limit_exceeded = false;
        $leave_limit = 0;
        
        switch ($leave_type) {
            case 'medical':
                $taken = $balance['medical_leave_taken'] + $leave_days;
                $limit_exceeded = $taken > MEDICAL_LEAVE_LIMIT;
                $leave_limit = MEDICAL_LEAVE_LIMIT;
                break;
            case 'casual':
                $taken = $balance['casual_leave_taken'] + $leave_days;
                $limit_exceeded = $taken > CASUAL_LEAVE_LIMIT;
                $leave_limit = CASUAL_LEAVE_LIMIT;
                break;
            case 'other':
                $taken = $balance['other_leave_taken'] + $leave_days;
                $limit_exceeded = $taken > OTHER_LEAVE_LIMIT;
                $leave_limit = OTHER_LEAVE_LIMIT;
                break;
        }
        
        if ($limit_exceeded) {
            $_SESSION['error'] = "Cannot approve leave. {$leave_type} leave limit ({$leave_limit} days) will be exceeded.";
            header("Location: leave-request.php");
            exit();
        }
    }
    
    // Update leave request status
    $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, admin_remarks = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $admin_remarks, $request_id);
    $stmt->execute();
    $stmt->close();
    
    // If approved, update attendance records and leave balance
    if ($status == 'approved') {
        // Update attendance records for each day of leave
        $current_date = clone $start_date;
        $admin_id = $_SESSION['admin_id'];
        
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            
            // Check if record already exists
            $stmt = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
            $stmt->bind_param("ss", $request['employee_id'], $date_str);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            
            if ($exists) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE attendance SET 
                                      status = 'leave',
                                      leave_type = ?,
                                      comments = ?,
                                      approved_by = ?,
                                      updated_at = NOW()
                                      WHERE employee_id = ? AND date = ?");
                $stmt->bind_param("sssss", $request['leave_type'], $request['reason'], $admin_id, $request['employee_id'], $date_str);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO attendance 
                                      (employee_id, employee_name, date, status, leave_type, comments, approved_by)
                                      VALUES (?, ?, ?, 'leave', ?, ?, ?)");
                $stmt->bind_param("ssssss", $request['employee_id'], $request['first_name'].' '.$request['last_name'], $date_str, 
                                 $request['leave_type'], $request['reason'], $admin_id);
            }
            $stmt->execute();
            $stmt->close();
            
            $current_date->modify('+1 day');
        }
        
        // Update leave balance
        $column = $request['leave_type'].'_leave_taken';
        $stmt = $conn->prepare("UPDATE leave_balance SET {$column} = {$column} + ? WHERE employee_id = ?");
        $stmt->bind_param("is", $leave_days, $request['employee_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Refresh page
    header("Location: leave-request.php");
    exit();
}

// Display any error from previous operation
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);



// Leave type colors
$leave_type_colors = [
    'casual' => 'bg-warning',    // Yellow
    'medical' => 'bg-orange',    // Orange
    'other' => 'bg-pink'        // Pink
];
?>

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
      margin-top: 20px;
    }
    .table th {
      background-color: #343a40;
      color: white;
    }
    .bg-orange {
      background-color: #fd7e14 !important;
      color: white;
    }
    .bg-pink {
      background-color: #e83e8c !important;
      color: white;
    }
    .filter-section {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
    }
    .badge-status {
      font-size: 0.9rem;
      padding: 5px 10px;
    }
    .action-buttons .btn {
      margin: 0 3px;
    }
    .leave-limit-warning {
      background-color: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 10px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="../index.html">
        <img src="assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <div>
        <a href="admin-dashboard.php" class="btn btn-outline-secondary mr-2">Dashboard</a>
        <a href="logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Leave Requests Content -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-12">
        <div class="leave-card">
          <h2 class="text-center mb-4">Leave Requests Management</h2>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>
          
          <!-- Pending Leave Requests -->
          <h4>Pending Requests</h4>
          <?php if (empty($pending_requests)): ?>
            <div class="alert alert-info">No pending leave requests</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
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
                  <?php foreach ($pending_requests as $request): 
                    // Get leave balance for warning
                    if (!$conn || $conn->connect_errno) {
                        die("Database connection failed: " . $conn->connect_error);
                    }
                    
                    $balance_stmt = $conn->prepare("SELECT * FROM leave_balance WHERE employee_id = ?");
                    $balance_stmt->bind_param("s", $request['employee_id']);
                    $balance_stmt->execute();
                    $balance = $balance_stmt->get_result()->fetch_assoc();
                    $balance_stmt->close();
                    
                    if (!$balance) {
                        $balance = [
                            'medical_leave_taken' => 0,
                            'casual_leave_taken' => 0,
                            'compensatory_leave_taken' => 0,
                            'other_leave_taken' => 0
                        ];
                    }
                    
                    $leave_days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1;
                    $limit_warning = '';
                    
                    switch ($request['leave_type']) {
                        case 'medical':
                            $remaining = MEDICAL_LEAVE_LIMIT - $balance['medical_leave_taken'];
                            if ($leave_days > $remaining) {
                                $limit_warning = "Warning: Approving this will exceed medical leave limit ({$remaining} days remaining)";
                            }
                            break;
                        case 'casual':
                            $remaining = CASUAL_LEAVE_LIMIT - $balance['casual_leave_taken'];
                            if ($leave_days > $remaining) {
                                $limit_warning = "Warning: Approving this will exceed casual leave limit ({$remaining} days remaining)";
                            }
                            break;
                        case 'other':
                            $remaining = OTHER_LEAVE_LIMIT - $balance['other_leave_taken'];
                            if ($leave_days > $remaining) {
                                $limit_warning = "Warning: Approving this will exceed other leave limit ({$remaining} days remaining)";
                            }
                            break;
                    }
                  ?>
                    <tr>
                      <td>
                        <?php echo htmlspecialchars($request['employee_id']); ?><br>
                        <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . htmlspecialchars($request['last_name'])); ?></strong>
                      </td>
                      <td>
                        <span class="badge <?php echo $leave_type_colors[$request['leave_type']] ?? 'badge-secondary'; ?>">
                          <?php echo ucfirst($request['leave_type']); ?>
                        </span>
                      </td>
                      <td>
                        <?php echo date('d M Y', strtotime($request['start_date'])); ?> <br>
                        to <br>
                        <?php echo date('d M Y', strtotime($request['end_date'])); ?>
                      </td>
                      <td>
                        <?php echo $leave_days; ?>
                      </td>
                      <td><?php echo htmlspecialchars($request['reason']); ?></td>
                      <td><?php echo date('d M Y h:i A', strtotime($request['created_at'])); ?></td>
                      <td class="action-buttons">
                        <form method="POST" action="leave-request.php">
                          <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                          
                          <?php if (!empty($limit_warning)): ?>
                            <div class="leave-limit-warning mb-2">
                              <i class="fas fa-exclamation-triangle"></i> <?php echo $limit_warning; ?>
                            </div>
                          <?php endif; ?>
                          
                          <div class="form-group">
                            <textarea class="form-control mb-2" name="admin_remarks" placeholder="Admin remarks (optional)" rows="2"></textarea>
                          </div>
                          <div class="d-flex justify-content-between">
                            <button type="submit" name="update_status" value="approved" class="btn btn-success">
                              <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="update_status" value="rejected" class="btn btn-danger">
                              <i class="fas fa-times"></i> Reject
                            </button>
                          </div>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          
          <!-- Leave History with Filters -->
          <h4 class="mt-5">Leave History</h4>
          <div class="filter-section">
            <form method="GET" class="form-inline">
              <div class="form-group mr-3">
                <label for="employee_id" class="mr-2">Employee:</label>
                <select class="form-control" id="employee_id" name="employee_id">
                  <option value="">All Employees</option>
                  <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['id']; ?>" <?php echo $filter_employee == $employee['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($employee['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group mr-3">
                <label for="month" class="mr-2">Month:</label>
                <select class="form-control" id="month" name="month">
                  <option value="">All Months</option>
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $filter_month ? 'selected' : ''; ?>>
                      <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group mr-3">
                <label for="year" class="mr-2">Year:</label>
                <select class="form-control" id="year" name="year">
                  <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>>
                      <?php echo $y; ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group mr-3">
                <label for="status" class="mr-2">Status:</label>
                <select class="form-control" id="status" name="status">
                  <option value="">All Statuses</option>
                  <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                  <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
              </div>
              <button type="submit" class="btn btn-dark mr-2">Filter</button>
              <a href="leave-request.php" class="btn btn-outline-secondary">Reset</a>
            </form>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Leave Type</th>
                  <th>Dates</th>
                  <th>Days</th>
                  <th>Reason</th>
                  <th>Status</th>
                  <th>Admin Remarks</th>
                  <th>Applied On</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($leave_history)): ?>
                  <tr>
                    <td colspan="9" class="text-center">No leave requests found with current filters</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($leave_history as $request): ?>
                    <tr>
                      <td>
                        <?php echo htmlspecialchars($request['employee_id']); ?><br>
                        <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . htmlspecialchars($request['last_name'])); ?></strong>
                      </td>
                      <td>
                        <span class="badge <?php echo $leave_type_colors[$request['leave_type']] ?? 'badge-secondary'; ?>">
                          <?php echo ucfirst($request['leave_type']); ?>
                        </span>
                      </td>
                      <td>
                        <?php echo date('d M Y', strtotime($request['start_date'])); ?> <br>
                        to <br>
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
                        <span class="badge 
                          <?php echo $request['status'] == 'approved' ? 'badge-success' : 
                                 ($request['status'] == 'rejected' ? 'badge-danger' : 'badge-warning'); ?>">
                          <?php echo ucfirst($request['status']); ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($request['admin_remarks'] ?: '--'); ?></td>
                      <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                      <td class="action-buttons">
                        <form method="POST" action="leave-request.php">
                          <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                          <div class="form-group">
                            <textarea class="form-control mb-2" name="admin_remarks" placeholder="Admin remarks" rows="2"><?php echo htmlspecialchars($request['admin_remarks']); ?></textarea>
                          </div>
                          <div class="d-flex justify-content-between">
                            <button type="submit" name="update_status" value="approved" class="btn btn-success btn-sm">
                              <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="update_status" value="pending" class="btn btn-warning btn-sm">
                              <i class="fas fa-clock"></i> Pending
                            </button>
                            <button type="submit" name="update_status" value="rejected" class="btn btn-danger btn-sm">
                              <i class="fas fa-times"></i> Reject
                            </button>
                          </div>
                        </form>
                      </td>
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



  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php
// Close DB connection after everything
$conn->close();
?>