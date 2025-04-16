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

if($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Prepare employee data
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $dob = ($_POST['dob'] === 'N/A' || empty($_POST['dob'])) ? NULL : $_POST['dob'];
        $address = ($_POST['address'] === 'N/A' || empty($_POST['address'])) ? NULL : $_POST['address'];
        $date_of_joining = $_POST['date_of_joining'] ?? '';
        $email = ($_POST['email'] === 'N/A' || empty($_POST['email'])) ? NULL : $_POST['email'];
        $phone = ($_POST['phone'] === 'N/A' || empty($_POST['phone'])) ? NULL : $_POST['phone'];
        $designation = ($_POST['designation'] === 'N/A' || empty($_POST['designation'])) ? NULL : $_POST['designation'];

        // Insert employee data
        $stmt = $conn->prepare("INSERT INTO employees (first_name, last_name, dob, address, date_of_joining, email, phone, designation) 
                              VALUES (:first_name, :last_name, :dob, :address, :date_of_joining, :email, :phone, :designation)");
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':dob', $dob);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':date_of_joining', $date_of_joining);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':designation', $designation);
        $stmt->execute();

        // Get the inserted employee ID
        $employee_id = $conn->lastInsertId();

        // Initialize leave balance for current financial year
        $stmt = $conn->prepare("INSERT INTO leave_balance (employee_id, financial_year) VALUES (:employee_id, :financial_year)");
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':financial_year', $current_financial_year);
        $stmt->execute();

        $success_message = "Employee added successfully!";

    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODD - Add Employee</title>
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
        
        .na-option {
            margin-top: 5px;
            display: flex;
            align-items: center;
        }
        
        .na-option input {
            width: auto;
            margin-right: 8px;
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
                <h2><i class="fas fa-user-plus"></i> Add New Employee</h2>
                
                <?php if($success_message): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob">
                        <div class="na-option">
                            <input type="checkbox" id="dob_na" name="dob_na" value="1">
                            <label for="dob_na">N/A</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_joining">Date of Joining *</label>
                        <input type="date" id="date_of_joining" name="date_of_joining" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"></textarea>
                        <div class="na-option">
                            <input type="checkbox" id="address_na" name="address_na" value="1">
                            <label for="address_na">N/A</label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                            <div class="na-option">
                                <input type="checkbox" id="email_na" name="email_na" value="1">
                                <label for="email_na">N/A</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone">
                            <div class="na-option">
                                <input type="checkbox" id="phone_na" name="phone_na" value="1">
                                <label for="phone_na">N/A</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation">
                        <div class="na-option">
                            <input type="checkbox" id="designation_na" name="designation_na" value="1">
                            <label for="designation_na">N/A</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">Add Employee</button>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    <script>
        // Handle N/A checkboxes
        document.querySelectorAll('[id$="_na"]').forEach(checkbox => {
            const fieldId = checkbox.id.replace('_na', '');
            const field = document.getElementById(fieldId);
            
            checkbox.addEventListener('change', function() {
                if(this.checked) {
                    field.value = 'N/A';
                    field.disabled = true;
                } else {
                    field.value = '';
                    field.disabled = false;
                }
            });
            
            // Initialize fields if N/A is checked on page load
            if(checkbox.checked) {
                field.value = 'N/A';
                field.disabled = true;
            }
        });
    </script>
</body>
</html>