<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
include 'db_connect.php';

$book = null;
$error = $success = "";
$search_book_id = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Search Logic
    if (isset($_POST['search'])) {
        $search_book_id = strtoupper(trim($_POST['book_id']));
        
        $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
        $stmt->bind_param("s", $search_book_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $book = $result->fetch_assoc();
        } else {
            $error = "No book found with ID: $search_book_id";
        }
        $stmt->close();
    }
    
    // 2. Remove Full Book Logic
    if (isset($_POST['remove_full_book']) && isset($_POST['book_internal_id'])) {
        $book_internal_id = (int)$_POST['book_internal_id'];
        $book_id_to_delete = $_POST['book_id_to_delete'];

        // Check if the book is currently issued (return_date IS NULL)
        $check_issue = $conn->query("SELECT COUNT(*) as total FROM issues WHERE book_id = $book_internal_id AND return_date IS NULL")->fetch_assoc()['total'];

        if ($check_issue > 0) {
            $error = "Cannot remove! <strong>$check_issue</strong> cop(y/ies) of this book are currently issued and must be returned first.";
        } else {
            // Delete book record
            $sql = "DELETE FROM books WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $book_internal_id);

            if ($stmt->execute()) {
                $success = "Book **$book_id_to_delete** and all its associated data removed successfully!";
                $book = null; // Hide form
            } else {
                $error = "Error removing book: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // 3. Decrease Quantity Logic
    if (isset($_POST['decrease_quantity']) && isset($_POST['book_internal_id'])) {
        $book_internal_id = (int)$_POST['book_internal_id'];
        $qty_to_remove = (int)$_POST['qty_to_remove'];
        $current_qty = (int)$_POST['current_quantity'];

        if ($qty_to_remove <= 0) {
            $error = "Quantity to remove must be greater than 0.";
        } elseif ($qty_to_remove >= $current_qty) {
            $error = "The quantity to remove **($qty_to_remove)** is greater than or equal to the current stock **($current_qty)**. Use the 'Remove Full Book' option instead if you want to remove all.";
        } else {
            // Update quantity
            $new_qty = $current_qty - $qty_to_remove;
            $sql = "UPDATE books SET quantity = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_qty, $book_internal_id);

            if ($stmt->execute()) {
                $success = "Successfully decreased book quantity by **$qty_to_remove**. New stock: **$new_qty**.";
                // Re-fetch book data to display updated quantity
                $result = $conn->query("SELECT * FROM books WHERE id = $book_internal_id");
                $book = $result->fetch_assoc();
            } else {
                $error = "Error updating quantity: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remove Book | The-Reading-Zone</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #f0f4f8, #c9d6df); min-height: 100vh; padding: 20px; }
        .container { max-width: 800px; margin: 30px auto; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 25px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .form-container { padding: 40px; }

        .search-form { background: #fbecec; padding: 25px; border-radius: 10px; margin-bottom: 30px; border: 1px dashed #e74c3c; display: flex; gap: 15px; align-items: center; }
        .search-form input[type="text"] { flex-grow: 1; padding: 12px; border: 1px solid #e74c3c; border-radius: 8px; font-size: 16px; text-transform: uppercase; }
        .search-form button { background: #c0392b; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .search-form button:hover { background: #a93226; }

        .detail-card { background: #fff3f3; border-radius: 15px; padding: 30px; border: 1px solid #e74c3c; margin-bottom: 30px; }
        .detail-card h3 { color: #c0392b; margin-bottom: 20px; font-size: 24px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #e74c3c; }
        .detail-row:last-child { border: none; }
        .label { font-weight: 600; color: #333; }
        .value { color: #c0392b; font-weight: 700; }
        
        .action-area { display: flex; gap: 20px; margin-top: 30px; }
        .action-area > div { flex: 1; padding: 20px; border-radius: 10px; border: 1px solid #ccc; }

        .remove-full-box { background: #fde0df; border-left: 5px solid #c0392b; }
        .remove-full-box h4 { color: #c0392b; margin-bottom: 15px; }
        .remove-full-btn { background: #e74c3c; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 10px; }
        .remove-full-btn:hover { background: #c0392b; }

        .decrease-qty-box { background: #e9f7ef; border-left: 5px solid #27ae60; }
        .decrease-qty-box h4 { color: #27ae60; margin-bottom: 15px; }
        .decrease-qty-box input[type="number"] { width: 100%; padding: 10px; border: 1px solid #27ae60; border-radius: 6px; margin-bottom: 10px; }
        .decrease-qty-btn { background: #2ecc71; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 10px; }
        .decrease-qty-btn:hover { background: #27ae60; }


        .success, .error { padding: 15px; border-radius: 10px; margin: 20px 0; text-align: center; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-btn { display: block; margin-top: 20px; text-align: center; color: #3498db; text-decoration: none; font-weight: 600; }
        
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>The-Reading-Zone</h1>
        <p>Remove Book System</p>
    </div>

    <div class="form-container">

        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <?php if($success) echo "<div class='success'>$success</div>"; ?>

        <form method="post" class="search-form">
            <input type="text" name="book_id" placeholder="Enter Book ID Heare" required maxlength="10" value="<?php echo htmlspecialchars($search_book_id); ?>">
            <button type="submit" name="search">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
        
        <?php if ($book): ?>
        <div class="detail-card">
            <h3>Book Details</h3>
            <div class="detail-row"><span class="label">ID:</span><span class="value"><?php echo $book['book_id']; ?></span></div>
            <div class="detail-row"><span class="label">Title:</span><span class="value"><?php echo htmlspecialchars($book['title']); ?></span></div>
            <div class="detail-row"><span class="label">Author:</span><span class="value"><?php echo htmlspecialchars($book['author']); ?></span></div>
            <div class="detail-row"><span class="label">Publisher:</span><span class="value"><?php echo htmlspecialchars($book['publisher'] ?: 'N/A'); ?></span></div>
            <div class="detail-row"><span class="label">Current Stock:</span><span class="value"><?php echo $book['quantity']; ?> copies</span></div>

            <div class="action-area">
                
                <div class="remove-full-box">
                    <h4><i class="fas fa-trash-alt"></i> Remove all Quantity	</h4>
                    <p style="font-size:14px; margin-bottom:10px;">यह बुक और इससे संबंधित सभी डेटाबेस रिकॉर्ड (जैसे इशू रिकॉर्ड्स) को स्थायी रूप से हटा देगा। **स्टॉक 0 होना चाहिए।**</p>
                    <form method="post" onsubmit="return confirm('WARNING: Are you sure you want to PERMANENTLY remove the book: <?php echo htmlspecialchars($book['title']); ?> (<?php echo $book['book_id']; ?>)?');">
                        <input type="hidden" name="book_internal_id" value="<?php echo $book['id']; ?>">
                        <input type="hidden" name="book_id_to_delete" value="<?php echo $book['book_id']; ?>">
                        <button type="submit" name="remove_full_book" class="remove-full-btn">
                            Remove Full Book
                        </button>
                    </form>
                </div>

                <div class="decrease-qty-box">
                    <h4><i class="fas fa-minus-circle"></i> Less Stock Quantity</h4>
                    <p style="font-size:14px; margin-bottom:10px;">यदि कुछ प्रतियाँ खराब हो गई हैं, तो स्टॉक से उनकी मात्रा कम करें।</p>
                    <form method="post">
                        <input type="hidden" name="book_internal_id" value="<?php echo $book['id']; ?>">
                        <input type="hidden" name="current_quantity" value="<?php echo $book['quantity']; ?>">
                        <label for="qty_to_remove">Quantity to Remove (Max: <?php echo $book['quantity'] - 1; ?>)</label>
                        <input type="number" name="qty_to_remove" id="qty_to_remove" min="1" max="<?php echo $book['quantity'] - 1; ?>" required placeholder="Enter Quantity">
                        <button type="submit" name="decrease_quantity" class="decrease-qty-btn">
                            Decrease Stock
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

</body>
</html>