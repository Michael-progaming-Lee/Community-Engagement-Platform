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

// Get the selected listing type from URL parameter, default to 'all'
$listing_type = isset($_GET['listing_type']) ? $_GET['listing_type'] : 'all';

// Define available categories
$categories = ['Vehicle', 'Tool', 'Appliances', 'House', 'Other'];

// Prepare the query based on selected category, listing type, search query, and user's parish
if ($selected_category !== 'all' && in_array($selected_category, $categories)) {
    $search_condition = "";
    switch($search_by) {
        case 'description':
            $search_condition = "p.product_description LIKE CONCAT('%', ?, '%')";
            break;
        case 'id':
            $search_condition = "p.Id = ?";
            break;
        default: 
            $search_condition = "p.product_name LIKE CONCAT('%', ?, '%')";
    }
    
    // Base query with category filter
    $query = "SELECT p.*, u.Parish
              FROM product p
              JOIN users u ON p.product_seller_id = u.Id
              WHERE p.product_category = ?
              AND p.product_quantity > 0
              AND u.Parish = ?
              AND p.status = 'approved'";
    
    // Add listing type filter if selected
    if ($listing_type !== 'all') {
        $query .= " AND p.listing_type = ?";
    }
    
    // Add search condition
    $query .= " AND $search_condition";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        die("Query preparation failed: " . $con->error);
    }
    
    // Bind parameters based on whether listing type is filtered
    if ($listing_type !== 'all') {
        $stmt->bind_param("ssss", $selected_category, $user_parish, $listing_type, $search_query);
    } else {
        $stmt->bind_param("sss", $selected_category, $user_parish, $search_query);
    }
} else {
    $search_condition = "";
    switch($search_by) {
        case 'description':
            $search_condition = "p.product_description LIKE CONCAT('%', ?, '%')";
            break;
        case 'id':
            $search_condition = "p.Id = ?";
            break;
        default: 
            $search_condition = "p.product_name LIKE CONCAT('%', ?, '%')";
    }
    
    // Base query without category filter
    $query = "SELECT p.*, u.Parish
              FROM product p
              JOIN users u ON p.product_seller_id = u.Id
              WHERE p.product_quantity > 0
              AND u.Parish = ?
              AND p.status = 'approved'";
    
    // Add listing type filter if selected
    if ($listing_type !== 'all') {
        $query .= " AND p.listing_type = ?";
    }
    
    // Add search condition
    $query .= " AND $search_condition";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        die("Query preparation failed: " . $con->error);
    }
    
    // Bind parameters based on whether listing type is filtered
    if ($listing_type !== 'all') {
        $stmt->bind_param("sss", $user_parish, $listing_type, $search_query);
    } else {
        $stmt->bind_param("ss", $user_parish, $search_query);
    }
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
    
    <style>
        .search-container {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1200px;
            padding: 20px;
        }

        .search-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-input {
            border: 1px solid #ddd;
            border-radius: 4px;
            flex-grow: 1;
            padding: 10px;
            min-width: 200px;
        }

        .search-select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }

        .search-btn, .reset-btn {
            background-color: #4CAF50;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            padding: 10px 15px;
            text-decoration: none;
        }

        .reset-btn {
            background-color: #f44336;
        }

        .search-btn:hover {
            background-color: #45a049;
        }

        .reset-btn:hover {
            background-color: #d32f2f;
        }

        .profile-btn {
            background-color: #4CAF50;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            padding: 10px 15px;
            text-decoration: none;
        }

        .profile-btn:hover {
            background-color: #45a049;
        }

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input, .search-select, .search-btn, .reset-btn, .profile-btn {
                width: 100%;
            }
        }
        
        /* Navigation button styles */
        .nav-btn {
            background-color: #4CAF50;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            padding: 10px 15px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .nav-btn:hover {
            background-color: #45a049;
        }
        
        .nav-btn-blue {
            background-color: #0066cc;
        }
        
        .nav-btn-blue:hover {
            background-color: #0055aa;
        }
    </style>
</head>

<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <?php include("php/header.php"); ?>
    
    <!-- Search Container -->
    <div class="search-container">
        <form action="" method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
            <select name="search_by" class="search-select">
                <option value="name" <?php echo $search_by === 'name' ? 'selected' : ''; ?>>Name</option>
                <option value="description" <?php echo $search_by === 'description' ? 'selected' : ''; ?>>Description</option>
                <option value="id" <?php echo $search_by === 'id' ? 'selected' : ''; ?>>Product ID#</option>
            </select>
            <select name="listing_type" class="search-select">
                <option value="all" <?php echo $listing_type === 'all' ? 'selected' : ''; ?>>All Listings</option>
                <option value="rent" <?php echo $listing_type === 'rent' ? 'selected' : ''; ?>>Rentals</option>
                <option value="sell" <?php echo $listing_type === 'sell' ? 'selected' : ''; ?>>For Sale</option>
            </select>
            <?php if(isset($_GET['category']) && $_GET['category'] != 'all'): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
            <?php endif; ?>
            <button type="submit" class="search-btn">Search</button>
            <a href="<?php echo isset($_GET['category']) ? '?category=' . htmlspecialchars($_GET['category']) : 'home.php'; ?>" class="reset-btn">Reset</a>
            <a href="edituserinfo.php?Id=<?php echo $res_id ?>" class="nav-btn">Change Profile Information</a>
        </form>
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
                                <?php if ($row['listing_type'] === 'rent'): ?>
                                    <div class="product-cost">
                                        <strong>Rental Rates:</strong>
                                        <?php if (isset($row['daily_rate']) && $row['daily_rate'] > 0): ?>
                                            <div>Daily: $<?php echo number_format($row['daily_rate'], 2); ?></div>
                                        <?php endif; ?>
                                        <?php if (isset($row['weekly_rate']) && $row['weekly_rate'] > 0): ?>
                                            <div>Weekly: $<?php echo number_format($row['weekly_rate'], 2); ?></div>
                                        <?php endif; ?>
                                        <?php if (isset($row['monthly_rate']) && $row['monthly_rate'] > 0): ?>
                                            <div>Monthly: $<?php echo number_format($row['monthly_rate'], 2); ?></div>
                                        <?php endif; ?>
                                        <?php if ((!isset($row['daily_rate']) || $row['daily_rate'] <= 0) && 
                                                  (!isset($row['weekly_rate']) || $row['weekly_rate'] <= 0) && 
                                                  (!isset($row['monthly_rate']) || $row['monthly_rate'] <= 0)): ?>
                                            <div>$<?php echo number_format($row['product_cost'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="product-cost">$<?php echo number_format($row['product_cost'], 2); ?></div>
                                <?php endif; ?>
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