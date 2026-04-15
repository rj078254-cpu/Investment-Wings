<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$student = null;
$paid_months = [];
$error = $success = "";
$receipt = null;

// Determine which student ID to process from either search or pay action
$search_student_id = '';
if (isset($_POST['search']) || isset($_POST['pay_fee'])) {
    // Get ID from the form submission
    $search_student_id = strtoupper(trim($_POST['student_id']));
}

// Step 1: Search/Load Student Details (Runs if ID is available from any action)
if (!empty($search_student_id)) {
    
    // Use prepared statement for student search
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $search_student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();

        // Get paid months
        $paid = $conn->query("SELECT month_year FROM fees WHERE student_id = {$student['id']} ORDER BY month_year DESC");
        while ($row = $paid->fetch_assoc()) {
            $paid_months[] = $row['month_year'];
        }
    } elseif (isset($_POST['search'])) {
        // Only show student not found error if the search button was explicitly used
        $error = "Student not found: $search_student_id";
    }
    $stmt->close();
}


// Step 2: Pay Fee (Only runs if a student was successfully loaded in Step 1)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_fee']) && $student) {
    $amount = (float)$_POST['amount'];
    $selected_month = $_POST['selected_month'] ?? '';
    // Fetch custom paid date, default to today if not provided
    $paid_date = $_POST['paid_date'] ?? date('Y-m-d'); 

    if (empty($selected_month)) {
        $error = "Please select a month.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0.";
    } elseif (in_array($selected_month, $paid_months)) {
        $error = "Fee for " . date('M Y', strtotime($selected_month . '-01')) . " is already paid.";
    } else {
        $conn->autocommit(FALSE);
        $receipt_no = "REC" . date('Ymd') . rand(100, 999);

        // INSERT FEE - Updated to use the custom $paid_date
        $stmt_fee = $conn->prepare("INSERT INTO fees (student_id, amount, paid_date, month_year, receipt_no) VALUES (?, ?, ?, ?, ?)");
        // Note the bind_param format change: i (int) d (double) s (string) s (string) s (string)
        $stmt_fee->bind_param("idsds", $student['id'], $amount, $paid_date, $selected_month, $receipt_no);
        
        if ($stmt_fee->execute()) {
            $conn->commit();

            // SUCCESS MESSAGE
            $success = "Fee paid successfully! Receipt: <strong>$receipt_no</strong><br><br>
                        <p style='text-align:center;'>
                            <a href='financial_summary.php' style='background:#27ae60; color:white; padding:12px 25px; border-radius:12px; text-decoration:none; font-weight:600;'>
                                View Updated Financial Summary
                            </a>
                        </p>";

            // Generate Receipt Data
            $receipt = [
                'receipt_no' => $receipt_no,
                'student' => $student,
                'amount' => $amount,
                'month' => $selected_month,
                'paid_date' => date('d M Y', strtotime($paid_date)), // Use the selected paid_date
                'total' => $amount
            ];
            
            // FIX: Re-fetch paid months after successful payment for immediate update in the form
            $paid = $conn->query("SELECT month_year FROM fees WHERE student_id = {$student['id']} ORDER BY month_year DESC");
            $paid_months = []; // Reset array
            while ($row = $paid->fetch_assoc()) {
                $paid_months[] = $row['month_year'];
            }

        } else {
            $conn->rollback();
            $error = "Payment failed. Try again: " . $stmt_fee->error;
        }
        $stmt_fee->close();
        $conn->autocommit(TRUE);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* General Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f4f6f9; padding: 20px; }
        .container { max-width: 900px; margin: 30px auto; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .content { padding: 40px; }

        /* Alerts/Messages */
        .alert { padding: 15px; border-radius: 12px; margin: 20px 0; text-align: center; font-weight: 600; font-size: 16px; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        /* Search Form */
        .search-form { background: #f8f9fa; padding: 20px; border-radius: 15px; margin-bottom: 30px; border: 1px solid #eee; }
        .search-grid { display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: end; }
        .search-box input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; text-transform: uppercase; }
        .search-btn { background: #3498db; color: white; padding: 12px 25px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .search-btn:hover { background: #2980b9; }

        /* Student Card */
        .student-card-section {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two main columns: Details and Payment */
            gap: 40px;
            margin-bottom: 30px;
        }
        .details-card {
            background: #ecf0f1; border-radius: 15px; padding: 25px; 
            border: 1px solid #bdc3c7;
        }
        .details-card h3 { color: #34495e; margin-bottom: 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #ccc; font-size: 15px; }
        .detail-row:last-child { border: none; }
        .label { font-weight: 600; color: #555; }
        .value { color: #2c3e50; font-weight: 500; }

        /* Payment Form */
        .pay-form-card {
            background: #fdfefe; border-radius: 15px; padding: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .pay-form-card h3 { color: #27ae60; margin-bottom: 20px; border-bottom: 2px solid #27ae60; padding-bottom: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #34495e; }
        input[type="number"], input[type="month"], input[type="date"] { 
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;
        }
        .fee-receipt-btn {
            display: block; width: 100%; background: #27ae60; color: white; padding: 15px; border: none; border-radius: 8px;
            font-size: 18px; font-weight: 700; cursor: pointer; margin-top: 25px;
            transition: background 0.3s, transform 0.3s;
        }
        .fee-receipt-btn:hover { background: #219653; transform: translateY(-2px); }

        /* Receipt (for Print) */
        .receipt-container { 
            margin-top: 30px; 
            border: 3px dashed #3498db; 
            padding: 30px; 
            border-radius: 15px;
            background: #f4f8fc;
            text-align: center;
        }
        .receipt-box { 
            max-width: 400px; 
            margin: 0 auto; 
            text-align: left; 
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .receipt-box h3 { color: #3498db; margin-bottom: 15px; text-align: center; }
        .receipt-box p { margin: 8px 0; font-size: 15px; }
        .receipt-box strong { font-size: 16px; color: #2c3e50; }
        .final-amount { font-size: 24px !important; color: #27ae60 !important; font-weight: 700; display: block; margin-top: 15px; }

        .print-btn {
            background: #e74c3c; color: white; padding: 12px 25px; border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer; margin-top: 20px; font-size: 16px;
        }
        .print-btn:hover { background: #c0392b; }

        .no-data { text-align: center; padding: 50px; color: #7f8c8d; font-size: 18px; }
        .back-btn { display: block; margin-top: 30px; text-align: center; color: #3498db; text-decoration: none; font-weight: 600; }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .receipt-container, .receipt-container * { visibility: visible; }
            .receipt-container { position: absolute; left: 0; top: 0; border: none; background: white; padding: 0; margin: 0; }
            .print-btn, .back-btn, .alert { display: none; }
            .header, .search-form, .student-card-section, .no-data { display: none; }
            .receipt-box { border: 1px solid black; padding: 15px; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <i class="fas fa-receipt" style="font-size: 30px; margin-right: 10px;"></i>
        <h1>Fee Payment (Fee Receipt)</h1>
        <p>Search by Student ID and Pay Fee</p>
    </div>

    <div class="content">

        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>
        <?php if($success) echo "<div class='alert success'>$success</div>"; ?>

        <form method="post" class="search-form">
            <div class="search-grid">
                <div class="search-box">
                    <input type="text" name="student_id" placeholder="Enter Student ID" required maxlength="10"
                           value="<?php echo $search_student_id; ?>">
                </div>
                <div>
                    <button type="submit" name="search" class="search-btn">
                        <i class="fas fa-search"></i> Search Student
                    </button>
                </div>
            </div>
        </form>

        <?php if ($student): ?>
        
        <div class="student-card-section">
            
            <div class="details-card">
                <h3><i class="fas fa-user-circle"></i> Student Details</h3>
                <div class="detail-row"><span class="label">ID:</span><span class="value"><?php echo $student['student_id']; ?></span></div>
                <div class="detail-row"><span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($student['name']); ?></span></div>
                <div class="detail-row"><span class="label">Father's Name:</span><span class="value"><?php echo htmlspecialchars($student['father_name']); ?></span></div>
                <div class="detail-row"><span class="label">Mobile:</span><span class="value"><?php echo $student['mobile']; ?></span></div>
                <div class="detail-row"><span class="label">Fee Amount:</span><span class="value">₹<?php echo number_format($student['fee_amount'], 2); ?></span></div>
                <div class="detail-row"><span class="label">Last Paid:</span><span class="value"><?php echo !empty($paid_months) ? date('M Y', strtotime($paid_months[0] . '-01')) : 'None'; ?></span></div>
            </div>

            <form method="post" class="pay-form-card">
                <h3><i class="fas fa-hand-holding-usd"></i> Pay Fee</h3>
                <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                
                <div class="form-group">
                    <label>Date Paid</label>
                    <input type="date" name="paid_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Fee Month</label>
                    <input type="month" name="selected_month" required 
                           min="<?php echo date('Y-m', strtotime('-11 months')); ?>" 
                           max="<?php echo date('Y-m'); ?>">
                </div>

                <div class="form-group">
                    <label>Amount (₹)</label>
                    <input type="number" name="amount" value="<?php echo htmlspecialchars($student['fee_amount']); ?>" 
                           min="1" step="0.01" required>
                </div>

                <button type="submit" name="pay_fee" class="fee-receipt-btn">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Receipt & Pay
                </button>
            </form>
        </div>

        <?php if ($receipt): ?>
        <div class="receipt-container">
            <div class="receipt-box">
                <h3 style="border-bottom: 1px solid #ccc; padding-bottom: 10px;">The-Reading-Zone</h3>
                <p><strong>Receipt No:</strong> <?php echo $receipt['receipt_no']; ?></p>
                <p><strong>Date Paid:</strong> <?php echo $receipt['paid_date']; ?></p>
                <hr style="margin: 10px 0;">
                <p><strong>Student:</strong> <?php echo htmlspecialchars($receipt['student']['name']); ?></p>
                <p><strong>ID:</strong> <?php echo $receipt['student']['student_id']; ?></p>
                <p><strong>Month:</strong> <?php echo date('M Y', strtotime($receipt['month'] . '-01')); ?></p>
                <hr style="margin: 10px 0;">
                <p><strong>Amount Paid:</strong> <span class="final-amount">₹<?php echo number_format($receipt['amount'], 2); ?></span></p>
                <p style="text-align: center; font-size: 12px; margin-top: 15px;">** This is a computer-generated receipt **</p>
            </div>
            <button onclick="window.print()" class="print-btn">
                <i class="fas fa-print"></i> Print Receipt
            </button>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-data">
            Please enter Student ID and click the Search button.
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>

    </div>
</div>

</body>
</html>