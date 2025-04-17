<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if(!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$current_financial_year = (date('m') > 3) ? date('Y') : date('Y') - 1;
$success_message = '';
$error_message = '';

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $holiday_name = trim($_POST['holiday_name']);
        $holiday_type = $_POST['holiday_type'];
        $from_date = $_POST['from_date'];
        $to_date = ($holiday_type == 'multiple' && !empty($_POST['to_date'])) ? $_POST['to_date'] : $from_date;
        $comments = !empty($_POST['comments']) ? trim($_POST['comments']) : NULL;

        // Validate dates
        if ($to_date < $from_date) {
            throw new Exception("End date must be after start date");
        }

        // Calculate day count
        $date1 = new DateTime($from_date);
        $date2 = new DateTime($to_date);
        $day_count = $date2->diff($date1)->days + 1; // +1 to include both start and end dates

        // Insert holiday
        $stmt = $conn->prepare("INSERT INTO holidays 
                              (holiday_name, from_date, to_date, day_count, financial_year, comments) 
                              VALUES (:holiday_name, :from_date, :to_date, :day_count, :financial_year, :comments)");
        $stmt->bindParam(':holiday_name', $holiday_name);
        $stmt->bindParam(':from_date', $from_date);
        $stmt->bindParam(':to_date', $to_date);
        $stmt->bindParam(':day_count', $day_count);
        $stmt->bindParam(':financial_year', $current_financial_year);
        $stmt->bindParam(':comments', $comments);
        $stmt->execute();

        $success_message = "Holiday added successfully! (Duration: $day_count days)";

    } catch(Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get recently added holidays
$recent_holidays = [];
try {
    $stmt = $conn->prepare("SELECT * FROM holidays 
                           WHERE financial_year = :financial_year 
                           ORDER BY from_date DESC LIMIT 10");
    $stmt->bindParam(':financial_year', $current_financial_year);
    $stmt->execute();
    $recent_holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching holidays: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODD - Add Holiday</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .form-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--primary-color);
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
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background-color: #1a252f;
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
        
        .holiday-type {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .holiday-type label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .recent-holidays {
            margin-top: 40px;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        .recent-holidays h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .holiday-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .holiday-item {
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .holiday-item:last-child {
            border-bottom: none;
        }
        
        .holiday-name {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .holiday-date {
            color: var(--text-color);
            font-size: 0.9rem;
        }
        
        .holiday-comments {
            margin-top: 5px;
            font-size: 0.9rem;
            color: #666;
        }
        
        #to_date_group {
            display: none;
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
            <div class="form-container">
                <h2><i class="fas fa-calendar-plus"></i> Add Holiday</h2>
                
                <?php if($success_message): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="holiday_name">Holiday Name *</label>
                        <input type="text" id="holiday_name" name="holiday_name" required>
                    </div>
                    
                    <div class="holiday-type">
                        <label>
                            <input type="radio" name="holiday_type" value="single" checked> Single Day
                        </label>
                        <label>
                            <input type="radio" name="holiday_type" value="multiple"> Multiple Days
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" id="from_date_group">
                            <label for="from_date">Date *</label>
                            <input type="date" id="from_date" name="from_date" required>
                        </div>
                        
                        <div class="form-group" id="to_date_group">
                            <label for="to_date">To Date *</label>
                            <input type="date" id="to_date" name="to_date">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comments">Comments (Optional)</label>
                        <textarea id="comments" name="comments" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn">Add Holiday</button>
                </form>
                
                <div class="recent-holidays">
    <h3><i class="fas fa-history"></i> Recently Added Holidays</h3>
    <div class="holiday-list">
        <?php if(empty($recent_holidays)): ?>
            <p>No holidays added yet</p>
        <?php else: ?>
            <?php foreach($recent_holidays as $holiday): ?>
                <div class="holiday-item">
                    <div class="holiday-name"><?php echo htmlspecialchars($holiday['holiday_name']); ?></div>
                    <div class="holiday-date">
                        <?php 
                        // Format date display based on whether it's a single or multi-day holiday
                        if ($holiday['day_count'] == 1) {
                            echo date('M d, Y', strtotime($holiday['from_date']));
                        } else {
                            echo date('M d, Y', strtotime($holiday['from_date'])) . ' to ' . 
                                 date('M d, Y', strtotime($holiday['to_date']));
                        }
                        ?>
                        (<?php echo $holiday['day_count']; ?> day<?php echo $holiday['day_count'] > 1 ? 's' : ''; ?>)
                    </div>
                    <?php if(!empty($holiday['comments'])): ?>
                        <div class="holiday-comments"><?php echo htmlspecialchars($holiday['comments']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    <script>
        // Toggle date fields based on holiday type
        document.querySelectorAll('input[name="holiday_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const toDateGroup = document.getElementById('to_date_group');
                if(this.value === 'multiple') {
                    toDateGroup.style.display = 'block';
                    document.getElementById('to_date').required = true;
                } else {
                    toDateGroup.style.display = 'none';
                    document.getElementById('to_date').required = false;
                }
            });
        });

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