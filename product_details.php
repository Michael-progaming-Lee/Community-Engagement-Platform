<?php
session_start();
include 'php/config.php';

$username = $_SESSION['username'];
$user_id = $_SESSION['id'];

// Fetch product details
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = "SELECT * FROM product WHERE id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Check if the product exists
if (!$product) {
    die("Product not found");
}

// Check if current user is the product seller
$is_owner = ($product['product_seller'] === $username);

// Create comments table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS product_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
)";
$con->query($create_table_query);

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment_text = trim($_POST['comment_text']);
    if (!empty($comment_text)) {
        $comment_query = "INSERT INTO product_comments (product_id, user_id, username, comment_text) VALUES (?, ?, ?, ?)";
        $comment_stmt = $con->prepare($comment_query);
        $comment_stmt->bind_param("iiss", $product_id, $user_id, $username, $comment_text);
        
        if ($comment_stmt->execute()) {
            echo "<script>window.location.href = window.location.href;</script>";
            exit();
        }
    }
}

// Fetch comments for this product
$comments_query = "SELECT * FROM product_comments WHERE product_id = ? ORDER BY created_at DESC";
$comments_stmt = $con->prepare($comments_query);
$comments_stmt->bind_param("i", $product_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/product_details.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
        }
        .product-image {
            text-align: center;
            background: transparent;
        }
        .product-image img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .product-info {
            background: transparent;
            padding: 25px;
            border-radius: 10px;
        }
        .info-item {
            display: block;
            margin-bottom: 20px;
        }
        .label {
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
            font-size: 16px;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .value {
            display: block;
            color: #333;
            font-size: 15px;
            line-height: 1.6;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .price {
            font-size: 24px;
            color: #2c3e50;
            font-weight: bold;
        }
        .rent-section {
            grid-column: 1 / -1;
            background: transparent;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .rent-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
            font-weight: bold;
            color: #2c3e50;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .form-group input {
            padding: 10px;
            border: 1px solid rgba(221, 221, 221, 0.7);
            background: rgba(255, 255, 255, 0.4);
            border-radius: 5px;
            font-size: 16px;
        }
        .button-group {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            background: rgba(76, 68, 182, 0.808);
            color: white;
        }
        .rent-btn {
            background: rgba(76, 68, 182, 0.808);
            color: white;
        }
        .negotiate-btn {
            background: rgba(76, 68, 182, 0.808);
            color: white;
            text-decoration: none;
        }
        .success {
            color: #1b5e20;
            background: rgba(232, 245, 233, 0.4);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .error {
            color: #b71c1c;
            background: rgba(255, 235, 238, 0.4);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
    </style>
    <title><?php echo htmlspecialchars($product['product_name']); ?></title>
</head>
<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
<?php include("php/header.php"); ?>
    <h1 style="color: #333; text-align: center; margin: 20px 0;"><?php echo htmlspecialchars($product['product_name']); ?></h1>

    <div class="container">
        <!-- Product Image -->
        <div class="product-image">
            <img src="<?php echo htmlspecialchars($product['product_img']); ?>" 
                 alt="<?php echo htmlspecialchars($product['product_name']); ?>">
        </div>
        
        <!-- Product Information -->
        <div class="product-info">
            <span class="info-item">
                <span class="label">Product ID:</span>
                <span class="value"><?php echo htmlspecialchars($product['id']); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Category:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_category']); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Description:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_description']); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Available Quantity:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_quantity']); ?></span>
            </span>

            <span class="info-item">
                <span class="label">Cost:</span>
                <span class="value price">$<?php echo number_format($product['product_cost'], 2); ?></span>
            </span>

            <span class="info-item">
                <span class="label">Seller:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_seller']); ?></span>
            </span>
        </div>

        <?php if ($is_owner): ?>
            <div class="owner-message">
                <p>This is your product listing. You cannot rent your own product.</p>
            </div>
        <?php else: ?>
            <!-- Rent Section -->
            <div class="rent-section">
                <h2>Add to Cart</h2>
                <form method="post" class="rent-form">
                    <div class="form-group">
                        <label for="rent_quantity">Enter Quantity:</label>
                        <input type="number" id="rent_quantity" name="rent_quantity" 
                               min="1" max="<?php echo $product['product_quantity']; ?>" required>
                    </div>
                    <div class="button-group" style="display: flex; gap: 10px;">
                        <button type="submit" class="btn rent-btn">Add to Cart</button>
                        <a href="negotiate-price.php?product_id=<?php echo $product_id; ?>" class="btn negotiate-btn" style="background-color: rgba(76, 68, 182, 0.808); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Negotiate Price</a>
                    </div>
                </form>
            </div>

            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rent_quantity'])) {
                $rent_quantity = isset($_POST['rent_quantity']) ? (int)$_POST['rent_quantity'] : 0;

                if ($rent_quantity > $product['product_quantity']) {
                    echo "<p class='error'>Cannot rent more than available stock.</p>";
                } else {
                    // Calculate total
                    $product_total = $rent_quantity * $product['product_cost'];;

                    // Insert into users_cart
                    $insert_query = "INSERT INTO users_cart (Username, UserID, product_id, product_name, product_category, product_description, product_quantity, product_cost, product_img, product_total)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $con->prepare($insert_query);
                    $stmt->bind_param(
                        "siisssidss",
                        $username,
                        $user_id,
                        $product_id,
                        $product['product_name'],
                        $product['product_category'],
                        $product['product_description'],
                        $rent_quantity,
                        $product['product_cost'],
                        $product['product_img'],
                        $product_total
                    );

                    if ($stmt->execute()) {
                        // Update product quantity
                        $new_quantity = $product['product_quantity'] - $rent_quantity;
                        $update_query = "UPDATE product SET product_quantity = ? WHERE id = ?";
                        $update_stmt = $con->prepare($update_query);
                        $update_stmt->bind_param("ii", $new_quantity, $product_id);
                        $update_stmt->execute();

                        // Mark as out of stock if quantity is 0
                        if ($new_quantity == 0) {
                            $out_of_stock_query = "UPDATE product SET product_description = CONCAT(product_description, ' - OUT OF STOCK') WHERE id = ?";
                            $out_of_stock_stmt = $con->prepare($out_of_stock_query);
                            $out_of_stock_stmt->bind_param("i", $product_id);
                            $out_of_stock_stmt->execute();
                        }

                        echo "<p class='success'>Product rented successfully!</p>";
                    } else {
                        echo "<p class='error'>Error renting product: " . $con->error . "</p>";
                    }
                }
            } ?>
        <?php endif; ?>
    </div>

    <!-- Comments Section - Moved outside the main container -->
    <div class="comments-container">
        <div class="comments-section">
            <h2>Comments</h2>
            
            <!-- Add Comment Form -->
            <form method="post" class="comment-form">
                <div class="form-group">
                    <label for="comment_text">Add a Comment:</label>
                    <textarea name="comment_text" id="comment_text" rows="3" required></textarea>
                </div>
                <button type="submit" name="comment" class="btn comment-btn">Post Comment</button>
            </form>

            <!-- Display Comments -->
            <div class="comments-list">
                <?php if ($comments_result->num_rows > 0): ?>
                    <?php while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span>
                                <span class="comment-date"><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-content">
                                <?php echo htmlspecialchars($comment['comment_text']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-comments">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>