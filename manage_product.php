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
$user_id = $result['Id'];

// Initialize error variable
$error = "";
$product = null; // Variable to store product details if found

// Get products for this user only
$products_query = "SELECT * FROM product WHERE product_seller_id = ?";
$stmt = $con->prepare($products_query);
$stmt->bind_param("i", $user_id);
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
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <title>Manage Your Products</title>
</head>
<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <?php include("php/header.php"); ?>
    <h1 style="color: #333; text-align: center; margin: 20px 0;">Manage Products</h1>

    <div class="container" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin: 20px 40px;">
        <!-- Display user's current products -->
        <div class="current-products">
            <h2 style="color: #333; margin-bottom: 20px;">Your Products</h2>
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: rgba(255, 255, 255, 0.9); margin-bottom: 20px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">ID</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">Image</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">QR Code</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">Name</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">Category</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd;">Description</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">Quantity</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">Price/Rates</th>
                                <th style="padding: 12px; text-align: left; background: rgba(102, 153, 204, 0.8); color: white; border: 1px solid #ddd; white-space: nowrap;">Listing Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product_row = $products_result->fetch_assoc()): ?>
                                <tr>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;"><?php echo htmlspecialchars($product_row['id']); ?></td>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;">
                                        <img src="<?php echo htmlspecialchars($product_row['product_img']); ?>" 
                                             alt="<?php echo htmlspecialchars($product_row['product_name']); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;">
                                        <?php if (!empty($product_row['product_qr_code'])): ?>
                                            <img src="<?php echo htmlspecialchars($product_row['product_qr_code']); ?>" 
                                                 alt="QR Code for <?php echo htmlspecialchars($product_row['product_name']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: contain;">
                                        <?php else: ?>
                                            <div id="qrcode_<?php echo $product_row['id']; ?>" style="width: 50px; height: 50px;"></div>
                                            <script>
                                                new QRCode(document.getElementById("qrcode_<?php echo $product_row['id']; ?>"), {
                                                    text: "http://<?php echo $_SERVER['HTTP_HOST']; ?>/Community%20Engagement%20Platform/product_details.php?id=<?php echo $product_row['id']; ?>",
                                                    width: 50,
                                                    height: 50
                                                });
                                            </script>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;"><?php echo htmlspecialchars($product_row['product_name']); ?></td>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;"><?php echo htmlspecialchars($product_row['product_category']); ?></td>
                                    <td style="padding: 12px; border: 1px solid #ddd; min-width: 300px; max-width: 500px;">
                                        <?php echo nl2br(htmlspecialchars($product_row['product_description'])); ?>
                                    </td>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;"><?php echo htmlspecialchars($product_row['product_quantity']); ?></td>
                                    <td style="padding: 12px; border: 1px solid #ddd;">
                                        <?php if ($product_row['listing_type'] === 'sell'): ?>
                                            $<?php echo number_format($product_row['product_cost'], 2); ?>
                                        <?php else: ?>
                                            <?php if ($product_row['daily_rate']): ?>
                                                Daily: $<?php echo number_format($product_row['daily_rate'], 2); ?><br>
                                            <?php endif; ?>
                                            <?php if ($product_row['weekly_rate']): ?>
                                                Weekly: $<?php echo number_format($product_row['weekly_rate'], 2); ?><br>
                                            <?php endif; ?>
                                            <?php if ($product_row['monthly_rate']): ?>
                                                Monthly: $<?php echo number_format($product_row['monthly_rate'], 2); ?>
                                            <?php endif; ?>
                                            <?php if (!$product_row['daily_rate'] && !$product_row['weekly_rate'] && !$product_row['monthly_rate']): ?>
                                                Contact for rates
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap; text-transform: capitalize;"><?php echo htmlspecialchars($product_row['listing_type']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin: 20px 0;">
                    You haven't added any products yet. 
                    <a href="addproduct.php" style="color: #6699CC; text-decoration: none;">Add your first product</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="container" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin: 20px 40px;">
                <!-- Update or Delete Product Form -->
        <div class="manage-form">
            <h2>Update or Delete Product</h2>
            <p>Select the ID of the product you want to modify from your products list above:</p>
            
            <form method="post">
                <div class="form-group">
                    <label for="product_id">Product ID:</label>
                    <select id="product_id" name="product_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; background-color: white;">
                        <option value="">--Select Product ID--</option>
                        <?php
                        // Reset the pointer to the beginning of the result set
                        if ($products_result) {
                            $products_result->data_seek(0);
                            while ($row = $products_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['id']) . '">' . 
                                     htmlspecialchars($row['id']) . ' - ' . htmlspecialchars($row['product_name']) . 
                                     '</option>';
                            }
                        }
                        ?>
                    </select>
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
                        <label>Select fields to update:</label><br>
                        <input type="checkbox" id="update_name" name="update_fields[]" value="name">
                        <label for="update_name">Name</label><br>
                        
                        <input type="checkbox" id="update_description" name="update_fields[]" value="description">
                        <label for="update_description">Description</label><br>
                        
                        <input type="checkbox" id="update_quantity" name="update_fields[]" value="quantity">
                        <label for="update_quantity">Quantity</label><br>
                        
                        <input type="checkbox" id="update_cost" name="update_fields[]" value="cost">
                        <label for="update_cost">Cost</label>
                    </div>

                    <div class="form-group name-field" style="display: none;">
                        <label for="product_name">Product Name:</label>
                        <input type="text" id="product_name" name="product_name">
                    </div>

                    <div class="form-group description-field" style="display: none;">
                        <label for="product_description">Product Description:</label>
                        <textarea id="product_description" name="product_description" rows="4"></textarea>
                    </div>

                    <div class="form-group quantity-field" style="display: none;">
                        <label for="product_quantity">Product Quantity:</label>
                        <input type="number" id="product_quantity" name="product_quantity" min="0">
                    </div>

                    <div class="form-group cost-field" style="display: none;">
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

            // Show/hide individual fields based on checkbox selection
            document.querySelectorAll('input[name="update_fields[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const fieldClass = this.value + '-field';
                    const field = document.querySelector('.' + fieldClass);
                    if (field) {
                        field.style.display = this.checked ? 'block' : 'none';
                        // Clear the field when unchecked
                        if (!this.checked) {
                            const input = field.querySelector('input, textarea');
                            if (input) input.value = '';
                        }
                    }
                });
            });
        </script>

        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $action = isset($_POST['action']) ? $_POST['action'] : '';

            // Check if product ID exists in the database and belongs to the current user
            $query = "SELECT * FROM product WHERE id = ? AND product_seller_id = ?";
            $stmt = $con->prepare($query);
            $stmt->bind_param("ii", $product_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();

                if ($action == 'update') {
                    // Update logic
                    $updates = array();
                    $types = "";
                    $params = array();

                    // Check which fields were selected for update
                    $update_fields = isset($_POST['update_fields']) ? $_POST['update_fields'] : array();

                    if (in_array('name', $update_fields) && isset($_POST['product_name'])) {
                        $updates[] = "product_name = ?";
                        $types .= "s";
                        $params[] = $_POST['product_name'];
                    }
                    if (in_array('description', $update_fields) && isset($_POST['product_description'])) {
                        $updates[] = "product_description = ?";
                        $types .= "s";
                        $params[] = $_POST['product_description'];
                    }
                    if (in_array('quantity', $update_fields) && isset($_POST['product_quantity'])) {
                        $updates[] = "product_quantity = ?";
                        $types .= "i";
                        $params[] = (int)$_POST['product_quantity'];
                    }
                    if (in_array('cost', $update_fields) && isset($_POST['product_cost'])) {
                        $updates[] = "product_cost = ?";
                        $types .= "d";
                        $params[] = (float)$_POST['product_cost'];
                    }

                    if (!empty($updates)) {
                        // Add the product_id and user_id parameters
                        $types .= "ii";
                        $params[] = $product_id;
                        $params[] = $user_id;

                        $update_query = "UPDATE product SET " . implode(", ", $updates) . 
                                      " WHERE id = ? AND product_seller_id = ?";
                        $update_stmt = $con->prepare($update_query);

                        // Create the parameter array for bind_param
                        $bind_params = array($types);
                        foreach ($params as $key => $value) {
                            $bind_params[] = &$params[$key];
                        }
                        call_user_func_array(array($update_stmt, 'bind_param'), $bind_params);

                        if ($update_stmt->execute()) {
                            echo "<p class='success'>Product updated successfully.</p>";
                            // Refresh the page to show updated data
                            echo "<script>window.location.href = 'manage_product.php';</script>";
                        } else {
                            $error = "Error updating product: " . $con->error;
                        }
                    } else {
                        $error = "Please select at least one field to update.";
                    }
                } elseif ($action == 'delete') {
                    // Delete logic
                    $delete_query = "DELETE FROM product WHERE id = ? AND product_seller_id = ?";
                    $delete_stmt = $con->prepare($delete_query);
                    $delete_stmt->bind_param("ii", $product_id, $user_id);

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