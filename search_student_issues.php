<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$student = null;
$issues = [];
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $student_id = strtoupper(trim($_POST['student_id']));

    // Search Student
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();

        // Fetch All Issues for this student
        $stmt2 = $conn->prepare("
            SELECT 
                i.id as issue_id,
                i.issue_date,
                i.due_date,
                i.return_date,
                i.quantity,
                b.book_id,
                b.title,
                DATEDIFF(COALESCE(i.return_date, CURDATE()), i.due_date) as late_days
            FROM issues i
            JOIN books b ON i.book_id = b.id
            WHERE i.student_id = ?
            ORDER BY i.issue_date DESC
        ");
        $stmt2->bind_param("i", $student['id']);
        $stmt2->execute();
        $issues_result = $stmt2->get_result();
        while ($row = $issues_result->fetch_assoc()) {
            $issues[] = $row;
        }
        $stmt2->close();
    } else {
        $error = "Student not found: $student_id";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Student Issues | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #2980b9, #3498db); min-height: 100vh; padding: 20px; }
        .container { max-width: 1100px; margin: 30px auto; background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2980b9, #3498db); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 15px; opacity: 0.9; }

        .content { padding: 40px; }

        .search-form {
            background: #f8f9fa; padding: 25px; border-radius: 15px; margin-bottom: 30px;
            border: 2px dashed #3498db;
        }
        .search-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;
        }
        .search-box input {
            width: 100%; padding: 14px; border: 1.5px solid #ddd; border-radius: 12px; font-size: 16px; text-transform: uppercase;
        }
        .search-btn {
            background: #2c3e50; color: white; padding: 14px 20px; border: none; border-radius: 12px;
            font-weight: 600; cursor: pointer;
        }
        .search-btn:hover { background: #1a252f; }

        .student-card {
            background: #e3f2fd; border-radius: 15px; padding: 20px; margin-bottom: 25px;
            border-left: 6px solid #3498db;
        }
        .student-card h3 { color: #2980b9; margin-bottom: 10px; font-size: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ddd; }
        .detail-row:last-child { border: none; }
        .label { font-weight: 600; color: #444; }
        .value { color: #2c3e50; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #f8f9fa; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        th { background: #3498db; color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 14px 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #e8f4fc; }

        .late { background: #fdf2e9 !important; }
        .very-late { background: #fce8e6 !important; color: #c0392b; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .late-badge { background: #e74c3c; color: white; }
        .on-time { background: #27ae60; color: white; }

        .no-data { text-align: center; padding: 50px; color: #7f8c8d; font-size: 18px; }

        .back-btn {
            display: inline-block; margin-top: 30px; color: #2980b9; text-decoration: none; font-weight: 600;
            padding: 12px 25px; background: #f8f9fa; border-radius: 12px; border: 2px solid #2980b9;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: #2980b9; color: white; text-decoration: none;
        }

        .alert { padding: 15px; border-radius: 12px; margin: 20px 0; text-align: center; font-weight: 500; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>The-Reading-Zone</h1>
        <p>Search Student – View All Issued Books</p>
    </div>

    <div class="content">

        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

        <!-- SEARCH FORM -->
        <form method="post" class="search-form">
            <div class="search-grid">
                <div>
                    <label>Student ID</label>
                    <input type="text" name="student_id" placeholder="TRZ001" required maxlength="10">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" name="search" class="search-btn">
                        Search Student
                    </button>
                </div>
            </div>
        </form>

        <!-- STUDENT DETAILS -->
        <?php if ($student): ?>
        <div class="student-card">
            <h3>Student Details</h3>
            <div class="detail-row"><span class="label">ID:</span><span class="value"><?php echo $student['student_id']; ?></span></div>
            <div class="detail-row"><span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($student['name']); ?></span></div>
            <div class="detail-row"><span class="label">Father:</span><span class="value"><?php echo htmlspecialchars($student['father_name']); ?></span></div>
            <div class="detail-row"><span class="label">Mobile:</span><span class="value"><?php echo $student['mobile']; ?></span></div>
        </div>

        <!-- ISSUES TABLE -->
        <?php if (!empty($issues)): ?>
        <table>
            <thead>
                <tr>
                    <th>Issue ID</th>
                    <th>Book</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Return Date</th>
                    <th>Qty</th>
                    <th>Late Days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $row): 
                    $late_days = $row['late_days'];
                    $is_returned = !is_null($row['return_date']);
                    $row_class = '';
                    $status = '';

                    if ($is_returned) {
                        $status = $late_days > 0 ? "<span class='badge late-badge'>Late $late_days days</span>" : "<span class='badge on-time'>On Time</span>";
                        if ($late_days > 0) $row_class = $late_days > 7 ? 'very-late' : 'late';
                    } else {
                        $status = "<strong>Not Returned</strong>";
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><strong>#<?php echo $row['issue_id']; ?></strong></td>
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
                    <td>
                        <?php echo $late_days > 0 ? "<strong style='color:#c0392b;'>$late_days days</strong>" : ($is_returned ? "0" : "—"); ?>
                    </td>
                    <td><?php echo $status; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            No issued books found for this student.
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-data">
            Enter Student ID and click Search.
        </div>
        <?php endif; ?>

        <!-- BACK TO DASHBOARD BUTTON -->
        <a href="dashboard.php" class="back-btn">
            Back to Dashboard
        </a>

    </div>
</div>

</body>
</html>