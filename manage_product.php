<?php
session_start();
include 'php/config.php'; // Include the database connection

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "community_engagement_db";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

$error = ""; // Variable to store error messages
$product = null; // Variable to store product details if found

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // Check if product ID exists in the database
    $query = "SELECT * FROM product WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
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
                            WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param(
                "ssidi",
                $product_name,
                $product_description,
                $product_quantity,
                $product_cost,
                $product_id
            );

            if ($update_stmt->execute()) {
                echo "<p class='success'>Product updated successfully.</p>";
            } else {
                $error = "Error updating product: " . $conn->error;
            }
        } elseif ($action == 'delete') {
            // Delete logic
            $delete_query = "DELETE FROM product WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $product_id);

            if ($delete_stmt->execute()) {
                echo "<p class='success'>Product deleted successfully.</p>";
            } else {
                $error = "Error deleting product: " . $conn->error;
            }
        }
    } else {
        $error = "Product ID not found.";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Product</title>
    <link rel="stylesheet" href="style/manage_product_style.css">
</head>

<body>
    <div class="nav">
        <div class="logo">
            <p><a href="home.php">Logo</a> </p>
        </div>

        <div class="right-links">

            <?php

            $id = $_SESSION['id'];
            $query = mysqli_query($conn, "SELECT*FROM users WHERE Id=$id");

            while ($result = mysqli_fetch_assoc($query)) {
                $res_Uname = $result['Username'];
                $res_Email = $result['Email'];
                $res_Age = $result['Age'];
                $res_id = $result['Id'];
            }


            ?>

            <a href="php/logout.php"> <button class="btn">Log Out</button> </a>

        </div>
    </div>

    <h1>Manage Product</h1>

    <form method="post">
        <label for="product_id">Enter Product ID:</label>
        <input type="number" id="product_id" name="product_id" required>
        <br><br>

        <label for="action">Select Action:</label>
        <select id="action" name="action" required>
            <option value="">--Choose Action--</option>
            <option value="update">Update Product</option>
            <option value="delete">Delete Product</option>
        </select>
        <br><br>

        <!-- Update Fields -->
        <div id="update-fields" style="display: none;">
            
            <label for="product_name">Product Name:</label>
            <input type="text" id="product_name" name="product_name" value="<?php echo isset($product['product_name']) ? $product['product_name'] : ''; ?>">
            <br><br>

            <label for="product_description">Product Description:</label>
            <textarea id="product_description" name="product_description"><?php echo isset($product['product_description']) ? $product['product_description'] : ''; ?></textarea>
            <br><br>

            <label for="product_quantity">Product Quantity:</label>
            <input type="number" id="product_quantity" name="product_quantity" value="<?php echo isset($product['product_quantity']) ? $product['product_quantity'] : ''; ?>">
            <br><br>

            <label for="product_cost">Product Cost:</label>
            <input type="number" step="0.01" id="product_cost" name="product_cost" value="<?php echo isset($product['product_cost']) ? $product['product_cost'] : ''; ?>">
            <br><br>
        </div>

        <button type="submit">Submit</button>
    </form>

    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

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

</body>

</html>