<?php
include 'php/config.php';

session_start();
$username = $_SESSION['username'];
$user_id = $_SESSION['id'];

// Fetch cart items
$query = "SELECT * FROM users_cart WHERE UserID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$total_cost = 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Your Cart</title>
    <link rel="stylesheet" href="style/style.css"> <!-- Link to your CSS file -->
</head>

<body>

    <h1>Your Cart</h1>
    <div style="float: right;"><strong>Total:</strong> $<?php echo number_format($total_cost, 2); ?></div>

    <table border="1">
        <thead>
            <tr>
                <th>Product Image</th>
                <th>Product Name</th>
                <th>Product ID</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Cost</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php $total_cost += $row['product_total']; ?>
                <tr>
                    <td><img src="<?php echo $row['product_img']; ?>" alt="<?php echo $row['product_name']; ?>" style="width:50px;"></td>
                    <td><?php echo $row['product_name']; ?></td>
                    <td><?php echo $row['product_id']; ?></td>
                    <td><?php echo $row['product_description']; ?></td>
                    <td><?php echo $row['product_quantity']; ?></td>
                    <td>$<?php echo number_format($row['product_cost'], 2); ?></td>
                    <td>$<?php echo number_format($row['product_total'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Return Product</h2>
    <form method="post">
        <label for="product_id">Enter Product ID:</label>
        <input type="number" id="product_id" name="product_id" required>
        <br><br>
        <label for="return_quantity">Enter Quantity to Return:</label>
        <input type="number" id="return_quantity" name="return_quantity" required>
        <br><br>
        <button type="submit">Return</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $return_product_id = $_POST['product_id'];
        $return_quantity = (int)$_POST['return_quantity'];

        // Fetch the product from users_cart
        $cart_query = "SELECT * FROM users_cart WHERE product_id = ? AND UserID = ?";
        $cart_stmt = $conn->prepare($cart_query);
        $cart_stmt->bind_param("ii", $return_product_id, $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();

        if ($cart_item = $cart_result->fetch_assoc()) {
            if ($return_quantity > $cart_item['product_quantity']) {
                echo "<p class='error'>Cannot return more than rented quantity.</p>";
            } else {
                // Fetch current product quantity from 'product' table
                $product_query = "SELECT product_quantity FROM product WHERE id = ?";
                $product_stmt = $conn->prepare($product_query);
                $product_stmt->bind_param("i", $return_product_id);
                $product_stmt->execute();
                $product_result = $product_stmt->get_result();
                $product_row = $product_result->fetch_assoc();
                $current_product_quantity = $product_row['product_quantity'];

                // Update product quantity in `product` table
                $update_product_query = "UPDATE product SET product_quantity = product_quantity + ? WHERE id = ?";
                $update_product_stmt = $conn->prepare($update_product_query);
                $update_product_stmt->bind_param("ii", $return_quantity, $return_product_id);
                $update_product_stmt->execute();

                // Check if product was previously out of stock and now back in stock
                if ($current_product_quantity == 0) {
                    // Remove ' - OUT OF STOCK' from product_description
                    $update_description_query = "UPDATE product SET product_description = REPLACE(product_description, ' - OUT OF STOCK', '') WHERE id = ?";
                    $update_description_stmt = $conn->prepare($update_description_query);
                    $update_description_stmt->bind_param("i", $return_product_id);
                    $update_description_stmt->execute();
                }

                // Remove from cart or update cart
                if ($return_quantity == $cart_item['product_quantity']) {
                    $delete_query = "DELETE FROM users_cart WHERE Id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    $delete_stmt->bind_param("i", $cart_item['Id']);
                    $delete_stmt->execute();
                } else {
                    $new_cart_quantity = $cart_item['product_quantity'] - $return_quantity;
                    $update_cart_query = "UPDATE users_cart SET product_quantity = ?, product_total = product_quantity * product_cost WHERE Id = ?";
                    $update_cart_stmt = $conn->prepare($update_cart_query);
                    $update_cart_stmt->bind_param("ii", $new_cart_quantity, $cart_item['Id']);
                    $update_cart_stmt->execute();
                }

                echo "<p class='success'>Product returned successfully!</p>";
            }
        } else {
            echo "<p class='error'>Product not found in your cart.</p>";
        }
    }
    ?>

</body>

</html>