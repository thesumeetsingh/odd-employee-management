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

// Process form submission
$errors = [];
$success = false;
$title = $notice_text = $notice_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $notice_text = trim($_POST['notice_text']);
    $notice_type = $_POST['notice_type'];
    
    // Validate inputs
    if (empty($title)) {
        $errors['title'] = 'Notice title is required';
    } elseif (strlen($title) > 255) {
        $errors['title'] = 'Title cannot exceed 255 characters';
    }
    
    if (empty($notice_text)) {
        $errors['notice_text'] = 'Notice content is required';
    }
    
    if (!in_array($notice_type, ['urgent', 'general', 'important'])) {
        $errors['notice_type'] = 'Invalid notice type';
    }
    
    if (empty($errors)) {
        // Insert notice into database
        $stmt = $conn->prepare("INSERT INTO odd_notice (title, notice_text, notice_type, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $notice_text, $notice_type, $admin_id);
        
        if ($stmt->execute()) {
            $success = true;
            // Clear form fields after successful submission
            $title = $notice_text = $notice_type = '';
        } else {
            $errors['database'] = 'Failed to save notice. Please try again. Error: ' . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Notice | ODD</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
  <style>
    .form-container {
      max-width: 800px;
      margin: 30px auto;
      padding: 30px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    .notice-type-badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
    }
    .urgent { background-color: #ffdddd; color: #d9534f; }
    .important { background-color: #fff3cd; color: #856404; }
    .general { background-color: #d4edda; color: #155724; }
    #charCount {
      font-size: 0.8rem;
      color: #6c757d;
      text-align: right;
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
        <h2><i class="fas fa-bullhorn mr-2"></i>Add New Notice</h2>
        <hr>
        
        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show">
            <strong>Success!</strong> Notice has been published successfully.
            <button type="button" class="close" data-dismiss="alert">
              <span>&times;</span>
            </button>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($errors['database'])): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <strong>Error!</strong> <?php echo $errors['database']; ?>
            <button type="button" class="close" data-dismiss="alert">
              <span>&times;</span>
            </button>
          </div>
        <?php endif; ?>
        
        <div class="form-container">
          <form method="POST" action="add-notice.php">
            <div class="form-group">
              <label for="title">Notice Title *</label>
              <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                     id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required maxlength="255">
              <?php if (isset($errors['title'])): ?>
                <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
              <?php endif; ?>
            </div>
            
            <div class="form-group">
              <label for="notice_type">Notice Type *</label>
              <select class="form-control <?php echo isset($errors['notice_type']) ? 'is-invalid' : ''; ?>" 
                      id="notice_type" name="notice_type" required>
                <option value="general" <?php echo ($notice_type === 'general') ? 'selected' : ''; ?>>General</option>
                <option value="important" <?php echo ($notice_type === 'important') ? 'selected' : ''; ?>>Important</option>
                <option value="urgent" <?php echo ($notice_type === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
              </select>
              <?php if (isset($errors['notice_type'])): ?>
                <div class="invalid-feedback"><?php echo $errors['notice_type']; ?></div>
              <?php endif; ?>
              <small class="form-text text-muted mt-2">
                <span class="notice-type-badge general">General</span> - Regular updates
                <span class="notice-type-badge important ml-2">Important</span> - Requires attention
                <span class="notice-type-badge urgent ml-2">Urgent</span> - Immediate action needed
              </small>
            </div>
            
            <div class="form-group">
              <label for="notice_text">Notice Content *</label>
              <textarea class="form-control <?php echo isset($errors['notice_text']) ? 'is-invalid' : ''; ?>" 
                        id="notice_text" name="notice_text" rows="8" required><?php echo htmlspecialchars($notice_text); ?></textarea>
              <div id="charCount">0 characters</div>
              <?php if (isset($errors['notice_text'])): ?>
                <div class="invalid-feedback"><?php echo $errors['notice_text']; ?></div>
              <?php endif; ?>
            </div>
            
            <div class="form-group mt-4">
              <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane mr-2"></i> Publish Notice
              </button>
              <a href="admin-dashboard.php" class="btn btn-outline-secondary btn-lg ml-2">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    $(document).ready(function() {
      // Character counter for notice text
      const textarea = $('#notice_text');
      const counter = $('#charCount');
      
      textarea.on('input', function() {
        const length = $(this).val().length;
        counter.text(length + ' characters');
      });
      
      // Trigger immediately to show current count
      textarea.trigger('input');
      
      // Highlight selected notice type
      $('#notice_type').change(function() {
        $('.notice-type-badge').removeClass('active');
        $('.' + $(this).val()).addClass('active');
      }).trigger('change');
    });
  </script>
</body>
</html>