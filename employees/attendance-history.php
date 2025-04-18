<?php
session_start();
require_once '../config/db_connection.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_name = '';

// Get employee name
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$employee_name = $employee['name'];
$stmt->close();

// Default to current month/year
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get attendance records for selected month/year
$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));

$stmt = $conn->prepare("SELECT * FROM attendance 
                       WHERE employee_id = ? 
                       AND date BETWEEN ? AND ?
                       ORDER BY date DESC");
$stmt->bind_param("sss", $employee_id, $start_date, $end_date);
$stmt->execute();
$attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Status badge classes
$status_classes = [
    'present' => 'badge-success',
    'half_day' => 'badge-info',
    'pending' => 'badge-warning',
    'leave' => 'badge-primary'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance History | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .history-card {
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
    .month-selector {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="../../index.html">
        <img src="../assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <div>
        <span class="mr-3"><?php echo htmlspecialchars($employee_name); ?></span>
        <a href="../logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Attendance History Content -->
  <div class="container mt-5 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-10">
        <div class="history-card">
          <h2 class="text-center mb-4">Attendance History</h2>
          
          <!-- Month/Year Selector -->
          <div class="month-selector">
            <form method="GET" class="form-inline justify-content-center">
              <div class="form-group mx-2">
                <label for="month" class="mr-2">Month:</label>
                <select class="form-control" id="month" name="month">
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                      <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group mx-2">
                <label for="year" class="mr-2">Year:</label>
                <select class="form-control" id="year" name="year">
                  <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                      <?php echo $y; ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-dark ml-2">View</button>
            </form>
          </div>
          
          <!-- Attendance Records Table -->
          <div class="table-responsive">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Check In</th>
                  <th>Check Out</th>
                  <th>Total Hours</th>
                  <th>Counted Hours</th>
                  <th>Status</th>
                  <th>Comments</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($attendance_records)): ?>
                  <tr>
                    <td colspan="7" class="text-center">No attendance records found for selected period</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($attendance_records as $record): ?>
                    <tr>
                      <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                      <td><?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '--:-- --'; ?></td>
                      <td><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '--:-- --'; ?></td>
                      <td><?php echo $record['total_hours'] ? number_format($record['total_hours'], 2) : '--'; ?></td>
                      <td><?php echo $record['counted_hours'] ? number_format($record['counted_hours'], 2) : '--'; ?></td>
                      <td>
                        <span class="badge <?php echo $status_classes[$record['status']] ?? 'badge-secondary'; ?>">
                          <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                        </span>
                      </td>
                      <td><?php echo $record['comments'] ? htmlspecialchars($record['comments']) : '--'; ?></td>
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
</body>
</html>