<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$student = null;
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['search'])) {
        $student_id = strtoupper(trim($_POST['student_id']));

        $sql = "SELECT * FROM students WHERE student_id = '$student_id'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
        } else {
            $error = "No student found with ID: $student_id";
        }
    }

    if (isset($_POST['delete'])) {
        $student_id = $_POST['delete_id'];

        // Delete related issues first
        $conn->query("DELETE FROM issues WHERE student_id = (SELECT id FROM students WHERE student_id = '$student_id')");

        // Delete student
        $sql = "DELETE FROM students WHERE student_id = '$student_id'";
        if ($conn->query($sql) === TRUE) {
            $success = "Student <strong>$student_id</strong> removed successfully!";
            $student = null;
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remove Student | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 15px; opacity: 0.9; }

        .form-container { padding: 40px; }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        .search-box input {
            flex: 1;
            padding: 14px 18px;
            border: 1.5px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            text-transform: uppercase;
        }
        .search-box button {
            padding: 14px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .search-box button:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .student-card {
            background: #f8f9fa;
            border: 2px solid #e74c3c;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            position: relative;
        }
        .student-id {
            position: absolute;
            top: -15px;
            right: 20px;
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed #ddd;
        }
        .detail-row:last-child { border-bottom: none; }
        .label { font-weight: 600; color: #444; }
        .value { color: #2c3e50; }

        .action-buttons {
            text-align: center;
            margin-top: 25px;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-delete:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }

        .success, .error {
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            font-weight: 500;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            color: #c0392b;
            text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>The-Reading-Zone</h1>
        <p>Remove Student by ID</p>
    </div>

    <div class="form-container">

        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <?php if($success) echo "<div class='success'>$success</div>"; ?>

        <form method="post" class="search-box">
            <input type="text" name="student_id" placeholder="Enter Student ID (e.g., TRZ001)" required maxlength="10">
            <button type="submit" name="search">
                Search
            </button>
        </form>

        <?php if ($student): ?>
            <div class="student-card">
                <div class="student-id"><?php echo $student['student_id']; ?></div>

                <div class="detail-row">
                    <span class="label">Name:</span>
                    <span class="value"><?php echo htmlspecialchars($student['name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Father's Name:</span>
                    <span class="value"><?php echo htmlspecialchars($student['father_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Mobile:</span>
                    <span class="value"><?php echo $student['mobile']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Email:</span>
                    <span class="value"><?php echo $student['email'] ?: '—'; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Address:</span>
                    <span class="value"><?php echo nl2br(htmlspecialchars($student['address'])) ?: '—'; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">DOJ:</span>
                    <span class="value"><?php echo $student['dob'] ? date('d M Y', strtotime($student['dob'])) : '—'; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Fee Paid:</span>
                    <span class="value">₹<?php echo number_format($student['fee_amount'], 2); ?></span>
                </div>

                <div class="action-buttons">
                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to PERMANENTLY remove this student?');">
                        <input type="hidden" name="delete_id" value="<?php echo $student['student_id']; ?>">
                        <button type="submit" name="delete" class="btn-delete">
                            Remove Student
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>