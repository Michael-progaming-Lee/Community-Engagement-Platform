<?php
include 'php/config.php'; // Include the database connection

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "community_engagement_db";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

session_start();
$username = $_SESSION['username']; // Get the logged-in username
$user_id = $_SESSION['id']; // Get the logged-in user ID

// Fetch product details
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = "SELECT * FROM product WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rent_quantity = isset($_POST['rent_quantity']) ? (int)$_POST['rent_quantity'] : 0;

    if ($rent_quantity > $product['product_quantity']) {
        echo "<p class='error'>Cannot rent more than available stock.</p>";
    } else {
        // Calculate total
        $product_total = $rent_quantity * $product['product_cost'];

        // Insert into users_cart
        $insert_query = "INSERT INTO users_cart (Username, UserID, product_id, product_name, product_category, product_description, product_quantity, product_cost, product_img, product_total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
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
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $new_quantity, $product_id);
            $update_stmt->execute();

            // Mark as out of stock if quantity is 0
            if ($new_quantity == 0) {
                $out_of_stock_query = "UPDATE product SET product_description = CONCAT(product_description, ' - OUT OF STOCK') WHERE id = ?";
                $out_of_stock_stmt = $conn->prepare($out_of_stock_query);
                $out_of_stock_stmt->bind_param("i", $product_id);
                $out_of_stock_stmt->execute();
            }

            echo "<p class='success'>Product rented successfully!</p>";
        } else {
            echo "<p class='error'>Error renting product: " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['product_name']; ?></title>
</head>

<body>

    <h1><?php echo $product['product_name']; ?></h1>
    <img src="<?php echo $product['product_img'];?>" alt="<?php echo $product['product_name']; ?>" style="width:300px;">
    <p><strong>Product ID:</strong> <?php echo $product['id']; ?></p>
    <p><strong>Category:</strong> <?php echo $product['product_category']; ?></p>
    <p><strong>Description:</strong> <?php echo $product['product_description']; ?></p>
    <p><strong>Available Quantity:</strong> <?php echo $product['product_quantity']; ?></p>
    <p><strong>Cost:</strong> $<?php echo number_format($product['product_cost'], 2); ?></p>

    <h2>Rent Product</h2>
    <form method="post">
        <label for="rent_quantity">Enter Quantity to Rent:</label>
        <input type="number" id="rent_quantity" name="rent_quantity" min="1" max="<?php echo $product['product_quantity']; ?>" required>
        <button type="submit">Rent</button>
    </form>

</body>

</html>