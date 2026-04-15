<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$success = $error = $generated_id = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $publisher = trim($_POST['publisher']);
    $quantity = (int)$_POST['quantity'];

    // Validation
    if (empty($title) || empty($author)) {
        $error = "Book Title and Author are required.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } else {
        // Auto Generate Book ID: BK001, BK002...
        $result = $conn->query("SELECT book_id FROM books ORDER BY id DESC LIMIT 1");
        if ($result->num_rows > 0) {
            $last = $result->fetch_assoc()['book_id'];
            $num = (int)substr($last, 2) + 1;
            $generated_id = "BK" . str_pad($num, 3, "0", STR_PAD_LEFT);
        } else {
            $generated_id = "BK001";
        }

        // Insert into books table
        $sql = "INSERT INTO books (book_id, title, author, publisher, quantity) 
                VALUES ('$generated_id', '$title', '$author', '$publisher', $quantity)";

        if ($conn->query($sql) === TRUE) {
            $success = "Book added successfully!";
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
    <title>Add Book | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 15px; opacity: 0.9; }

        .form-container { padding: 40px; }

        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #27ae60;
            font-size: 18px;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 1.5px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            transition: 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.15);
        }

        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
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

        .id-display {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin: 20px 0;
            letter-spacing: 2px;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            color: #27ae60;
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
        <p>Add New Book</p>
    </div>

    <div class="form-container">

        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <?php if($success) echo "<div class='success'>$success</div>"; ?>
        <?php if($generated_id) echo "<div class='id-display'>Generated Book ID: $generated_id</div>"; ?>

        <form method="post">
            <div class="form-group">
                <label>Book Name *</label>
                <div class="input-group">
                    <i class="fas fa-book"></i>
                    <input type="text" name="title" required placeholder="Enter Book Name">
                </div>
            </div>

            <div class="form-group">
                <label>Author Name *</label>
                <div class="input-group">
                    <i class="fas fa-user-edit"></i>
                    <input type="text" name="author" required placeholder="Enter Author Name">
                </div>
            </div>

            <div class="form-group">
                <label>Publisher Name</label>
                <div class="input-group">
                    <i class="fas fa-building"></i>
                    <input type="text" name="publisher" placeholder="Enter Publisher Name">
                </div>
            </div>

            <div class="form-group">
                <label>Quantity *</label>
                <div class="input-group">
                    <i class="fas fa-layer-group"></i>
                    <input type="number" name="quantity" required value="1" min="1">
                </div>
            </div>

            <button type="submit" class="btn">
                Add Book
            </button>
        </form>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>