<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

// काउंट्स
$students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
$books = $conn->query("SELECT COUNT(*) as total FROM books")->fetch_assoc()['total'];

// इशू काउंट – return_date IS NULL वाले
$issued_result = $conn->query("SELECT COUNT(*) as total FROM issues WHERE return_date IS NULL");
$issued = $issued_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>डैशबोर्ड | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: #f4f6f9;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #764ba2, #667eea);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 {
            font-size: 28px;
            display: inline-block;
        }
        .logout {
            float: right;
            margin-top: 8px;
        }
        .logout a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: 0.3s;
        }
        .logout a:hover { background: rgba(255,255,255,0.3); }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            text-align: center;
            transition: 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .stat-card i {
            font-size: 40px;
            margin-bottom: 15px;
            color: #764ba2;
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            text-decoration: none;
            color: #333;
            transition: 0.3s;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .action-btn i {
            font-size: 36px;
            margin-bottom: 15px;
        }
        .action-btn.add { color: #27ae60; }
        .action-btn.remove { color: #e74c3c; }
        .action-btn.issue { color: #f39c12; }
        .action-btn.return { color: #3498db; }
        .action-btn.view { color: #9b59b6; }
        .action-btn.search { color: #1abc9c; }

        footer {
            text-align: center;
            padding: 30px;
            color: #888;
            font-size: 14px;
            margin-top: 50px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>The-Reading-Zone</h1>
    <div class="logout">
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="stats">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <h3>Total Student</h3>
            <div class="number"><?php echo $students; ?></div>
        </div>
        <div class="stat-card">
            <i class="fas fa-book"></i>
            <h3>Total Book</h3>
            <div class="number"><?php echo $books; ?></div>
        </div>
        <div class="stat-card">
            <i class="fas fa-book-open"></i>
            <h3>Total Issued Book</h3>
            <div class="number"><?php echo $issued; ?></div>
        </div>
    </div>

    <div class="actions">
        <a href="add_student.php" class="action-btn add">
            <i class="fas fa-user-plus"></i>
            Add Student
        </a>
		<a href="edit_student.php" class="action-btn view">
		<i class="fas fa-user-edit"></i>
			Edit Student
        </a>
        <a href="remove_student.php" class="action-btn remove">
            <i class="fas fa-user-minus"></i>
            Remove Student
        </a>
        <a href="add_book.php" class="action-btn add">
            <i class="fas fa-plus-circle"></i>
            Add Book
        </a>
        <a href="remove_book.php" class="action-btn remove">
            <i class="fas fa-trash-alt"></i>
            Remove Book
        </a>
        <a href="issue_book.php" class="action-btn issue">
            <i class="fas fa-arrow-up"></i>
            Issue Book
        </a>
        <a href="return_book.php" class="action-btn return">
            <i class="fas fa-arrow-down"></i>
            Return Book
        </a>

        <a href="view_issued_books.php" class="action-btn view">
            <i class="fas fa-list-alt"></i>
            View Issued Books
        </a>

        <a href="search_student_issues.php" class="action-btn search">
            <i class="fas fa-search"></i>
            Search by Student
        </a>
		<a href="view_books_summary.php" class="action-btn view">
			<i class="fas fa-book-open"></i>
			Book Summary
		</a>
		<a href="fee_receipt.php" class="action-btn add">
			<i class="fas fa-receipt"></i>
			Fee Receipt
		</a>
		<a href="financial_summary.php" class="action-btn view">
			<i class="fas fa-chart-line"></i>
			Financial Summary
		</a>
    </div>

</div>

<footer>
    © 2025 The-Reading-Zone | All Right Reserved
</footer>

</body>
</html>