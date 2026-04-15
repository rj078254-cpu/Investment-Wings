<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$search = '';
$books = [];

// Excel Export Logic
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Book_Summary_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // BOM for Hindi support in Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Header row
    fputcsv($output, ['Book ID', 'Title', 'Author', 'प्रकाशन', 'Quantity']);

    $sql = "SELECT * FROM books";
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search = $conn->real_escape_string(trim($_GET['search']));
        $sql .= " WHERE book_id LIKE '%$search%' 
                   OR title LIKE '%$search%' 
                   OR author LIKE '%$search%' 
                   OR publisher LIKE '%$search%'";
    }
    $sql .= " ORDER BY book_id ASC";

    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['book_id'],
            $row['title'],
            $row['author'],
            $row['publisher'] ?? 'N/A',
            $row['quantity']
        ]);
    }
    fclose($output);
    exit();
}

// Normal View Logic
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $search = $conn->real_escape_string($search);

    $sql = "SELECT * FROM books 
            WHERE book_id LIKE '%$search%' 
               OR title LIKE '%$search%' 
               OR author LIKE '%$search%' 
               OR publisher LIKE '%$search%' 
            ORDER BY book_id ASC";
} else {
    $sql = "SELECT * FROM books ORDER BY book_id ASC";
}

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Summary | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #2c3e50, #34495e); min-height: 100vh; padding: 20px; }
        .container { max-width: 1300px; margin: 30px auto; background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2980b9, #3498db); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 15px; opacity: 0.9; }

        .content { padding: 40px; }

        .search-form {
            background: #f8f9fa; padding: 25px; border-radius: 15px; margin-bottom: 30px;
            border: 2px dashed #3498db;
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }
        .search-box {
            flex: 1; min-width: 250px;
        }
        .search-box input {
            width: 100%; padding: 14px; border: 1.5px solid #ddd; border-radius: 12px; font-size: 16px;
        }
        .search-btn {
            background: #2c3e50; color: white; padding: 14px 25px; border: none; border-radius: 12px;
            font-weight: 600; cursor: pointer; white-space: nowrap;
        }
        .search-btn:hover { background: #1a252f; }

        .export-btn {
            background: #27ae60; color: white; padding: 14px 25px; border: none; border-radius: 12px;
            font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block;
            white-space: nowrap;
        }
        .export-btn:hover { background: #219653; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #f8f9fa; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        th { background: #3498db; color: white; padding: 16px; text-align: left; font-weight: 600; }
        td { padding: 14px 16px; border-bottom: 1px solid #eee; }
        tr:hover { background: #e8f4fc; transition: 0.3s; }

        .qty-low { background: #fdf2e9 !important; }
        .qty-zero { background: #fce8e6 !important; color: #c0392b; }

        .back-btn {
            display: inline-block; margin-top: 30px; color: #2980b9; text-decoration: none; font-weight: 600;
            padding: 12px 25px; background: #f8f9fa; border-radius: 12px; border: 2px solid #2980b9;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: #2980b9; color: white; text-decoration: none;
        }

        .no-data { text-align: center; padding: 50px; color: #7f8c8d; font-size: 18px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>The-Reading-Zone</h1>
        <p>Book Summary</p>
    </div>

    <div class="content">

        <!-- SEARCH + EXPORT FORM -->
        <form method="get" class="search-form">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search here" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="search-btn">
                Search
            </button>
            <a href="?export=excel<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="export-btn" onclick="return confirm('Export to Excel?')">
                Export to Excel
            </a>
        </form>

        <!-- BOOKS TABLE -->
        <?php if (!empty($books)): ?>
        <table>
            <thead>
                <tr>
                    <th>Book ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Publisher</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): 
                    $qty = $book['quantity'];
                    $row_class = '';
                    if ($qty == 0) $row_class = 'qty-zero';
                    elseif ($qty <= 2) $row_class = 'qty-low';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><strong><?php echo $book['book_id']; ?></strong></td>
                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                    <td><?php echo htmlspecialchars($book['publisher'] ?? 'N/A'); ?></td>
                    <td>
                        <strong class="<?php echo $qty == 0 ? 'text-danger' : ($qty <= 2 ? 'text-warning' : ''); ?>">
                            <?php echo $qty; ?> copy(ies)
                        </strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            No books found.
        </div>
        <?php endif; ?>

        <!-- BACK TO DASHBOARD -->
        <a href="dashboard.php" class="back-btn">
            Back to Dashboard
        </a>

    </div>
</div>

</body>
</html>