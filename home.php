<?php
session_start();
include("php/config.php");

if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

// Get user information
$id = $_SESSION['id'];
$query = mysqli_query($con, "SELECT * FROM users WHERE Id=$id");
$result = mysqli_fetch_assoc($query);
$res_Uname = $result['Username'];
$res_Email = $result['Email'];
$res_Age = $result['Age'];
$res_id = $result['Id'];

// Get the selected category from URL parameter, default to 'all'
$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Define available categories
$categories = ['Vehicle', 'Tool', 'Appliances', 'House', 'Other'];

// Prepare the query based on selected category
if ($selected_category !== 'all' && in_array($selected_category, $categories)) {
    $query = "SELECT * FROM product WHERE product_category = ? AND product_quantity > 0";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $selected_category);
} else {
    $query = "SELECT * FROM product WHERE product_quantity > 0";
    $stmt = $con->prepare($query);
}

$stmt->execute();
$resultd = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/home.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Home</title>

    <style>
        /* Styling for the product grid */
        .product-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .product-item {
            border: 1px solid #ccc;
            padding: 10px;
            width: 200px;
            text-align: center;
        }

        .product-img {
            width: 100%;
            align-items: center;
            height: auto;
        }

        .product-name {
            font-size: 18px;
            margin: 10px 0;
        }

        .product-cost {
            font-size: 16px;
            color: #333;
        }
    </style>

</head>
<body>
    <div class="nav">
        <div class="logo">
            <p><a href="home.php">Logo</a> </p>
        </div>

        <div class="right-links">
            <a href="edituserinfo.php?Id=<?php echo $res_id ?>">Change Profile Information</a>
            <a href="php/logout.php"> <button class="btn">Log Out</button> </a>
        </div>
    </div>

    <main>
        <!-- Welcome Message-->
        <div class="main-box top">
            <div class="top">
                <div class="box">
                    <p>Hello <b><?php echo $res_Uname ?></b>, Welcome</p>
                </div>
                <div class="box">
                    <p>Your email is <b><?php echo $res_Email ?></b>.</p>
                </div>
            </div>
        </div>
    </main>

    <div class="main-content">
        <!-- Action Buttons Section -->
        <div class="action-buttons">
            <a href="addproduct.php" class="action-btn add-product">
                <i class="fas fa-plus"></i>
                Add New Product
            </a>
            <a href="users_cart.php" class="action-btn view-cart">
                <i class="fas fa-shopping-cart"></i>
                View Your Cart
            </a>
        </div>

        <div class="category-filters">
            <h2>Categories</h2>
            <div class="category-buttons">
                <a href="home.php" class="category-btn <?php echo $selected_category === 'all' ? 'active' : ''; ?>">
                    All Products
                </a>
                <?php foreach ($categories as $category): ?>
                    <a href="home.php?category=<?php echo urlencode($category); ?>" 
                       class="category-btn <?php echo $selected_category === $category ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <h1><?php echo $selected_category === 'all' ? 'All Products' : htmlspecialchars($selected_category) . ' Products'; ?></h1>

        <?php if ($resultd->num_rows > 0): ?>
            <div class="product-grid">
                <?php while ($row = $resultd->fetch_assoc()): ?>
                    <div class="product-item">
                        <a href="product_details.php?id=<?php echo $row['id']; ?>">
                            <?php if (!empty($row['product_img']) && file_exists($row['product_img'])): ?>
                                <img src="<?php echo htmlspecialchars($row['product_img']); ?>" 
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
                                     class="product-img">
                            <?php else: ?>
                                <img src="style/default-product.png" 
                                     alt="No image available" 
                                     class="product-img">
                            <?php endif; ?>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                <div class="product-category">Category: <?php echo htmlspecialchars($row['product_category']); ?></div>
                                <div class="product-cost">$<?php echo number_format($row['product_cost'], 2); ?></div>
                                <div class="product-quantity">Available: <?php echo $row['product_quantity']; ?></div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="no-products">No products available in this category.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Close the database connection
$con->close();
?>