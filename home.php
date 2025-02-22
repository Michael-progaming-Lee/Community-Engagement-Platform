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
$user_parish = $result['Parish'];

// Get the search query if it exists
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_by = isset($_GET['search_by']) ? $_GET['search_by'] : 'name';

// Get the selected category from URL parameter, default to 'all'
$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Define available categories
$categories = ['Vehicle', 'Tool', 'Appliances', 'House', 'Other'];

// Prepare the query based on selected category, search query, and user's parish
if ($selected_category !== 'all' && in_array($selected_category, $categories)) {
    $search_condition = "";
    switch($search_by) {
        case 'description':
            $search_condition = "p.product_description LIKE CONCAT('%', ?, '%')";
            break;
        case 'id':
            $search_condition = "p.Id = ?";
            break;
        default: // name
            $search_condition = "p.product_name LIKE CONCAT('%', ?, '%')";
    }
    
    $query = "SELECT p.*, u.Parish
            FROM product p
            JOIN users u ON p.product_seller = u.Username
            WHERE p.product_category = ?
            AND p.product_quantity > 0
            AND u.Parish = ?
            AND ($search_condition)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("sss", $selected_category, $user_parish, $search_query);
} else {
    $search_condition = "";
    switch($search_by) {
        case 'description':
            $search_condition = "p.product_description LIKE CONCAT('%', ?, '%')";
            break;
        case 'id':
            $search_condition = "p.Id = ?";
            break;
        default: // name
            $search_condition = "p.product_name LIKE CONCAT('%', ?, '%')";
    }
    
    $query = "SELECT p.*, u.Parish
            FROM product p
            JOIN users u ON p.product_seller = u.Username
            WHERE p.product_quantity > 0
            AND u.Parish = ?
            AND ($search_condition)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ss", $user_parish, $search_query);
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
    <script>
        function changeBackground(category) {
            const body = document.body;
            switch (category) {
                case 'Vehicle':
                    body.style.backgroundImage = "url('Background Images/Car-Background.png')";
                    break;
                case 'Tool':
                    body.style.backgroundImage = "url('Background Images/Tool-Background.png')";
                    break;
                case 'Appliances':
                    body.style.backgroundImage = "url('Background Images/Appliances-Background.png')";
                    break;
                case 'House':
                    body.style.backgroundImage = "url('Background Images/House-Background.png')";
                    break;
                case 'Other':
                    body.style.backgroundImage = "url('Background Images/Other-Background.jpg')";
                    break;
                default:
                    body.style.backgroundImage = "url('Background Images/Home_Background.png')";
            }
        }

        // Check initial category on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const category = urlParams.get('category');
            changeBackground(category);
        });
    </script>
</head>

<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <div class="nav" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(147, 163, 178, 0.8); backdrop-filter: blur(10px); display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
        <div class="logo" style="flex: 0 0 auto;">
            <img src="Background Images/CommUnity Logo.png" alt="Company Logo" style="height: 50px;">
        </div>

        <div style="flex: 0 0 auto; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; color: #333;">CommUnity Rentals</h1>
        </div>

        <div style="flex: 0 0 auto; display: flex; gap: 20px; align-items: center;">
            <form action="" method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>" 
                    style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 250px;">
                <select name="search_by" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="name" <?php echo $search_by === 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="description" <?php echo $search_by === 'description' ? 'selected' : ''; ?>>Description</option>
                    <option value="id" <?php echo $search_by === 'id' ? 'selected' : ''; ?>>Product ID#</option>
                </select>
                <?php if(isset($_GET['category']) && $_GET['category'] != 'all'): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
                <?php endif; ?>
                <button type="submit" class="btn" style="margin: 0; padding: 8px 15px; height: 35px;">Search</button>
                <a href="<?php echo isset($_GET['category']) ? '?category=' . htmlspecialchars($_GET['category']) : 'home.php'; ?>" 
                   class="btn" style="margin: 0; padding: 8px 15px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 35px;">Reset</a>
            </form>
            <a href="edituserinfo.php?Id=<?php echo $res_id ?>">Change Profile Information</a>
            <a href="php/logout.php"> <button class="btn">Log Out</button> </a>
        </div>
    </div>
    <div style="height: 70px;"></div>

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

                <div class="box">
                    <p>Store Region: <b><?php echo $user_parish ?></b>.</p>
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
            <a href="my-negotiations.php" class="action-btn" style="background-color: #4CAF50;">
                <i class="fas fa-handshake"></i>
                Price Negotiations
            </a>
        </div>

        <div class="category-filters">
            <h2>Categories</h2>
            <div class="category-buttons">
                <a href="home.php" class="category-btn <?php echo $selected_category === 'all' ? 'active' : ''; ?>" onclick="changeBackground('all')">
                    All Products
                </a>
                <?php foreach ($categories as $category): ?>
                    <a href="home.php?category=<?php echo $category ?>"
                        class="category-btn <?php echo $selected_category === $category ? 'active' : ''; ?>"
                        onclick="changeBackground('<?php echo $category ?>')">
                        <?php echo $category ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <h1><?php echo $selected_category === 'all' ? 'All Products' : $selected_category . ' Products'; ?></h1>

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
            <div class="message" style="margin-top: 20px;">
                <p>No products available in your parish (<?php echo htmlspecialchars($user_parish); ?>) at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
<?php
// Close the database connection
$con->close();
?>