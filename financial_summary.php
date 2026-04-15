<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$error = $success = "";

// ----------------------------------------------------
// 1. Date Range Setup
// ----------------------------------------------------
// Default: Last 30 days
$start_date = $_REQUEST['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_REQUEST['end'] ?? date('Y-m-d');
$period_name = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));

// ----------------------------------------------------
// 2. Expense Management (ADD/EDIT/DELETE)
// ----------------------------------------------------
$edit_expense = null;
$exp_id = 0;
$exp_description = "";
$exp_amount = "";
$exp_date = date('Y-m-d'); // Default to today

// DELETE Logic
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    
    $success = "Expense deleted successfully!";
    // Redirect to clean the URL
    header("Location: financial_summary.php?start=$start_date&end=$end_date");
    exit();
}

// EDIT Logic - Fetch data for edit form
if (isset($_GET['edit'])) {
    $exp_id = (int)$_GET['edit'];
    
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $exp_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_expense = $edit_result->fetch_assoc();
        $exp_description = $edit_expense['description'];
        $exp_amount = $edit_expense['amount'];
        $exp_date = $edit_expense['expense_date'];
    }
    $stmt->close();
}

// SUBMIT ADD/EDIT Expense
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_expense'])) {
    $exp_description = trim($_POST['description']);
    $exp_amount = (float)$_POST['amount'];
    $exp_date = $_POST['expense_date'];
    $exp_id = (int)($_POST['expense_id'] ?? 0);
    
    if (empty($exp_description) || $exp_amount <= 0) {
        $error = "विवरण और राशि (जो 0 से अधिक हो) आवश्यक है।";
    } else {
        if ($exp_id > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE expenses SET description = ?, amount = ?, expense_date = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $exp_description, $exp_amount, $exp_date, $exp_id);
            $success_msg = "Expense updated successfully!";
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO expenses (description, amount, expense_date) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $exp_description, $exp_amount, $exp_date);
            $success_msg = "Expense recorded successfully!";
        }
        
        if ($stmt->execute()) {
            $success = $success_msg;
            // Redirect to clean the POST data and refresh summary
            header("Location: financial_summary.php?start=$start_date&end=$end_date");
            exit();
        } else {
            $error = "Database Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// 3. Financial Summary Calculation (READ)
// ----------------------------------------------------

// 1. Total Fee Income (from fees table)
$fee_sql = "
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM fees 
    WHERE paid_date BETWEEN ? AND ?
";
$fee_stmt = $conn->prepare($fee_sql);
$fee_stmt->bind_param("ss", $start_date, $end_date);
$fee_stmt->execute();
$total_fee_income = $fee_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$fee_stmt->close();

// 2. Total Fines Collected (Income Source 2)
// NOTE: This assumes that the 'fine' column in the 'issues' table represents the amount charged/collected upon return.
// The WHERE return_date BETWEEN ensures the fine was finalized (book returned) within the date range.
$fine_sql = "
    SELECT COALESCE(SUM(fine), 0) as total 
    FROM issues 
    WHERE return_date BETWEEN ? AND ? AND fine > 0
";
$fine_stmt = $conn->prepare($fine_sql);
$fine_stmt->bind_param("ss", $start_date, $end_date);
$fine_stmt->execute();
$total_fine_income = $fine_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$fine_stmt->close();

// Combine all income sources
$total_income = $total_fee_income + $total_fine_income;

// Total Expenses
$exp_sql = "
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
";
$exp_stmt = $conn->prepare($exp_sql);
$exp_stmt->bind_param("ss", $start_date, $end_date);
$exp_stmt->execute();
$total_expense = $exp_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$exp_stmt->close();

// Profit / Loss
$profit = $total_income - $total_expense;
$profit_class = $profit >= 0 ? 'profit' : 'loss';
$profit_text = $profit >= 0 ? "Profit" : "Loss";

// ----------------------------------------------------
// 4. Expense List for Table Display (READ)
// ----------------------------------------------------
$exp_list_sql = "
    SELECT * FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
    ORDER BY expense_date DESC, id DESC
";
$exp_list_stmt = $conn->prepare($exp_list_sql);
$exp_list_stmt->bind_param("ss", $start_date, $end_date);
$exp_list_stmt->execute();
$exp_list = $exp_list_stmt->get_result();
$exp_list_stmt->close();

?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Summary | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f4f6f9; padding: 20px; }
        .container { max-width: 1200px; margin: 30px auto; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #1abc9c, #16a085); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 30px; margin-bottom: 5px; }
        .content-area { padding: 30px; }

        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card h4 { font-size: 16px; color: #555; margin-bottom: 10px; font-weight: 500; }
        .card .amount { font-size: 32px; font-weight: 700; }
        
        .income { background: #e8f8f5; border: 1px solid #1abc9c; }
        .income .amount { color: #1abc9c; }
        
        .expense { background: #f9ebea; border: 1px solid #e74c3c; }
        .expense .amount { color: #e74c3c; }
        
        .profit { background: #eaf2f8; border: 1px solid #3498db; }
        .loss { background: #fdf2e9; border: 1px solid #e67e22; }
        .profit .amount, .loss .amount { color: #3498db; }
        .loss .amount { color: #e67e22; }


        /* Expense Form */
        .expense-form-section { background: #f8f9fa; padding: 25px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #ddd; }
        .expense-form-section h3 { margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; align-items: end; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 16px; }
        .form-btn { background: #2c3e50; color: white; padding: 11px 20px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; }
        .form-btn:hover { background: #1a252f; }

        /* Date Filter */
        .date-filter-form { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; }

        /* Table Style */
        .table-section h3 { margin-top: 30px; margin-bottom: 15px; color: #34495e; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #ecf0f1; color: #2c3e50; font-weight: 600; }
        td:last-child { text-align: center; }
        
        .action-btn { padding: 6px 10px; text-decoration: none; border-radius: 4px; font-size: 14px; margin: 0 2px; }
        .edit-btn { background: #3498db; color: white; }
        .edit-btn:hover { background: #2980b9; }
        .delete-btn { background: #e74c3c; color: white; }
        .delete-btn:hover { background: #c0392b; }

        /* Messages */
        .success, .error { padding: 15px; border-radius: 10px; margin: 20px 0; text-align: center; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-btn { display: block; margin-top: 20px; text-align: center; color: #3498db; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Financial Summary</h1>
    </div>

    <div class="content-area">
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <?php if($success) echo "<div class='success'>$success</div>"; ?>
        
        <form method="get" class="date-filter-form">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start" value="<?php echo $start_date; ?>" required>
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end" value="<?php echo $end_date; ?>" required>
            </div>
            <div class="form-group">
                <button type="submit" class="form-btn" style="margin-top:20px;">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>

        <div class="summary-grid">
            
            <div class="card income">
                <h4>Total Income (Fee + Fines)</h4>
                <div class="amount">₹<?php echo number_format($total_income, 2); ?></div>
            </div>

            <div class="card expense">
                <h4>Total Expense</h4>
                <div class="amount">₹<?php echo number_format($total_expense, 2); ?></div>
            </div>

            <div class="card <?php echo $profit_class; ?>">
                <h4>शुद्ध <?php echo $profit_text; ?></h4>
                <div class="amount">₹<?php echo number_format(abs($profit), 2); ?></div>
            </div>

        </div>

        <hr style="margin: 30px 0;">

        <div class="expense-form-section">
            <h3><i class="fas fa-money-bill-wave"></i> <?php echo $exp_id > 0 ? 'Edit Exp.' : 'Add New Exp.'; ?></h3>
            <form method="post">
                <input type="hidden" name="expense_id" value="<?php echo $exp_id; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" value="<?php echo htmlspecialchars($exp_description); ?>" required placeholder="Enter Expense Here">
                    </div>
                    <div class="form-group">
                        <label>Amount ₹</label>
                        <input type="number" name="amount" value="<?php echo htmlspecialchars($exp_amount); ?>" min="0.01" step="0.01" required placeholder="1200.00">
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="expense_date" value="<?php echo htmlspecialchars($exp_date); ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit_expense" class="form-btn">
                            <i class="fas fa-save"></i> <?php echo $exp_id > 0 ? 'Update' : 'Add Expense'; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-section">
            <h3><i class="fas fa-receipt"></i>(List of Expenses)</h3>
            <?php if ($exp_list->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount (₹)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($exp = $exp_list->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($exp['expense_date'])); ?></td>
                        <td><?php echo htmlspecialchars($exp['description']); ?></td>
                        <td>₹<?php echo number_format($exp['amount'], 2); ?></td>
                        <td>
                            <a href="?edit=<?php echo $exp['id']; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" 
                               class="action-btn edit-btn" title="Edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?delete=<?php echo $exp['id']; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" 
                               class="action-btn delete-btn" title="Delete"
                               onclick="return confirm('क्या आप इस खर्च को हटाना चाहते हैं?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; color: #7f8c8d; padding: 30px; background: #f0f0f0; border-radius: 8px;">इस अवधि में कोई खर्च दर्ज नहीं किया गया है।</p>
            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>