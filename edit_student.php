<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$student = null;
$error = $success = "";
$student_id_search = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Handle Search
    if (isset($_POST['search'])) {
        $student_id_search = strtoupper(trim($_POST['student_id_search']));

        $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $student_id_search);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
        } else {
            $error = "No student found with ID: " . htmlspecialchars($student_id_search);
        }
        $stmt->close();
    }
    
    // 2. Handle Update
    if (isset($_POST['update_student'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $father_name = trim($_POST['father_name']);
        $mobile = trim($_POST['mobile']);
        $address = trim($_POST['address']);
        $dob = $_POST['dob'];
        $fee_amount = $_POST['fee_amount'];
        $email = trim($_POST['email']);
        
        // Validation (simplified)
        if (!preg_match("/^[a-zA-Z ]+$/", $name)) $error = "Name should contain only letters and spaces.";
        elseif (!preg_match("/^[0-9]{10}$/", $mobile)) $error = "Mobile number must be 10 digits.";
        elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Invalid email format.";
        else {
            // Update the record
            $stmt = $conn->prepare("UPDATE students SET name=?, father_name=?, mobile=?, address=?, dob=?, fee_amount=?, email=? WHERE id=?");
            $stmt->bind_param("sssssdsi", $name, $father_name, $mobile, $address, $dob, $fee_amount, $email, $id);

            if ($stmt->execute()) {
                $success = "Student details updated successfully!";
                // Re-fetch the updated student data
                $stmt_fetch = $conn->prepare("SELECT * FROM students WHERE id = ?");
                $stmt_fetch->bind_param("i", $id);
                $stmt_fetch->execute();
                $student = $stmt_fetch->get_result()->fetch_assoc();
                $stmt_fetch->close();
            } else {
                $error = "Error updating record: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* General Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f4f6f9; min-height: 100vh; padding: 20px; }
        .container {
            max-width: 700px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #eee;
        }
        .header {
            background: linear-gradient(135deg, #f39c12, #e67e22); /* Orange Gradient */
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 8px; }

        .content-container { padding: 30px; }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f39c12;
            padding-bottom: 20px;
        }
        .search-form input[type="text"] {
            flex-grow: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        .search-form button {
            background: #2980b9;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        .search-form button:hover { background: #3498db; }

        /* Update Form */
        .update-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #34495e; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        textarea { resize: vertical; height: 80px; }

        .btn-update {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3); }

        /* Messages */
        .success, .error {
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
            font-weight: 500;
            text-align: center;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover { text-decoration: underline; }

        .student-info {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <i class="fas fa-user-edit" style="margin-right: 10px;"></i>
        <h1>Edit Student Details</h1>
        <p>Search for a student and update their information.</p>
    </div>

    <div class="content-container">

        <?php if($error) echo "<div class='error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>
        <?php if($success) echo "<div class='success'><i class='fas fa-check-circle'></i> $success</div>"; ?>
        
        <form method="post" class="search-form">
            <input type="text" name="student_id_search" 
                   placeholder="Enter Student ID (e.g., TRZ001)" 
                   value="<?php echo htmlspecialchars($student_id_search); ?>" required>
            <button type="submit" name="search">
                <i class="fas fa-search"></i> Search
            </button>
        </form>

        <?php if ($student): ?>
        <div class="student-info">
            Editing Student: <strong><?php echo htmlspecialchars($student['name']); ?></strong> (ID: <?php echo $student['student_id']; ?>)
        </div>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
            <input type="hidden" name="student_id_search" value="<?php echo $student['student_id']; ?>">
            
            <div class="update-form-grid">
                <div class="form-group">
                    <label>Student Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Father's Name *</label>
                    <input type="text" name="father_name" value="<?php echo htmlspecialchars($student['father_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Mobile Number *</label>
                    <input type="tel" name="mobile" value="<?php echo htmlspecialchars($student['mobile']); ?>" required maxlength="10">
                </div>
                <div class="form-group">
                    <label>Email ID</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>">
                </div>
                <div class="form-group">
                    <label>Date of Join *</label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($student['dob']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Fee Amount (₹) *</label>
                    <input type="number" name="fee_amount" value="<?php echo htmlspecialchars($student['fee_amount']); ?>" min="0" step="1" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Full Address</label>
                    <textarea name="address"><?php echo htmlspecialchars($student['address']); ?></textarea>
                </div>
            </div>

            <button type="submit" name="update_student" class="btn-update">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>