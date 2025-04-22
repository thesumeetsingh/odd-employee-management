<?php
session_start();
require_once '../config/db_connection.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];

// Get employee details
$stmt = $conn->prepare("SELECT first_name, last_name FROM odd_employee WHERE id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

// Get all notices with view status
$stmt = $conn->prepare("
    SELECT n.*, 
           (nv.viewed_at IS NOT NULL) as is_viewed,
           CONCAT(e.first_name, ' ', e.last_name) as author_name
    FROM odd_notice n
    LEFT JOIN notice_views nv ON n.id = nv.notice_id AND nv.employee_id = ?
    LEFT JOIN odd_employee e ON n.created_by = e.id
    ORDER BY n.created_at DESC
");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mark all notices as viewed when page loads
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach ($notices as $notice) {
        if (!$notice['is_viewed']) {
            $stmt = $conn->prepare("INSERT IGNORE INTO notice_views (notice_id, employee_id) VALUES (?, ?)");
            $stmt->bind_param("is", $notice['id'], $employee_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Company Notices | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="../assets/images/logo.jpg" type="image/jpeg">
  <style>
    .notice-card {
      margin-bottom: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 15px rgba(0,0,0,0.08);
      transition: transform 0.3s ease;
      border-left: 4px solid;
    }
    .notice-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 20px rgba(0,0,0,0.12);
    }
    .notice-urgent {
      border-left-color: #dc3545;
      background-color: rgba(220, 53, 69, 0.05);
    }
    .notice-important {
      border-left-color: #ffc107;
      background-color: rgba(255, 193, 7, 0.05);
    }
    .notice-general {
      border-left-color: #17a2b8;
      background-color: rgba(23, 162, 184, 0.05);
    }
    .notice-badge {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 4px;
      text-transform: uppercase;
    }
    .badge-urgent {
      background-color: #dc3545;
      color: white;
    }
    .badge-important {
      background-color: #ffc107;
      color: #212529;
    }
    .badge-general {
      background-color: #17a2b8;
      color: white;
    }
    .new-indicator {
      position: absolute;
      top: 15px;
      right: 15px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background-color: #28a745;
    }
    .notice-date {
      font-size: 0.85rem;
      color: #6c757d;
    }
    .author-info {
      font-size: 0.9rem;
      color: #495057;
    }
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      background-color: #f8f9fa;
      border-radius: 8px;
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
        <a href="../logout.php" class="btn btn-dark">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mt-5 pt-5">
    <div class="row">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2><i class="fas fa-bullhorn mr-2"></i>Company Notices</h2>
          <a href="employee-dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
          </a>
        </div>
        <hr>
        
        <?php if (empty($notices)): ?>
          <div class="empty-state">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <h4>No Notices Available</h4>
            <p class="text-muted">There are currently no company notices posted.</p>
          </div>
        <?php else: ?>
          <div class="row">
            <?php foreach ($notices as $notice): ?>
              <div class="col-md-6">
                <div class="card notice-card notice-<?php echo $notice['notice_type']; ?>">
                  <div class="card-body position-relative">
                    <?php if (!$notice['is_viewed']): ?>
                      <div class="new-indicator" title="New Notice"></div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <span class="notice-badge badge-<?php echo $notice['notice_type']; ?>">
                        <?php echo ucfirst($notice['notice_type']); ?>
                      </span>
                      <small class="notice-date">
                        <?php echo date('M j, Y \a\t g:i a', strtotime($notice['created_at'])); ?>
                      </small>
                    </div>
                    
                    <h4 class="card-title"><?php echo htmlspecialchars($notice['title']); ?></h4>
                    
                    <div class="card-text mb-3">
                      <?php echo nl2br(htmlspecialchars($notice['notice_text'])); ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="author-info">
                        <i class="fas fa-user-edit mr-1"></i>
                        Posted by: <?php echo htmlspecialchars($notice['author_name']); ?>
                      </small>
                      <?php if ($notice['created_at'] != $notice['updated_at']): ?>
                        <small class="notice-date text-muted">
                          <i class="fas fa-edit mr-1"></i>
                          Updated: <?php echo date('M j, Y \a\t g:i a', strtotime($notice['updated_at'])); ?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    // Simple animation for new notices
    $(document).ready(function() {
      $('.new-indicator').each(function() {
        $(this).css({
          'animation': 'pulse 1.5s infinite',
          'box-shadow': '0 0 0 rgba(40, 167, 69, 0.4)'
        });
      });
    });
  </script>
</body>
</html>