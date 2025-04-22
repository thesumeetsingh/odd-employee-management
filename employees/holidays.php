<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

// Get current financial year (April to March)
$current_month = date('n');
$current_year = date('Y');
$financial_year_start = ($current_month >= 4) ? $current_year : $current_year - 1;
$financial_year_end = $financial_year_start + 1;

// Format dates for SQL query
$fy_start_date = "$financial_year_start-04-01";
$fy_end_date = "$financial_year_end-03-31";

// Get holidays for current financial year
$stmt = $conn->prepare("SELECT holiday_name, start_date, end_date 
                       FROM odd_holiday 
                       WHERE start_date >= ? AND end_date <= ?
                       ORDER BY start_date ASC");
$stmt->bind_param("ss", $fy_start_date, $fy_end_date);
$stmt->execute();
$result = $stmt->get_result();
$holidays = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get employee details for greeting
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
  <title>Company Holidays | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .holiday-container {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
    .holiday-card {
      border-left: 4px solid #343a40;
      margin-bottom: 15px;
      transition: transform 0.3s ease;
    }
    .holiday-card:hover {
      transform: translateX(5px);
    }
    .holiday-date {
      font-weight: bold;
      color: #343a40;
    }
    .holiday-name {
      font-size: 1.1rem;
    }
    .financial-year-badge {
      background-color: #343a40;
      color: white;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.9rem;
    }
    .month-section {
      margin-bottom: 30px;
    }
    .month-header {
      border-bottom: 2px solid #343a40;
      padding-bottom: 5px;
      margin-bottom: 15px;
      color: #343a40;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="index.html">
        <img src="../assets/images/logo.jpg" alt="Logo" class="logo-img mr-2">
        <strong style="font-size:30px;">Organised Design Desk</strong>
      </a>
      <div>
        <span class="mr-3">Welcome, <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></span>
        <a href="logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mt-5 pt-5">
    <div class="row">
      <div class="col-12">
        <a href="employee-dashboard.php" class="btn btn-secondary mb-3">
          <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h2>Company Holidays</h2>
        <div class="financial-year-badge d-inline-block mb-3">
          Financial Year: <?php echo "April $financial_year_start - March $financial_year_end"; ?>
        </div>
        <hr>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="holiday-container">
          <?php if (empty($holidays)): ?>
            <div class="alert alert-info">No holidays scheduled for this financial year.</div>
          <?php else: ?>
            <?php
            // Group holidays by month
            $monthly_holidays = [];
            foreach ($holidays as $holiday) {
                $month = date('F Y', strtotime($holiday['start_date']));
                $monthly_holidays[$month][] = $holiday;
            }
            
            // Display holidays by month
            foreach ($monthly_holidays as $month => $month_holidays): ?>
              <div class="month-section">
                <h4 class="month-header"><?php echo $month; ?></h4>
                
                <?php foreach ($month_holidays as $holiday): ?>
                  <div class="card holiday-card mb-2">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <h5 class="holiday-name mb-1"><?php echo htmlspecialchars($holiday['holiday_name']); ?></h5>
                          <div class="holiday-date">
                            <?php
                            $start_date = date('M j', strtotime($holiday['start_date']));
                            $end_date = date('M j', strtotime($holiday['end_date']));
                            
                            if ($holiday['start_date'] == $holiday['end_date']) {
                                echo $start_date;
                            } else {
                                echo "$start_date - $end_date";
                            }
                            
                            // Show duration if more than 1 day
                            $start = new DateTime($holiday['start_date']);
                            $end = new DateTime($holiday['end_date']);
                            $end->modify('+1 day');
                            $interval = $start->diff($end);
                            $days = $interval->days;
                            
                            if ($days > 1) {
                                echo " <span class='text-muted'>($days days)</span>";
                            }
                            ?>
                          </div>
                        </div>
                        <div>
                          <span class="badge badge-success">Holiday</span>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
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