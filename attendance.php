<?php
session_start();
require_once 'config/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Map database values to display values and colors
$status_mapping = [
    'present' => ['code' => 'P', 'color' => 'bg-success', 'description' => 'Present'],
    'absent' => ['code' => 'A', 'color' => 'bg-danger', 'description' => 'Absent'],
    'casual' => ['code' => 'CL', 'color' => 'bg-warning', 'description' => 'Casual Leave'],
    'medical' => ['code' => 'ML', 'color' => 'bg-orange', 'description' => 'Medical Leave'],
    'comp-off' => ['code' => 'CO', 'color' => 'bg-primary', 'description' => 'Compensatory Off'],
    'holiday' => ['code' => 'H', 'color' => 'bg-secondary', 'description' => 'Holiday'],
    'sunday' => ['code' => 'S', 'color' => 'bg-light', 'description' => 'Sunday'],
    'lwp' => ['code' => 'LWP', 'color' => 'bg-pink', 'description' => 'Leave Without Pay'],
    'late' => ['code' => 'LA', 'color' => 'bg-purple', 'description' => 'Late Arrival'],
    'early' => ['code' => 'ED', 'color' => 'bg-purple', 'description' => 'Early Departure']
];

// Get filter parameters
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$employee_name = isset($_GET['employee_name']) ? $_GET['employee_name'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build SQL query with filters
$sql = "SELECT * FROM attendance WHERE 1=1";
$params = [];
$types = '';

if (!empty($employee_id)) {
    $sql .= " AND employee_id LIKE ?";
    $params[] = "%$employee_id%";
    $types .= 's';
}

if (!empty($employee_name)) {
    $sql .= " AND employee_name LIKE ?";
    $params[] = "%$employee_name%";
    $types .= 's';
}

if (!empty($month)) {
    $sql .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $month;
    $types .= 's';
}

if (!empty($status)) {
    $sql .= " AND LOWER(status) = LOWER(?)";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY date DESC, employee_name ASC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$attendance_records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distinct employees for dropdown
$employee_stmt = $conn->prepare("SELECT DISTINCT employee_id, employee_name FROM attendance ORDER BY employee_name");
$employee_stmt->execute();
$employee_result = $employee_stmt->get_result();
$employees = $employee_result->fetch_all(MYSQLI_ASSOC);
$employee_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance Management | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
  <style>
    .bg-orange {
      background-color: #fd7e14 !important;
      color: white;
    }
    .bg-pink {
      background-color: #e83e8c !important;
      color: white;
    }
    .bg-purple {
      background-color: #6f42c1 !important;
      color: white;
    }
    .status-badge {
      display: inline-block;
      padding: 0.25em 0.4em;
      font-size: 75%;
      font-weight: 700;
      line-height: 1;
      text-align: center;
      white-space: nowrap;
      vertical-align: baseline;
      border-radius: 0.25rem;
    }
    .filter-card {
      margin-bottom: 20px;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .table-responsive {
      max-height: 600px;
      overflow-y: auto;
    }
    .table thead th {
      position: sticky;
      top: 0;
      background-color: #f8f9fa;
      z-index: 10;
    }
    .action-buttons .btn {
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
    }
    .text-light-gray {
      color: #6c757d;
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
        <a href="admin-dashboard.php" class="btn btn-outline-dark mr-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mt-5 pt-5">
    <div class="row mb-4">
      <div class="col-12">
        <h2><i class="fas fa-calendar-check mr-2"></i>Attendance Management</h2>
        <hr>
      </div>
    </div>

    <!-- Filters Card -->
    <div class="card filter-card">
      <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-filter mr-2"></i>Filters</h5>
      </div>
      <div class="card-body">
        <form method="get" action="attendance.php">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="employee_id">Employee ID</label>
              <input type="text" class="form-control" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>" placeholder="Enter employee ID">
            </div>
            <div class="form-group col-md-3">
              <label for="employee_name">Employee Name</label>
              <select class="form-control" id="employee_name" name="employee_name">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo htmlspecialchars($emp['employee_name']); ?>" <?php echo ($employee_name == $emp['employee_name']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['employee_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label for="month">Month</label>
              <input type="month" class="form-control" id="month" name="month" value="<?php echo htmlspecialchars($month); ?>">
            </div>
            <div class="form-group col-md-3">
              <label for="status">Status</label>
              <select class="form-control" id="status" name="status">
                <option value="">All Statuses</option>
                <?php foreach ($status_mapping as $db_value => $info): ?>
                  <option value="<?php echo htmlspecialchars($db_value); ?>" <?php echo ($status == $db_value) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($info['code'] . ' - ' . $info['description']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="text-right">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-2"></i>Apply Filters</button>
            <a href="attendance.php" class="btn btn-outline-secondary"><i class="fas fa-sync-alt mr-2"></i>Reset</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Attendance Table -->
    <div class="card">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-table mr-2"></i>Attendance Records</h5>
        <div>
          <span class="badge badge-light">Total Records: <?php echo count($attendance_records); ?></span>
          <button class="btn btn-sm btn-success ml-2" data-toggle="modal" data-target="#exportModal">
            <i class="fas fa-file-export mr-1"></i>Export
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Check-In</th>
                <th>Check-Out</th>
                <th>Total Hours</th>
                <th>Status</th>
                <th>Leave Type</th>
                <th>Comments</th>
                <th>Approved By</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($attendance_records)): ?>
                <tr>
                  <td colspan="11" class="text-center text-muted py-4">No attendance records found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($attendance_records as $record): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(date('d-M-Y', strtotime($record['date']))); ?></td>
                    <td><?php echo htmlspecialchars(date('D', strtotime($record['date']))); ?></td>
                    <td><?php echo htmlspecialchars($record['employee_id']); ?></td>
                    <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                    <td><?php echo !empty($record['check_in']) ? htmlspecialchars(date('h:i A', strtotime($record['check_in']))) : '-'; ?></td>
                    <td><?php echo !empty($record['check_out']) ? htmlspecialchars(date('h:i A', strtotime($record['check_out']))) : '-'; ?></td>
                    <td><?php echo !empty($record['total_hours']) ? htmlspecialchars($record['total_hours']) : '-'; ?></td>
                    <td>
                      <?php 
                        $status_key = strtolower($record['status']);
                        if (isset($status_mapping[$status_key])) {
                          $status_info = $status_mapping[$status_key];
                          echo '<span class="status-badge ' . $status_info['color'] . '" title="' . $status_info['description'] . '">' . 
                               $status_info['code'] . '</span>';
                        } else {
                          echo '<span class="status-badge bg-secondary">' . 
                               htmlspecialchars($record['status']) . '</span>';
                        }
                      ?>
                    </td>
                    <td><?php echo !empty($record['leave_type']) ? htmlspecialchars($record['leave_type']) : '-'; ?></td>
                    <td><?php echo !empty($record['comments']) ? htmlspecialchars($record['comments']) : '-'; ?></td>
                    <td><?php echo !empty($record['approved_by']) ? htmlspecialchars($record['approved_by']) : '-'; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Export Modal -->
  <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exportModalLabel">Export Attendance Data</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="exportForm" method="post" action="export_attendance.php">
            <div class="form-group">
              <label for="exportFormat">Format</label>
              <select class="form-control" id="exportFormat" name="format">
                <option value="csv">CSV</option>
                <option value="excel">Excel</option>
                <option value="pdf">PDF</option>
              </select>
            </div>
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">
            <input type="hidden" name="employee_name" value="<?php echo htmlspecialchars($employee_name); ?>">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" form="exportForm" class="btn btn-primary">Export</button>
        </div>
      </div>
    </div>
  </div>



  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>