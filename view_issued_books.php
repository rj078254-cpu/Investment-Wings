<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

// Pay Fine
if (isset($_GET['pay_fine'])) {
    $issue_id = (int)$_GET['pay_fine'];
    $conn->query("UPDATE issues SET fine = 0.00 WHERE id = $issue_id");
    $success = "Fine paid successfully! Fine cleared.";
}

// Return Book + Calculate Fine
if (isset($_GET['return'])) {
    $issue_id = (int)$_GET['return'];
    $sql = "SELECT i.*, b.id as book_internal_id FROM issues i JOIN books b ON i.book_id = b.id WHERE i.id = $issue_id AND i.return_date IS NULL";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $qty = $row['quantity'];
        $book_internal_id = $row['book_internal_id'];
        $due_date = $row['due_date'];
        $return_date = date("Y-m-d");
        $late_days = max(0, (strtotime($return_date) - strtotime($due_date)) / 86400);
        $fine = $late_days * 1; // ₹1 per day

        $conn->query("UPDATE issues SET return_date = '$return_date', fine = $fine WHERE id = $issue_id");
        $conn->query("UPDATE books SET quantity = quantity + $qty WHERE id = $book_internal_id");

        $success = "Book returned! Fine: ₹$fine (Late: $late_days days)";
    } else {
        $error = "Invalid or already returned.";
    }
}

// Fetch ALL issued books
$sql = "SELECT 
            i.id as issue_id,
            i.issue_date, i.due_date, i.return_date, i.quantity, COALESCE(i.fine, 0) as fine,
            s.student_id, s.name as student_name,
            b.book_id, b.title
        FROM issues i
        JOIN students s ON i.student_id = s.id
        JOIN books b ON i.book_id = b.id
        ORDER BY i.issue_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>इशू की गई बुक्स + फाइन | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #2c3e50, #34495e); min-height: 100vh; padding: 20px; }
        .container { max-width: 1350px; margin: 30px auto; background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 15px; opacity: 0.9; }

        .content { padding: 40px; }

        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; text-align: center; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table {
            width: 100%; border-collapse: collapse; margin-top: 20px; background: #f8f9fa; border-radius: 12px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        th { background: #e74c3c; color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 14px 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #fdf2e9; transition: 0.3s; }

        .late { background: #fef5e7 !important; }
        .very-late { background: #fce8e6 !important; color: #c0392b; }

        .return-btn {
            background: #3498db; color: white; padding: 8px 16px; border: none; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .return-btn:hover { background: #2980b9; transform: translateY(-2px); }

        .pay-btn {
            background: #27ae60; color: white; padding: 8px 16px; border: none; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .pay-btn:hover { background: #219653; transform: translateY(-2px); }

        .fine { font-weight: bold; }
        .fine.zero { color: #27ae60; }
        .fine.positive { color: #c0392b; }

        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .late-badge { background: #e74c3c; color: white; }
        .on-time { background: #27ae60; color: white; }

        .back-btn {
            display: inline-block; margin-top: 20px; color: #e74c3c; text-decoration: none; font-weight: 600;
        }
        .back-btn:hover { text-decoration: underline; }

        .no-data {
            text-align: center; padding: 50px; color: #7f8c8d; font-size: 18px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>The-Reading-Zone</h1>
        <p>इशू की गई बुक्स – फाइन सिस्टम (₹1/दिन)</p>
    </div>

    <div class="content">

        <?php if(isset($success)) echo "<div class='alert success'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert error'>$error</div>"; ?>

        <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Issue ID</th>
                    <th>Student</th>
                    <th>Book</th>
                    <th>Issue</th>
                    <th>Due</th>
                    <th>Return</th>
                    <th>Qty</th>
                    <th>Late Days</th>
                    <th>Fine</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $is_returned = !is_null($row['return_date']);
                    $due = strtotime($row['due_date']);
                    $return = $is_returned ? strtotime($row['return_date']) : time();
                    $late_days = max(0, floor(($return - $due) / 86400));
                    $fine = $is_returned ? $row['fine'] : ($late_days * 1);
                    $row_class = $late_days > 0 ? ($late_days > 7 ? 'very-late' : 'late') : '';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><strong>#<?php echo $row['issue_id']; ?></strong></td>
                    <td>
                        <strong><?php echo $row['student_id']; ?></strong><br>
                        <small><?php echo htmlspecialchars($row['student_name']); ?></small>
                    </td>
                    <td>
                        <strong><?php echo $row['book_id']; ?></strong><br>
                        <small><?php echo htmlspecialchars($row['title']); ?></small>
                    </td>
                    <td><?php echo date("d M Y", strtotime($row['issue_date'])); ?></td>
                    <td><?php echo date("d M Y", strtotime($row['due_date'])); ?></td>
                    <td>
                        <?php echo $is_returned ? date("d M Y", strtotime($row['return_date'])) : "<span style='color:#e74c3c;'>Pending</span>"; ?>
                    </td>
                    <td><strong><?php echo $row['quantity']; ?></strong></td>
                    <td><strong><?php echo $late_days; ?></strong></td>
                    <td class="fine <?= $fine == 0 ? 'zero' : 'positive' ?>">
                        ₹<?php echo number_format($fine, 2); ?>
                    </td>
                    <td>
                        <?php if ($is_returned): ?>
                            <?php echo $fine > 0 ? "<span class='badge late-badge'>Late</span>" : "<span class='badge on-time'>On Time</span>"; ?>
                        <?php else: ?>
                            <strong style="color:#e67e22;">Not Returned</strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$is_returned): ?>
                            <a href="?return=<?php echo $row['issue_id']; ?>" 
                               class="return-btn" 
                               onclick="return confirm('Return now?\nFine: ₹<?php echo $late_days; ?>')">
                                Return + Fine
                            </a>
                        <?php elseif ($fine > 0): ?>
                            <a href="?pay_fine=<?php echo $row['issue_id']; ?>" class="pay-btn">
                                Pay Fine
                            </a>
                        <?php else: ?>
                            <span style="color:#27ae60; font-weight:600;">Paid</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            कोई रिकॉर्ड नहीं मिला।
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>