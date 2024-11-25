<?php
session_start();
include("php/config.php");

if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

$id = $_SESSION['id'];
$query = mysqli_query($con, "SELECT * FROM users WHERE Id=$id");
$result = mysqli_fetch_assoc($query);

// Fetch cart items
$cart_query = "SELECT * FROM users_cart WHERE UserID = ?";
$stmt = $con->prepare($cart_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$cart_result = $stmt->get_result();

// Calculate total before displaying
$total_cost = 0;
$cart_items = array();
while ($row = $cart_result->fetch_assoc()) {
    $total_cost += $row['product_total'];
    $cart_items[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/users_cart.css">
    <title>Shopping Cart</title>
</head>
<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <?php include("php/header.php"); ?>
    <h1 style="color: #333; text-align: center; margin: 20px 0;">Your Cart</h1>

    <div class="container" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-left: 150px;  margin-right: 150px; margin-bottom: 20px;">
        <!-- Cart Items -->
        <div class="current-products">
            <?php if (count($cart_items) > 0): ?>
                <div class="cart-total">
                    <strong>Total:</strong> $<?php echo number_format($total_cost, 2); ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Product ID</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Cost per Item</th>
                            <th>Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $row): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($row['product_img']); ?>" 
                                         alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                </td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['product_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['product_description']); ?></td>
                                <td><?php echo htmlspecialchars($row['product_quantity']); ?></td>
                                <td>$<?php echo number_format($row['product_cost'], 2); ?></td>
                                <td>$<?php echo number_format($row['product_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Your cart is empty. <a href="home.php">Continue shopping</a></p>
            <?php endif; ?>
        </div>

        <hr class="section-divider">
    </div>

    <!-- Return Product Section - Moved outside the main container -->
    <h2 style="color: #333; text-align: center; margin: 20px 0;">Return Product</h2>
    <div class="container" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-left: 150px;  margin-right: 150px; margin-bottom: 20px;">
        <div class="container-b">
            
            <p>Enter the ID of the product you want to return from your cart above:</p>
        
            <form method="post" class="return-form" style="display: flex; flex-direction: column; align-items: center;">
                <div class="form-group">
                    <label for="product_id" style="width: 240px; display: Inline-block;">Product ID:</label>
                    <input type="number" id="product_id" name="product_id" required>
                </div>
                
                <div class="form-group">
                    <label for="return_quantity" style="width: 240px; display: Inline-block;">Quantity to Return:</label>
                    <input type="number" id="return_quantity" name="return_quantity" required>
                </div>
                
                <button type="submit" class="btn">Return Product</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $return_product_id = $_POST['product_id'];
                $return_quantity = (int)$_POST['return_quantity'];

                // Fetch the product from users_cart
                $cart_query = "SELECT * FROM users_cart WHERE product_id = ? AND UserID = ?";
                $cart_stmt = $con->prepare($cart_query);
                $cart_stmt->bind_param("ii", $return_product_id, $id);
                $cart_stmt->execute();
                $cart_result = $cart_stmt->get_result();

                if ($cart_item = $cart_result->fetch_assoc()) {
                    if ($return_quantity > $cart_item['product_quantity']) {
                        echo "<p class='error'>Cannot return more than rented quantity.</p>";
                    } else {
                        // Fetch current product quantity from 'product' table
                        $product_query = "SELECT product_quantity FROM product WHERE id = ?";
                        $product_stmt = $con->prepare($product_query);
                        $product_stmt->bind_param("i", $return_product_id);
                        $product_stmt->execute();
                        $product_result = $product_stmt->get_result();
                        $product_row = $product_result->fetch_assoc();

                        // Update product quantity in 'product' table
                        $new_quantity = $product_row['product_quantity'] + $return_quantity;
                        $update_query = "UPDATE product SET product_quantity = ? WHERE id = ?";
                        $update_stmt = $con->prepare($update_query);
                        $update_stmt->bind_param("ii", $new_quantity, $return_product_id);
                        
                        if ($update_stmt->execute()) {
                            // Update or delete from users_cart
                            $new_cart_quantity = $cart_item['product_quantity'] - $return_quantity;
                            if ($new_cart_quantity > 0) {
                                $new_total = $new_cart_quantity * $cart_item['product_cost'];
                                $cart_update = "UPDATE users_cart SET product_quantity = ?, product_total = ? WHERE product_id = ? AND UserID = ?";
                                $cart_update_stmt = $con->prepare($cart_update);
                                $cart_update_stmt->bind_param("idii", $new_cart_quantity, $new_total, $return_product_id, $id);
                                $cart_update_stmt->execute();
                            } else {
                                $cart_delete = "DELETE FROM users_cart WHERE product_id = ? AND UserID = ?";
                                $cart_delete_stmt = $con->prepare($cart_delete);
                                $cart_delete_stmt->bind_param("ii", $return_product_id, $id);
                                $cart_delete_stmt->execute();
                            }
                            
                            echo "<p class='success'>Product returned successfully!</p>";
                            echo "<script>window.location.href = window.location.href;</script>";
                        } else {
                            echo "<p class='error'>Error updating product quantity.</p>";
                        }
                    }
                } else {
                    echo "<p class='error'>Product not found in your cart.</p>";
                }
            }
            ?>
        </div>
    </div>
</body>
</html>