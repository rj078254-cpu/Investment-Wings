<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
include 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$student = null;
$book = null;
$error = $success = "";
$issue_date = date("Y-m-d");
$due_date = date("Y-m-d", strtotime("+14 days"));

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $student_id = strtoupper(trim($_POST['student_id']));
    $book_id = strtoupper(trim($_POST['book_id']));

    // Search Student
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    } else {
        $error = "Student not found: $student_id";
    }
    $stmt->close();

    // Search Book
    $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->bind_param("s", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
        if ($book['quantity'] <= 0) {
            $error = "Book OUT OF STOCK!";
            $book = null;
        }
    } else {
        $error = "Book not found: $book_id";
    }
    $stmt->close();
}

// FIX START: $student && $book की निर्भरता को हटाकर IDs से re-fetch किया गया
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['issue_book'])) {
    // Hidden fields से internal IDs और quantity प्राप्त करें
    $s_id = (int)($_POST['s_id'] ?? 0); 
    $b_id = (int)($_POST['b_id'] ?? 0); 
    $qty = (int)$_POST['issue_qty'];
    $issue_date = $_POST['issue_date'];
    $due_date = $_POST['due_date'];

    // 1. Student details को Re-fetch करें (केवल नाम के लिए)
    $student_q = $conn->prepare("SELECT name FROM students WHERE id = ?");
    $student_q->bind_param("i", $s_id);
    $student_q->execute();
    $student_details = $student_q->get_result()->fetch_assoc();
    $student_q->close();

    // 2. Book details को Re-fetch करें (title और quantity के लिए)
    $book_q = $conn->prepare("SELECT title, quantity FROM books WHERE id = ?");
    $book_q->bind_param("i", $b_id);
    $book_q->execute();
    $book_details = $book_q->get_result()->fetch_assoc();
    $book_q->close();

    // 3. Validation
    if (!$student_details || !$book_details) {
        $error = "Issue failed: Student or Book record not found (Invalid Internal IDs).";
    } elseif ($qty < 1 || $qty > $book_details['quantity']) {
        // Re-fetched book quantity का उपयोग करके validate करें
        $error = "Invalid quantity! Available: " . $book_details['quantity'];
    } else {
        // 4. INSERT into issues
        $stmt = $conn->prepare("INSERT INTO issues (student_id, book_id, issue_date, due_date, quantity, fine, return_date) VALUES (?, ?, ?, ?, ?, 0.00, NULL)");
        $stmt->bind_param("iissi", $s_id, $b_id, $issue_date, $due_date, $qty);
        
        if ($stmt->execute()) {
            // 5. UPDATE books quantity
            $update_stmt = $conn->prepare("UPDATE books SET quantity = quantity - ? WHERE id = ?");
            $update_stmt->bind_param("ii", $qty, $b_id);
            
            if ($update_stmt->execute()) {
                $success = "<strong>$qty</strong> copy(ies) of <strong>" . htmlspecialchars($book_details['title']) . "</strong> issued to <strong>" . htmlspecialchars($student_details['name']) . "</strong>!<br>
                            Due Date: <strong>" . date("d M Y", strtotime($due_date)) . "</strong>";
                // $student और $book को null सेट करें ताकि इशू फॉर्म छिप जाए
                $student = $book = null;
            } else {
                $error = "Stock update failed: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $error = "Issue failed: " . $stmt->error;
        }
        $stmt->close();
    }
}
// FIX END
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>बुक इशू करें | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #3498db, #2980b9); min-height: 100vh; padding: 20px; }
        .container { max-width: 1100px; margin: 30px auto; background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2980b9, #3498db); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 15px; opacity: 0.9; }

        .form-container { padding: 40px; }

        .search-form {
            background: #f8f9fa; padding: 25px; border-radius: 15px; margin-bottom: 30px;
            border: 2px dashed #3498db;
        }
        .search-grid {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; align-items: end;
        }
        .search-box input {
            width: 100%; padding: 14px; border: 1.5px solid #ddd; border-radius: 12px; font-size: 16px; text-transform: uppercase;
        }
        .search-btn {
            background: #2c3e50; color: white; padding: 14px 20px; border: none; border-radius: 12px;
            font-weight: 600; cursor: pointer;
        }
        .search-btn:hover { background: #1a252f; }

        .details-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin: 30px 0;
        }
        .detail-card {
            background: #f8f9fa; border-radius: 15px; padding: 25px;
            border-left: 6px solid #3498db; box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .detail-card h3 { color: #2980b9; margin-bottom: 15px; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #ddd; }
        .detail-row:last-child { border: none; }
        .label { font-weight: 600; color: #444; }
        .value { color: #2c3e50; }

        .issue-form {
            background: #e3f2fd; padding: 30px; border-radius: 15px; margin-top: 30px;
            border: 2px dashed #27ae60;
        }
        .form-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;
        }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: 600; color: #34495e; }
        input, select { padding: 12px; border: 1.5px solid #ddd; border-radius: 10px; font-size: 16px; }

        .issue-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 16px 40px;
            border: none; border-radius: 12px; font-size: 18px; font-weight: 700; cursor: pointer;
            width: 100%; margin-top: 15px;
        }
        .issue-btn:hover {
            transform: translateY(-3px); box-shadow: 0 10px 25px rgba(39,174,96,0.3);
        }

        .success, .error {
            padding: 15px; border-radius: 12px; margin: 20px 0; text-align: center; font-weight: 500;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .back-btn {
            display: inline-block; margin-top: 20px; color: #2980b9; text-decoration: none; font-weight: 600;
        }
        .back-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>The-Reading-Zone</h1>
        <p>बुक इशू करें – स्टॉक कम होगा!</p>
    </div>

    <div class="form-container">

        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <?php if($success) echo "<div class='success'>$success</div>"; ?>

        <form method="post" class="search-form">
            <div class="search-grid">
                <div>
                    <label>Student ID</label>
                    <input type="text" name="student_id" placeholder="TRZ001" required maxlength="10">
                </div>
                <div>
                    <label>Book ID</label>
                    <input type="text" name="book_id" placeholder="BK001" required maxlength="10">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" name="search" class="search-btn">
                        Search
                    </button>
                </div>
            </div>
        </form>

        <?php if ($student || $book): ?>
        <div class="details-grid">
            <?php if ($student): ?>
            <div class="detail-card">
                <h3>Student Details</h3>
                <div class="detail-row"><span class="label">ID:</span><span class="value"><?php echo $student['student_id']; ?></span></div>
                <div class="detail-row"><span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($student['name']); ?></span></div>
                <div class="detail-row"><span class="label">Father:</span><span class="value"><?php echo htmlspecialchars($student['father_name']); ?></span></div>
                <div class="detail-row"><span class="label">Mobile:</span><span class="value"><?php echo $student['mobile']; ?></span></div>
            </div>
            <?php endif; ?>

            <?php if ($book): ?>
            <div class="detail-card">
                <h3>Book Details</h3>
                <div class="detail-row"><span class="label">ID:</span><span class="value"><?php echo $book['book_id']; ?></span></div>
                <div class="detail-row"><span class="label">Title:</span><span class="value"><?php echo htmlspecialchars($book['title']); ?></span></div>
                <div class="detail-row"><span class="label">Author:</span><span class="value"><?php echo htmlspecialchars($book['author']); ?></span></div>
                <div class="detail-row"><span class="label">Available:</span><span class="value"><strong><?php echo $book['quantity']; ?> copies</strong></span></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($student && $book): ?>
        <form method="post" class="issue-form">
            <input type="hidden" name="s_id" value="<?php echo $student['id']; ?>">
            <input type="hidden" name="b_id" value="<?php echo $book['id']; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" value="<?php echo $issue_date; ?>" required>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?php echo $due_date; ?>" required>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <select name="issue_qty" required>
                        <?php for($i=1; $i<=$book['quantity']; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> copy(ies)</option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <button type="submit" name="issue_book" class="issue-btn">
                Issue Book Now
            </button>
        </form>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>