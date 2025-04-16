<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['loggedin']) ){
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODD - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .dashboard-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .dashboard-card {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .dashboard-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .dashboard-card h3 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #e74c3c;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
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
            <div class="dashboard-header">
                <h2>Admin Dashboard</h2>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            
            <div class="dashboard-grid">
                <a href="add_employee.php" class="dashboard-card">
                    <i class="fas fa-user-plus"></i>
                    <h3>Add Employee</h3>
                    <p>Register new employees to the system</p>
                </a>
                
                <a href="add_holiday.php" class="dashboard-card">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>Add Holiday</h3>
                    <p>Add holidays to the company calendar</p>
                </a>
                
                <a href="holiday_status.php" class="dashboard-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Holiday Status</h3>
                    <p>View employee holiday requests</p>
                </a>
                
                <a href="download_data.php" class="dashboard-card">
                    <i class="fas fa-download"></i>
                    <h3>Download Data</h3>
                    <p>Export employee data and reports</p>
                </a>
                
                <a href="overview.php" class="dashboard-card">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Overview</h3>
                    <p>System statistics and analytics</p>
                </a>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
</body>
</html>