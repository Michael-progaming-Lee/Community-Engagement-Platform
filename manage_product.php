<?php
session_start();
include("php/config.php");

if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

$id = $_SESSION['id'];
$query = mysqli_query($con, "SELECT * FROM users WHERE Id=$id");
$result = mysqli_fetch_assoc($query);
$username = $result['Username'];

// Initialize error variable
$error = "";
$product = null; // Variable to store product details if found

// Get products for this user only
$products_query = "SELECT * FROM product WHERE product_seller = ?";
$stmt = $con->prepare($products_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$products_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/manage_product.css">
    <title>Manage Your Products</title>
</head>
<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <?php include("php/header.php"); ?>
    <h1 style="color: #333; text-align: center; margin: 20px 0;">Manage Products</h1>

    <div class="container" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
        <!-- Display user's current products -->
        <div class="current-products">
            <h2>Your Products</h2>
            <?php if ($products_result->num_rows > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product_row = $products_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product_row['id']); ?></td>
                                <td>
                                    <img src="<?php echo htmlspecialchars($product_row['product_img']); ?>" 
                                         alt="<?php echo htmlspecialchars($product_row['product_name']); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                </td>
                                <td><?php echo htmlspecialchars($product_row['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product_row['product_category']); ?></td>
                                <td><?php echo htmlspecialchars($product_row['product_description']); ?></td>
                                <td><?php echo htmlspecialchars($product_row['product_quantity']); ?></td>
                                <td>$<?php echo number_format($product_row['product_cost'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You haven't added any products yet. <a href="addproduct.php">Add your first product</a></p>
            <?php endif; ?>
        </div>

        <hr class="section-divider">

        <!-- Update or Delete Product Form -->
        <div class="manage-form">
            <h2>Update or Delete Product</h2>
            <p>Enter the ID of the product you want to modify from your products list above:</p>
            
            <form method="post">
                <div class="form-group">
                    <label for="product_id">Product ID:</label>
                    <input type="number" id="product_id" name="product_id" required>
                </div>

                <div class="form-group">
                    <label for="action">Select Action:</label>
                    <select id="action" name="action" required>
                        <option value="">--Choose Action--</option>
                        <option value="update">Update Product</option>
                        <option value="delete">Delete Product</option>
                    </select>
                </div>

                <!-- Update Fields -->
                <div id="update-fields" style="display: none;">
                    <div class="form-group">
                        <label for="product_name">Product Name:</label>
                        <input type="text" id="product_name" name="product_name">
                    </div>

                    <div class="form-group">
                        <label for="product_description">Product Description:</label>
                        <textarea id="product_description" name="product_description" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="product_quantity">Product Quantity:</label>
                        <input type="number" id="product_quantity" name="product_quantity" min="0">
                    </div>

                    <div class="form-group">
                        <label for="product_cost">Product Cost:</label>
                        <input type="number" step="0.01" id="product_cost" name="product_cost" min="0">
                    </div>
                </div>

                <button type="submit" class="btn">Submit</button>
            </form>

            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </div>

        <script>
            // Show/hide update fields based on the selected action
            document.getElementById('action').addEventListener('change', function() {
                const updateFields = document.getElementById('update-fields');
                if (this.value === 'update') {
                    updateFields.style.display = 'block';
                } else {
                    updateFields.style.display = 'none';
                }
            });
        </script>

        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $action = isset($_POST['action']) ? $_POST['action'] : '';

            // Check if product ID exists in the database and belongs to the current user
            $query = "SELECT * FROM product WHERE id = ? AND product_seller = ?";
            $stmt = $con->prepare($query);
            $stmt->bind_param("is", $product_id, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();

                if ($action == 'update') {
                    // Update logic
                    $product_name = $_POST['product_name'];
                    $product_description = $_POST['product_description'];
                    $product_quantity = (int)$_POST['product_quantity'];
                    $product_cost = (float)$_POST['product_cost'];

                    $update_query = "UPDATE product 
                                   SET product_name = ?,
                                       product_description = ?,
                                       product_quantity = ?,
                                       product_cost = ?
                                   WHERE id = ? AND product_seller = ?";
                    $update_stmt = $con->prepare($update_query);
                    $update_stmt->bind_param("ssidis", 
                        $product_name,
                        $product_description,
                        $product_quantity,
                        $product_cost,
                        $product_id,
                        $username
                    );

                    if ($update_stmt->execute()) {
                        echo "<p class='success'>Product updated successfully.</p>";
                        // Refresh the page to show updated data
                        echo "<script>window.location.href = 'manage_product.php';</script>";
                    } else {
                        $error = "Error updating product: " . $con->error;
                    }
                } elseif ($action == 'delete') {
                    // Delete logic
                    $delete_query = "DELETE FROM product WHERE id = ? AND product_seller = ?";
                    $delete_stmt = $con->prepare($delete_query);
                    $delete_stmt->bind_param("is", $product_id, $username);

                    if ($delete_stmt->execute()) {
                        echo "<p class='success'>Product deleted successfully.</p>";
                        // Refresh the page to show updated data
                        echo "<script>window.location.href = 'manage_product.php';</script>";
                    } else {
                        $error = "Error deleting product: " . $con->error;
                    }
                }
            } else {
                $error = "Product not found or you don't have permission to modify it.";
            }
        }
        ?>
    </div>
</body>
</html>