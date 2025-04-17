<?php
session_start();
require_once 'config.php';

// Check if user is already logged in
if(isset($_SESSION['loggedin'])) {
    header("Location: " . ($_SESSION['is_admin'] ? 'admin/admin-dashboard.php' : 'employee-dashboard.php'));
    exit;
}

// Handle form submission
$login_error = '';
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $employeeID = trim($_POST['employeeID']);
    $password = $_POST['password'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM ODDemployee WHERE employeeID = ?");
        $stmt->execute([$employeeID]);
        
        if($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password (admin has plain text password 'adminadmin')
            if(($employeeID === 'admin' && $password === 'adminadmin') || 
               ($employeeID !== 'admin' && password_verify($password, $user['password']))) {
                
                // Set session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['employeeID'] = $user['employeeID'];
                $_SESSION['firstName'] = $user['firstName'];
                $_SESSION['lastName'] = $user['lastName'];
                $_SESSION['is_admin'] = ($user['employeeID'] === 'admin');
                $_SESSION['user_id'] = $user['employeeID'];
                
                // Redirect to appropriate dashboard
                header("Location: " . ($_SESSION['is_admin'] ? 'admin-dashboard.php' : 'employee-dashboard.php'));
                exit;
            } else {
                $login_error = 'Invalid employee ID or password';
            }
        } else {
            $login_error = 'Invalid employee ID or password';
        }
    } catch(PDOException $e) {
        $login_error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODD - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .login-container h2 {
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
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .login-btn:hover {
            background-color: #1a252f;
        }
        
        .error-message {
            color: #e74c3c;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo-container">
                <img src="assets/images/logo.jpg" alt="Organised Design Desk Logo" class="logo">
                <h1>Organised Design Desk</h1>
            </div>
            <div class="header-controls">
                <button id="theme-toggle" aria-label="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
    </header>

    <main>
        <div class="login-container">
            <h2><i class="fas fa-lock"></i> Employee Login</h2>
            
            <?php if($login_error): ?>
                <div class="error-message"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="employeeID">Employee ID *</label>
                    <input type="text" id="employeeID" name="employeeID" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </main>

    <script src="assets/js/script.js"></script>
</body>
</html>