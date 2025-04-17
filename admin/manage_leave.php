<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if(!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$current_financial_year = (date('m') > 3) ? date('Y') : date('Y') - 1;
$selected_employee = null;
$employee_leave_stats = null;
$success_message = '';
$error_message = '';

// Get all employees
try {
    $stmt = $conn->prepare("SELECT * FROM employees ORDER BY first_name");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching employees: " . $e->getMessage();
}

// Handle employee selection
if(isset($_GET['employee_id'])) {
    $employee_id = $_GET['employee_id'];
    
    try {
        // Get employee details
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $selected_employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get leave statistics for current financial year
        $stmt = $conn->prepare("SELECT 
            SUM(CASE WHEN leave_type = 'sick' AND status = 'approved' THEN DATEDIFF(to_date, from_date) + 1 ELSE 0 END) as sick_leave_taken,
            SUM(CASE WHEN leave_type = 'casual' AND status = 'approved' THEN DATEDIFF(to_date, from_date) + 1 ELSE 0 END) as casual_leave_taken
            FROM leave_requests 
            WHERE employee_id = ? AND financial_year = ?");
        $stmt->execute([$employee_id, $current_financial_year]);
        $employee_leave_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Initialize if null
        if(!$employee_leave_stats['sick_leave_taken']) $employee_leave_stats['sick_leave_taken'] = 0;
        if(!$employee_leave_stats['casual_leave_taken']) $employee_leave_stats['casual_leave_taken'] = 0;
        
    } catch(PDOException $e) {
        $error_message = "Error fetching employee data: " . $e->getMessage();
    }
}

// Handle leave approval
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_leave'])) {
    $employee_id = $_POST['employee_id'];
    $leave_type = $_POST['leave_type'];
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $comments = $_POST['comments'] ?? '';
    
    try {
        // Calculate leave days
        $date1 = new DateTime($from_date);
        $date2 = new DateTime($to_date);
        $leave_days = $date2->diff($date1)->days + 1;
        
        // Check sick leave limit (6 days)
        if($leave_type == 'sick') {
            $stmt = $conn->prepare("SELECT 
                SUM(DATEDIFF(to_date, from_date) + 1) as total_sick_leave 
                FROM leave_requests 
                WHERE employee_id = ? AND leave_type = 'sick' AND status = 'approved' AND financial_year = ?");
            $stmt->execute([$employee_id, $current_financial_year]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_sick_leave = $result['total_sick_leave'] ?? 0;
            
            if(($total_sick_leave + $leave_days) > 6) {
                $error_message = "Cannot approve. Employee has already used $total_sick_leave of 6 sick leave days.";
            } else {
                $remaining_sick_leave = 6 - ($total_sick_leave + $leave_days);
                $success_message = "Leave approved. Employee has $remaining_sick_leave sick leave days remaining.";
            }
        }
        
        // If no error, approve the leave
        if(empty($error_message)) {
            $stmt = $conn->prepare("INSERT INTO leave_requests 
                (employee_id, leave_type, from_date, to_date, status, comments, financial_year) 
                VALUES (?, ?, ?, ?, 'approved', ?, ?)");
            $stmt->execute([$employee_id, $leave_type, $from_date, $to_date, $comments, $current_financial_year]);
            
            if($stmt->rowCount() > 0 && empty($success_message)) {
                $success_message = "Leave approved successfully!";
            }
        }
        
        // Refresh leave stats
        $stmt = $conn->prepare("SELECT 
            SUM(CASE WHEN leave_type = 'sick' AND status = 'approved' THEN DATEDIFF(to_date, from_date) + 1 ELSE 0 END) as sick_leave_taken,
            SUM(CASE WHEN leave_type = 'casual' AND status = 'approved' THEN DATEDIFF(to_date, from_date) + 1 ELSE 0 END) as casual_leave_taken
            FROM leave_requests 
            WHERE employee_id = ? AND financial_year = ?");
        $stmt->execute([$employee_id, $current_financial_year]);
        $employee_leave_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $error_message = "Error processing leave: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODD - Manage Leave Requests</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .employee-card {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .employee-card:hover {
            transform: translateY(-5px);
        }
        
        .employee-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .employee-card h3 {
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .leave-form {
            margin-top: 40px;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .leave-form h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .submit-btn {
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .submit-btn:hover {
            background-color: #1a252f;
        }
        
        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            flex: 1;
            background-color: var(--bg-color);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card h4 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo-container">
                <img src="../assets/images/logo.jpg" alt="Organised Design Desk Logo" class="logo">
                <h1>Organised Design Desk</h1>
            </div>
            <div class="header-controls">
                <button id="theme-toggle" aria-label="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <h2><i class="fas fa-calendar-check"></i> Manage Leave Requests</h2>
            
            <?php if($error_message): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if($success_message): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <div class="employee-grid">
                <?php foreach($employees as $employee): ?>
                    <a href="manage_leave.php?employee_id=<?php echo $employee['id']; ?>" class="employee-card">
                        <i class="fas fa-user-tie"></i>
                        <h3><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
                        <p><?php echo htmlspecialchars($employee['designation'] ?? 'No designation'); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if($selected_employee): ?>
                <div class="leave-form">
                    <h3>Approve Leave for <?php echo htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']); ?></h3>
                    
                    <?php if($employee_leave_stats): ?>
                        <div class="stats-container">
                            <div class="stat-card">
                                <h4>Sick Leave</h4>
                                <p><?php echo $employee_leave_stats['sick_leave_taken']; ?> of 6 days used</p>
                            </div>
                            <div class="stat-card">
                                <h4>Casual Leave</h4>
                                <p><?php echo $employee_leave_stats['casual_leave_taken']; ?> days used</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="employee_id" value="<?php echo $selected_employee['id']; ?>">
                        
                        <div class="form-group">
                            <label for="leave_type">Leave Type *</label>
                            <select id="leave_type" name="leave_type" required>
                                <option value="sick">Sick/Medical Leave</option>
                                <option value="casual">Casual Leave</option>
                                <option value="comp_off">Compensatory Off</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="from_date">From Date *</label>
                                <input type="date" id="from_date" name="from_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="to_date">To Date *</label>
                                <input type="date" id="to_date" name="to_date" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="comments">Comments (Optional)</label>
                            <textarea id="comments" name="comments" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="approve_leave" class="submit-btn">
                            <i class="fas fa-check"></i> Approve Leave
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    <script>
        // Set minimum date for date inputs to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('from_date').min = today;
        document.getElementById('to_date').min = today;

        // When from date changes, update to date min value
        document.getElementById('from_date').addEventListener('change', function() {
            document.getElementById('to_date').min = this.value;
        });
    </script>
</body>
</html>