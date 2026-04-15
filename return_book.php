<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$error = $success = "";
$issued_records = [];
$search_book_id = "";

// Fine Calculation Function (from view_issued_books.php)
function calculate_fine($due_date) {
    $return_date = date("Y-m-d");
    $late_days = max(0, (strtotime($return_date) - strtotime($due_date)) / 86400);
    return $late_days * 1; // ₹1 per day
}

// 1. Return Book Logic
if (isset($_GET['return'])) {
    $issue_id = (int)$_GET['return'];
    
    // Select relevant issue details and book ID
    $sql = "SELECT i.*, b.id as book_internal_id FROM issues i 
            JOIN books b ON i.book_id = b.id 
            WHERE i.id = ? AND i.return_date IS NULL";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $qty = $row['quantity'];
        $book_internal_id = $row['book_internal_id'];
        $due_date = $row['due_date'];
        $return_date = date("Y-m-d");
        $fine = calculate_fine($due_date);

        // Update issues table: set return_date and calculate fine
        $update_issue_stmt = $conn->prepare("UPDATE issues SET return_date = ?, fine = ? WHERE id = ?");
        $update_issue_stmt->bind_param("sdi", $return_date, $fine, $issue_id);

        if ($update_issue_stmt->execute()) {
            // Update books table: increase quantity
            $update_book_stmt = $conn->prepare("UPDATE books SET quantity = quantity + ? WHERE id = ?");
            $update_book_stmt->bind_param("ii", $qty, $book_internal_id);

            if ($update_book_stmt->execute()) {
                $success = "Book returned successfully! Quantity restored. Fine calculated: ₹" . number_format($fine, 2) . ".";
            } else {
                $error = "Book returned, but stock update failed: " . $update_book_stmt->error;
            }
            $update_book_stmt->close();
        } else {
            $error = "Issue update failed: " . $update_issue_stmt->error;
        }
        $update_issue_stmt->close();
    } else {
        $error = "No pending issue found with ID: $issue_id";
    }
}

// 2. Search Logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_book'])) {
    $search_book_id = strtoupper(trim($_POST['book_id']));
    
    if (empty($search_book_id)) {
        $error = "Please enter a Book ID.";
    } else {
        // Fetch all pending issues for the given book_id
        $sql = "SELECT i.id AS issue_id, i.issue_date, i.due_date, i.quantity,
                       b.book_id, b.title,
                       s.student_id, s.name AS student_name
                FROM issues i
                JOIN books b ON i.book_id = b.id
                JOIN students s ON i.student_id = s.id
                WHERE b.book_id = ? AND i.return_date IS NULL";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $search_book_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $row['fine'] = calculate_fine($row['due_date']);
                $issued_records[] = $row;
            }
        } else {
            $error = "No currently issued copies found for Book ID: $search_book_id";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>बुक रिटर्न करें | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* CSS from your other files can be included or linked here for consistent styling */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #f0f4f8, #c9d6df); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 30px auto; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #e67e22, #d35400); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .form-container { padding: 40px; }

        .search-form { background: #fdf5e6; padding: 25px; border-radius: 10px; margin-bottom: 30px; border: 1px dashed #e67e22; }
        .search-form input[type="text"] { width: 70%; padding: 12px; border: 1px solid #e67e22; border-radius: 8px; font-size: 16px; text-transform: uppercase; }
        .search-form button { width: 25%; background: #e67e22; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .search-form button:hover { background: #d35400; }

        .table-container { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f1f1f1; color: #333; font-weight: 600; }
        .return-btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 6px; font-weight: 500; transition: background 0.3s; display: inline-block; }
        .return-btn:hover { background: #2980b9; }

        .fine-cell { font-weight: 700; color: #e74c3c; }
        .no-data { text-align: center; padding: 30px; background: #f8d7da; color: #721c24; border-radius: 10px; border: 1px solid #f5c6cb; }
        .success, .error { padding: 15px; border-radius: 10px; margin: 20px 0; text-align: center; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-btn { display: block; margin-top: 20px; text-align: center; color: #3498db; text-decoration: none; font-weight: 600; }

    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>बुक रिटर्न करें</h1>
        <p>बुक ID से सर्च करके इशू किए गए रिकॉर्ड देखें और रिटर्न करें</p>
    </div>

    <div class="form-container">

        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <?php if($success) echo "<div class='success'>$success</div>"; ?>

        <form method="post" class="search-form">
            <input type="text" name="book_id" placeholder="बुक ID दर्ज करें (जैसे BK001)" required maxlength="10" value="<?php echo htmlspecialchars($search_book_id); ?>" style="text-transform: uppercase;">
            <button type="submit" name="search_book">
                <i class="fas fa-search"></i> Search Book
            </button>
        </form>

        <?php if (!empty($issued_records)): ?>
        <div class="table-container">
            <h2>Issued Copies for: **<?php echo htmlspecialchars($issued_records[0]['title']); ?> (<?php echo htmlspecialchars($issued_records[0]['book_id']); ?>)**</h2>
            <table>
                <thead>
                    <tr>
                        <th>Issue ID</th>
                        <th>Student Name (ID)</th>
                        <th>Qty</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Fine (₹1/Day)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issued_records as $row): ?>
                    <tr>
                        <td><?php echo $row['issue_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?> (<?php echo $row['student_id']; ?>)</td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['issue_date'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['due_date'])); ?></td>
                        <td class="fine-cell">₹<?php echo number_format($row['fine'], 2); ?></td>
                        <td>
                            <a href="?return=<?php echo $row['issue_id']; ?>&book_id=<?php echo $search_book_id; ?>" 
                               class="return-btn" 
                               onclick="return confirm('क्या आप इस किताब को रिटर्न करना चाहते हैं?\nफाइन: ₹<?php echo number_format($row['fine'], 2); ?>')">
                                <i class="fas fa-arrow-down"></i> Return
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_book'])): ?>
        <div class="no-data">
            कोई पेंडिंग इशू रिकॉर्ड नहीं मिला।
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>