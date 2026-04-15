<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$success = $error = $generated_id = "";
$new_student_data = null; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $father_name = trim($_POST['father_name']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address']);
    $dob = $_POST['dob']; 
    $fee_amount = $_POST['fee_amount'];
    $email = trim($_POST['email']);

    // Validation
    if (!preg_match("/^[a-zA-Z ]+$/", $name)) $error = "Name should contain only letters and spaces.";
    elseif (!preg_match("/^[0-9]{10}$/", $mobile)) $error = "Mobile number must be 10 digits.";
    elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Invalid email format.";
    else {
        
        // ***********************************************
        // ** SMART ID GENERATION LOGIC: Reuse Deleted IDs **
        // ***********************************************
        $prefix = "TRZ";
        
        // 1. Find the smallest missing ID number to reuse
        $max_result = $conn->query("SELECT MAX(CAST(SUBSTR(student_id, 4) AS UNSIGNED)) as max_num FROM students");
        $max_num = $max_result->fetch_assoc()['max_num'] ?? 0;
        
        $reused_id_num = null;
        for ($i = 1; $i <= $max_num; $i++) {
            $check_id = $prefix . str_pad($i, 3, "0", STR_PAD_LEFT);
            $check_result = $conn->query("SELECT student_id FROM students WHERE student_id = '$check_id'");
            if ($check_result->num_rows == 0) {
                $reused_id_num = $i;
                break; // Found the smallest gap, use this number
            }
        }

        if ($reused_id_num) {
            $generated_id = $prefix . str_pad($reused_id_num, 3, "0", STR_PAD_LEFT);
        } else {
            // No gap found, generate the next sequential ID
            $generated_id = $prefix . str_pad($max_num + 1, 3, "0", STR_PAD_LEFT);
        }
        // ***********************************************
        // ***********************************************
        
        // Using Prepared Statements for safe insertion
        $stmt = $conn->prepare("INSERT INTO students (name, father_name, mobile, address, dob, fee_amount, email, student_id, roll_no, class) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '')");
        
        $stmt->bind_param("sssssdss", $name, $father_name, $mobile, $address, $dob, $fee_amount, $email, $generated_id);


        if ($stmt->execute()) {
            $success = "Student added successfully! ID: $generated_id";
            
            $new_student_data = [
                'student_id' => $generated_id,
                'name' => htmlspecialchars($name),
                'father_name' => htmlspecialchars($father_name),
                'mobile' => $mobile,
                'address' => htmlspecialchars($address), // Added address to ID card data
                'doj' => date('d M Y', strtotime($dob)), 
                'fee_amount' => number_format($fee_amount, 2)
            ];

        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* General Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: #f4f6f9; 
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #eee;
        }
        .header {
            background: linear-gradient(135deg, #3498db, #2980b9); 
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .header p { font-size: 15px; opacity: 0.9; }

        .form-container {
            padding: 40px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group.full { grid-column: 1 / -1; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e; 
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
            font-size: 18px;
        }
        input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input[type="number"], textarea {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 1.5px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: 0.3s;
            background: #fdfdfd;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
        }
        textarea { height: 100px; resize: vertical; padding-left: 45px; }

        .btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
        }

        /* Messages */
        .success, .error {
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            font-weight: 500;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* ID Card Display */
        .id-card-section {
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-top: 30px;
            border: 1px solid #eee;
            text-align: center;
        }
        .id-card-wrapper {
            display: flex; 
            justify-content: center;
            gap: 15px; /* Reduced gap */
            margin: 0 auto 20px;
        }
        /* Aadhar Card Size: 85.6mm x 53.98mm (Aspect Ratio) */
        .id-card {
            width: 320px; /* ~85mm */
            height: 190px; /* ~54mm (Adjusted for better fit) */
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            text-align: left;
            flex-shrink: 0;
        }
        .id-card-back {
            width: 320px;
            height: 190px;
            background: #ecf0f1; 
            color: #34495e;
            border: 1px solid #bdc3c7;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            text-align: center;
            flex-shrink: 0;
        }
        .id-card-back h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 4px;
        }
        .id-card-back ul {
            list-style: none;
            padding: 0;
            margin: 8px 0 0 0;
            text-align: left;
            font-size: 11px;
        }
        .id-card-back ul li {
            padding: 3px 0;
            border-bottom: 1px dashed #ccc;
        }
        .id-card-back ul li:last-child {
            border-bottom: none;
        }
        /* Front Side Details */
        .id-card h4 {
            font-size: 15px;
            margin-bottom: 4px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            padding-bottom: 4px;
            font-weight: 700;
            text-align: center;
        }
        .id-card .logo-title {
            font-size: 10px;
            font-weight: 400;
            opacity: 0.8;
            margin-bottom: 5px;
            text-align: center;
        }
        .card-photo {
            width: 60px;
            height: 60px;
            background: white;
            border: 3px solid #f1c40f;
            border-radius: 5px;
            float: right;
            margin-left: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            color: #3498db;
        }
        .card-id-display {
            font-size: 13px; 
            font-weight: 600;
            margin-bottom: 5px;
            clear: both;
        }
        .card-details {
            font-size: 12px;
            line-height: 1.4;
            padding-top: 5px;
        }
        .card-details p { margin: 2px 0; }
        .card-details strong { font-weight: 600; font-size: 13px; }
        .card-footer {
            position: absolute;
            bottom: 8px;
            right: 12px;
            font-size: 9px;
            opacity: 0.7;
            text-align: right;
        }
        
        .print-id-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: background 0.3s;
        }
        .print-id-btn:hover { background: #c0392b; }


        .back-btn {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover { text-decoration: underline; }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .id-card-section, .id-card-section * { visibility: hidden; } 
            
            /* Print wrapper positioning */
            .id-card-wrapper {
                visibility: visible;
                position: absolute; 
                left: 50%;
                top: 50px;
                transform: translateX(-50%);
                margin: 0;
                box-shadow: none;
                display: flex; 
                gap: 10px; /* Smaller gap for print */
                page-break-after: always;
            }
            .id-card, .id-card-back, .id-card-wrapper * { 
                visibility: visible;
                position: static;
                box-shadow: none;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            .id-card, .id-card-back {
                border: 1px solid #000; /* Ensure borders show up on print */
            }
            .print-id-btn, .back-btn, .header, .success, .error, .form-container {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <i class="fas fa-user-plus" style="margin-right: 10px;"></i>
        <h1>Add New Student</h1>
        <p>Enter student Details</p>
    </div>

    <div class="form-container">

        <?php if($error) echo "<div class='error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>
        <?php if($success) echo "<div class='success'><i class='fas fa-check-circle'></i> $success</div>"; ?>
        
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label>Student Name *</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" required placeholder=>
                    </div>
                </div>

                <div class="form-group">
                    <label>Father's Name *</label>
                    <div class="input-group">
                        <i class="fas fa-male"></i>
                        <input type="text" name="father_name" required placeholder=>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mobile Number *</label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="mobile" required placeholder=>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email ID</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder=>
                    </div>
                </div>

                <div class="form-group">
                    <label>Date of Join *</label>
                    <div class="input-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="dob" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Fee Amount (₹) *</label>
                    <div class="input-group">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" name="fee_amount" value=min="0" step="1" required>
                    </div>
                </div>

                <div class="form-group full">
                    <label>Full Address</label>
                    <div class="input-group">
                        <i class="fas fa-home"></i>
                        <textarea name="address" placeholder=></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Add Student
            </button>
        </form>

        <?php if($new_student_data): ?>
        <div class="id-card-section">
            <h3 style="color:#2c3e50; margin-bottom: 20px;">Generated Student ID Card (Front & Back)</h3>
            <div class="id-card-wrapper">
                
                <div class="id-card">
                    <div class="card-photo"><i class="fas fa-camera"></i></div>
                    <h4>The-Reading-Zone</h4>
                    <div class="logo-title">Student Identity Card</div>
                    
                    <div class="card-id-display">
                        <span style="color: #f1c40f;">STUDENT ID:</span> 
                        <span style="color: white;"><?php echo $new_student_data['student_id']; ?></span>
                    </div>

                    <div class="card-details">
                        <p>Name: <strong><?php echo $new_student_data['name']; ?></strong></p>
                        <p>Father: <strong><?php echo $new_student_data['father_name']; ?></strong></p>
                        <p>Mobile: <strong><?php echo $new_student_data['mobile']; ?></strong></p>
                        <p>DOJ: <strong><?php echo $new_student_data['doj']; ?></strong></p>
                    </div>
                    
                    <div class="card-footer">
                        Authorised Signature
                    </div>
                </div>

                <div class="id-card-back">
                    <h4><i class="fas fa-info-circle" style="color:#3498db;"></i> Important Rules</h4>
                    <ul>
                        <li><i class="fas fa-book-reader" style="color:#27ae60;"></i> Books must be returned within **07 days**.</li>
                        <li><i class="fas fa-hourglass-half" style="color:#e67e22;"></i> Fine of **₹1 per day** for late returns.</li>
                        <li><i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i> Lost/damaged books must be **replaced**.</li>
                        <li><i class="fas fa-volume-mute" style="color:#95a5a6;"></i> Maintain **silence** in the library premises.</li>
                        <li><i class="fas fa-times-circle" style="color:#f39c12;"></i> This card is **non-transferable**.</li>
                    </ul>
                    <p style="font-size:10px; margin-top:10px; color:#95a5a6;">**The-Reading-Zone, [Your Location/Details]**</p>
                </div>

            </div>
            
            <button onclick="window.print()" class="print-id-btn">
                <i class="fas fa-print"></i> Print ID Card (Front & Back)
            </button>
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>