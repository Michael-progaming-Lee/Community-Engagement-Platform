<?php
// Include the database configuration file
include 'php/config.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "community_engagement_db";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Get product ID from the URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch product details
$query = "SELECT * FROM product WHERE id = $product_id";
$result = $conn->query($query);
$product = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title><?php echo $product['product_name']; ?></title>
</head>
<body>

<?php if ($product): ?>
    <h1><?php echo $product['product_name']; ?></h1>
    <img src="<?php echo $product['product_img']; ?>" alt="<?php echo $product['product_name']; ?>" style="width:300px;">
    <p><strong>Seller:</strong> <?php echo $product['product_seller']; ?></p>
    <p><strong>Description:</strong> <?php echo $product['product_description']; ?></p>
    <p><strong>Quantity:</strong> <?php echo $product['product_quantity']; ?></p>
    <p><strong>Cost:</strong> $<?php echo number_format($product['product_cost'], 2); ?></p>
<?php else: ?>
    <p>Product not found.</p>
<?php endif; ?>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
