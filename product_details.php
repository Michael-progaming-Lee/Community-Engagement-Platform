<?php
session_start();
include 'php/config.php';

// Handle add to cart AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    header('Content-Type: application/json');
    
    // Start logging
    error_log("==================== ADD TO CART STARTED ====================");
    
    if (!isset($_SESSION['id'])) {
        error_log("Error: User not logged in");
        die(json_encode(['error' => 'Please log in to add items to cart']));
    }
    
    error_log("Add to cart POST data: " . print_r($_POST, true));
    
    try {
        $user_id = $_SESSION['id'];
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $rental_start_date = isset($_POST['rental_start_date']) ? $_POST['rental_start_date'] : null;
        $rental_end_date = isset($_POST['rental_end_date']) ? $_POST['rental_end_date'] : null;
        $rental_duration = isset($_POST['total_days']) ? (int)$_POST['total_days'] : 0;
        $duration_unit = isset($_POST['duration_unit']) ? $_POST['duration_unit'] : 'weekly';
    
        if ($product_id <= 0 || $quantity <= 0) {
            error_log("Invalid product or quantity: id={$product_id}, quantity={$quantity}");
            die(json_encode(['error' => 'Invalid product or quantity']));
        }
    
        // Get current time for debugging
        $now = date('Y-m-d H:i:s');
        error_log("[$now] Processing cart add for product ID: $product_id");
    
        // Check if product exists and get its details
        $query = "SELECT p.*, u.Username as product_seller 
                  FROM product p
                  JOIN users u ON p.product_seller_id = u.Id
                  WHERE p.id = ?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
    
        if (!$product) {
            error_log("Product not found: id={$product_id}");
            die(json_encode(['error' => 'Product not found']));
        }
    
        error_log("Product found: " . print_r($product, true));
    
        // Verify requested quantity is available
        if ($quantity > $product['product_quantity']) {
            error_log("Requested quantity exceeds available stock: requested={$quantity}, available={$product['product_quantity']}");
            die(json_encode(['error' => 'Requested quantity exceeds available stock']));
        }
    
        // Check if rental dates are required and provided
        if ($product['listing_type'] === 'rent' && (!$rental_start_date || !$rental_end_date)) {
            error_log("Missing rental dates: start={$rental_start_date}, end={$rental_end_date}");
            die(json_encode(['error' => 'Rental dates are required for rental items']));
        }
        
        // Format the rental dates to ensure they're in proper MySQL date format (YYYY-MM-DD)
        if ($product['listing_type'] === 'rent') {
            // Properly format rental dates to ensure they're stored correctly
            try {
                $start_date = new DateTime($rental_start_date);
                $rental_start_date = $start_date->format('Y-m-d');
                
                $end_date = new DateTime($rental_end_date);
                $rental_end_date = $end_date->format('Y-m-d');
                
                error_log("Formatted rental dates: start={$rental_start_date}, end={$rental_end_date}");
            } catch (Exception $e) {
                error_log("Date formatting error: " . $e->getMessage());
                die(json_encode(['error' => 'Invalid date format. Please try again.']));
            }
        }
        
        // Check if rental duration exceeds maximum allowed period
        if ($product['listing_type'] === 'rent') {
            if ($duration_unit === 'daily' && $rental_duration > 12) {
                error_log("Daily rental duration exceeds maximum allowed period: {$rental_duration} days (max: 12 days)");
                die(json_encode(['error' => 'Maximum rental period for daily rentals is 12 days']));
            } elseif ($duration_unit === 'weekly' && $rental_duration > 21) {
                error_log("Weekly rental duration exceeds maximum allowed period: {$rental_duration} days (max: 21 days)");
                die(json_encode(['error' => 'Maximum rental period for weekly rentals is 21 days (3 weeks)']));
            } elseif ($duration_unit === 'monthly' && $rental_duration > 122) {
                error_log("Monthly rental duration exceeds maximum allowed period: {$rental_duration} days (max: 122 days)");
                die(json_encode(['error' => 'Maximum rental period for monthly rentals is 122 days (4 months)']));
            }
        }
    
        // Calculate total cost
        if ($product['listing_type'] === 'rent') {
            // For rental products, calculate based on rental period
            if ($rental_duration <= 0) {
                error_log("Rental duration is zero or negative, setting to default");
                $rental_duration = 7; // Default to 1 week (7 days)
            }
    
            error_log("Processing rental product with duration: {$rental_duration} {$duration_unit}");
    
            // Get the appropriate rate based on duration unit
            $rate = 0;
            if ($duration_unit === 'daily') {
                $rate = isset($product['daily_rate']) && $product['daily_rate'] > 0 ? $product['daily_rate'] : $product['product_cost'];
                error_log("Using daily rate: {$rate}");
            } elseif ($duration_unit === 'weekly') {
                $rate = isset($product['weekly_rate']) && $product['weekly_rate'] > 0 ? $product['weekly_rate'] : $product['product_cost'];
                error_log("Using weekly rate: {$rate}");
            } elseif ($duration_unit === 'monthly') {
                $rate = isset($product['monthly_rate']) && $product['monthly_rate'] > 0 ? $product['monthly_rate'] : $product['product_cost'];
                error_log("Using monthly rate: {$rate}");
            } else {
                // Default to product cost if no specific rate is available
                $rate = $product['product_cost'];
                error_log("Using default product cost: {$rate}");
            }
    
            // Calculate total based on duration units
            $total_cost = $quantity * $rate;
            
            // Adjust calculation based on duration unit
            if ($duration_unit === 'weekly') {
                // For weekly rentals, calculate the number of weeks
                $weeks = ceil($rental_duration / 7);
                $total_cost = $quantity * $rate * $weeks;
                error_log("Weekly rental calculation: quantity={$quantity}, rate={$rate}, weeks={$weeks}, total={$total_cost}");
            } elseif ($duration_unit === 'monthly') {
                // For monthly rentals, calculate the number of months (30 days per month)
                $months = ceil($rental_duration / 30);
                // Ensure at least 1 month is charged
                $months = max(1, $months);
                $total_cost = $quantity * $rate * $months;
                error_log("Monthly rental calculation: quantity={$quantity}, rate={$rate}, months={$months}, total={$total_cost}");
            } elseif ($duration_unit === 'daily') {
                // For daily rentals, multiply by the number of days
                $total_cost = $quantity * $rate * $rental_duration;
                error_log("Daily rental calculation: quantity={$quantity}, rate={$rate}, days={$rental_duration}, total={$total_cost}");
            }
    
            // Check if bulk discount applies
            $bulk_threshold = $product['bulk_discount_threshold'] ?? 0;
            $bulk_discount = $product['bulk_discount_percent'] ?? 0;
    
            if ($bulk_threshold > 0 && $bulk_discount > 0 && $rental_duration >= $bulk_threshold) {
                // Apply bulk discount
                $total_cost = $total_cost * (1 - ($bulk_discount / 100));
                error_log("Applied bulk discount: threshold={$bulk_threshold}, discount={$bulk_discount}%, new_total={$total_cost}");
            }
    
            error_log("Rental calculation: duration={$rental_duration}, unit={$duration_unit}, rate={$rate}, total={$total_cost}");
        } else {
            // For purchase products, calculate standard price
            $total_cost = $quantity * $product['product_cost'];
            error_log("Standard product total: quantity={$quantity}, cost={$product['product_cost']}, total={$total_cost}");
        }
    
        // Check if item already exists in cart
        $check_cart = "SELECT product_quantity FROM users_cart WHERE UserID = ? AND product_id = ?";
        if ($product['listing_type'] === 'rent') {
            // First check if rental columns exist
            $check_columns = $con->query("SHOW COLUMNS FROM users_cart LIKE 'rental_start_date'");
            if ($check_columns && $check_columns->num_rows == 0) {
                // Add the missing columns
                $alter_query = "ALTER TABLE users_cart 
                    ADD COLUMN rental_start_date DATE NULL,
                    ADD COLUMN rental_end_date DATE NULL,
                    ADD COLUMN rental_duration INT NULL,
                    ADD COLUMN duration_unit VARCHAR(20) NULL";
                
                if (!$con->query($alter_query)) {
                    error_log("Failed to alter table: " . $con->error);
                    die(json_encode(['error' => 'Failed to update database structure. Please contact support.']));
                }
                error_log("Added rental columns to users_cart table");
            }
            
            // Now we can safely use these columns in our query
            $check_cart .= " AND rental_start_date = ? AND rental_end_date = ?";
            if (isset($_POST['duration_unit'])) {
                $check_cart .= " AND duration_unit = ?";
            }
        }
    
        error_log("Check cart query: {$check_cart}");
    
        $stmt = $con->prepare($check_cart);
        if ($stmt === false) {
            error_log("Prepare failed: " . $con->error);
            die(json_encode(['error' => 'Database error: ' . $con->error]));
        }
        
        if ($product['listing_type'] === 'rent') {
            if (isset($_POST['duration_unit'])) {
                $stmt->bind_param("iisss", $user_id, $product_id, $rental_start_date, $rental_end_date, $duration_unit);
            } else {
                $stmt->bind_param("iiss", $user_id, $product_id, $rental_start_date, $rental_end_date);
            }
        } else {
            $stmt->bind_param("ii", $user_id, $product_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_item = $result->fetch_assoc();
    
        if ($existing_item) {
            $new_quantity = $existing_item['product_quantity'] + $quantity;
            error_log("Item already exists in cart, updating quantity from " . $existing_item['product_quantity'] . " to " . $new_quantity);
            
            // Calculate new total cost
            if ($product['listing_type'] === 'rent') {
                // Use the appropriate rate based on duration unit
                $new_total = $new_quantity * $rate;
    
                // Apply bulk discount if applicable
                if ($bulk_threshold > 0 && $bulk_discount > 0 && $rental_duration >= $bulk_threshold) {
                    $new_total = $new_total * (1 - ($bulk_discount / 100));
                }
            } else {
                $new_total = $new_quantity * $product['product_cost'];
            }
    
            $update_query = "UPDATE users_cart SET product_quantity = ?, product_total = ? WHERE UserID = ? AND product_id = ?";
            if ($product['listing_type'] === 'rent') {
                $update_query .= " AND rental_start_date = ? AND rental_end_date = ?";
                if (isset($_POST['duration_unit'])) {
                    $update_query .= " AND duration_unit = ?";
                }
            }
    
            error_log("Update cart query: {$update_query}");
    
            $stmt = $con->prepare($update_query);
            if ($stmt === false) {
                error_log("Prepare failed: " . $con->error);
                die(json_encode(['error' => 'Database error: ' . $con->error]));
            }
            
            if ($product['listing_type'] === 'rent') {
                if (isset($_POST['duration_unit'])) {
                    $stmt->bind_param("idiisss", $new_quantity, $new_total, $user_id, $product_id, $rental_start_date, $rental_end_date, $duration_unit);
                } else {
                    $stmt->bind_param("idiiss", $new_quantity, $new_total, $user_id, $product_id, $rental_start_date, $rental_end_date);
                }
            } else {
                $stmt->bind_param("idii", $new_quantity, $new_total, $user_id, $product_id);
            }
        } else {
            // Insert new cart item
            if ($product['listing_type'] === 'rent') {
                // Add debug output for rental insert
                error_log("Inserting rental product: duration={$rental_duration}, unit={$duration_unit}, rate={$rate}");
                error_log("Rental product details: \n    Start Date: {$rental_start_date}\n    End Date: {$rental_end_date}\n    Duration: {$rental_duration} days\n    Unit: {$duration_unit}\n    Rate: {$rate}");

                // Check if the users_cart table has the necessary columns
                $check_columns_query = "SHOW COLUMNS FROM users_cart LIKE 'listing_type'";
                $columns_result = $con->query($check_columns_query);
                
                if ($columns_result && $columns_result->num_rows == 0) {
                    // Add missing listing_type column if it doesn't exist
                    error_log("Adding missing listing_type column to users_cart table");
                    $alter_table_query = "ALTER TABLE users_cart ADD COLUMN listing_type VARCHAR(50) DEFAULT 'purchase'";
                    $con->query($alter_table_query);
                }

                $check_columns_query = "SHOW COLUMNS FROM users_cart LIKE 'rental_duration'";
                $columns_result = $con->query($check_columns_query);
                
                if ($columns_result && $columns_result->num_rows == 0) {
                    // Add missing rental columns if they don't exist
                    error_log("Adding missing rental columns to users_cart table");
                    $alter_table_query = "ALTER TABLE users_cart 
                        ADD COLUMN rental_start_date DATE NULL,
                        ADD COLUMN rental_end_date DATE NULL,
                        ADD COLUMN rental_duration INT NULL,
                        ADD COLUMN duration_unit VARCHAR(20) NULL";
                    $con->query($alter_table_query);
                }

                $insert_query = "INSERT INTO users_cart (
                    UserID, product_id, product_name, product_category, 
                    product_description, product_img, product_cost, 
                    product_quantity, product_total, listing_type,
                    rental_start_date, rental_end_date, rental_duration, duration_unit
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                error_log("Insert cart query: {$insert_query}");
                error_log("Rental dates being inserted: start={$rental_start_date}, end={$rental_end_date}");

                $stmt = $con->prepare($insert_query);
                if ($stmt === false) {
                    error_log("Prepare failed: " . $con->error);
                    die(json_encode(['error' => 'Database error: ' . $con->error]));
                }
                
                // Use 'rent' as the listing_type for rentals
                $listing_type = 'rent';
                
                $stmt->bind_param("iissssddisssis", 
                    $user_id, 
                    $product_id, 
                    $product['product_name'], 
                    $product['product_category'], 
                    $product['product_description'], 
                    $product['product_img'], 
                    $rate, 
                    $quantity, 
                    $total_cost,
                    $listing_type,
                    $rental_start_date,
                    $rental_end_date,
                    $rental_duration,
                    $duration_unit
                );
            } else {
                // Use the product's listing_type for purchase items
                $listing_type = $product['listing_type'];
                
                $insert_query = "INSERT INTO users_cart (
                    UserID, product_id, product_name, product_category, 
                    product_description, product_img, product_cost, 
                    product_quantity, product_total, listing_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $con->prepare($insert_query);
                if ($stmt === false) {
                    error_log("Prepare failed: " . $con->error);
                    die(json_encode(['error' => 'Database error: ' . $con->error]));
                }
                
                $stmt->bind_param("iissssdids", 
                    $user_id, 
                    $product_id, 
                    $product['product_name'], 
                    $product['product_category'], 
                    $product['product_description'], 
                    $product['product_img'], 
                    $product['product_cost'], 
                    $quantity, 
                    $total_cost,
                    $listing_type
                );
            }
        }
    
        if ($stmt->execute()) {
            error_log("Item added to cart successfully");
            echo json_encode([
                'success' => true,
                'message' => htmlspecialchars($product['product_name']) . ' added to cart successfully'
            ]);
        } else {
            error_log("Failed to add item to cart: " . $stmt->error);
            echo json_encode(['error' => 'Failed to add item to cart: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }
    
    error_log("==================== ADD TO CART COMPLETED ====================");
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['id'];

// Fetch product details
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = "SELECT p.*, u.Username as product_seller 
          FROM product p
          JOIN users u ON p.product_seller_id = u.Id
          WHERE p.id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Check if the product exists
if (!$product) {
    die("Product not found");
}

// Check if current user is the product seller
$is_owner = ($product['product_seller'] === $username);

// Create comments table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS product_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
)";
$con->query($create_table_query);

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment_text = trim($_POST['comment_text']);
    if (!empty($comment_text)) {
        $comment_query = "INSERT INTO product_comments (product_id, user_id, username, comment_text) VALUES (?, ?, ?, ?)";
        $comment_stmt = $con->prepare($comment_query);
        $comment_stmt->bind_param("iiss", $product_id, $user_id, $username, $comment_text);
        
        if ($comment_stmt->execute()) {
            echo "<script>window.location.href = window.location.href;</script>";
            exit();
        }
    }
}

// Fetch comments for this product
$comments_query = "SELECT * FROM product_comments WHERE product_id = ? ORDER BY created_at DESC";
$comments_stmt = $con->prepare($comments_query);
$comments_stmt->bind_param("i", $product_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/product_details.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
        }
        .product-image {
            text-align: center;
            background: transparent;
            cursor: pointer;
        }
        .product-image img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .product-image img:hover {
            transform: scale(1.02);
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90vh;
            margin: auto;
            display: block;
            object-fit: contain;
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .close-modal:hover {
            color: #bbb;
        }
        @keyframes zoom {
            from {transform: scale(0)}
            to {transform: scale(1)}
        }
        .modal-content {
            animation-name: zoom;
            animation-duration: 0.6s;
        }
        .product-info {
            background: transparent;
            padding: 25px;
            border-radius: 10px;
        }
        .info-item {
            display: block;
            margin-bottom: 20px;
        }
        .label {
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
            font-size: 16px;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .value {
            display: block;
            color: #333;
            font-size: 15px;
            line-height: 1.6;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .price {
            font-size: 24px;
            color: #2c3e50;
            font-weight: bold;
        }
        .rent-section {
            grid-column: 1 / -1;
            background: transparent;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .rent-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
            font-weight: bold;
            color: #2c3e50;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .form-group input {
            padding: 10px;
            border: 1px solid rgba(221, 221, 221, 0.7);
            background: rgba(255, 255, 255, 0.4);
            border-radius: 5px;
            font-size: 16px;
        }
        .button-group {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            background: rgba(76, 68, 182, 0.808);
            color: white;
        }
        .rent-btn {
            background: rgba(76, 68, 182, 0.808);
            color: white;
        }
        .negotiate-btn {
            background: rgba(76, 68, 182, 0.808);
            color: white;
            text-decoration: none;
        }
        .success {
            color: #1b5e20;
            background: rgba(232, 245, 233, 0.4);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .error {
            color: #b71c1c;
            background: rgba(255, 235, 238, 0.4);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-shadow: 1px 1px 1px rgba(255, 255, 255, 0.5);
        }
        .availability-calendar {
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
        .discount-info {
            margin: 10px 0;
            padding: 10px;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 5px;
            color: #4CAF50;
        }
        .date-range-picker {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .error-message {
            color: #f44336;
            margin: 5px 0;
            font-size: 0.9em;
        }
        .success-message {
            color: #4CAF50;
            margin: 5px 0;
            font-size: 0.9em;
        }
    </style>
    <title><?php echo htmlspecialchars($product['product_name']); ?></title>
</head>
<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
<?php include("php/header.php"); ?>
    <h1 style="color: #333; text-align: center; margin: 20px 0;"><?php echo htmlspecialchars($product['product_name']); ?></h1>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <div class="container">
        <!-- Product Image -->
        <div class="product-image">
            <img src="<?php echo htmlspecialchars($product['product_img']); ?>" 
                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                 onclick="openModal(this.src)">
        </div>
        
        <!-- Product Information -->
        <div class="product-info">
            <span class="info-item">
                <span class="label">Product ID:</span>
                <span class="value"><?php echo htmlspecialchars($product['id']); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Category:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_category']); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Description:</span>
                <span class="value"><?php echo nl2br(htmlspecialchars($product['product_description'])); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Quantity Available:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_quantity']); ?></span>
            </span>
            
            <span class="info-item">
                <span class="label">Listing Type:</span>
                <span class="value"><?php echo ucfirst(htmlspecialchars($product['listing_type'])); ?></span>
            </span>
            
            <span class="info-item">
                <?php if ($product['listing_type'] === 'sell'): ?>
                    <span class="label">Price:</span>
                    <span class="value price">$<?php echo number_format($product['product_cost'], 2); ?></span>
                <?php else: ?>
                    <span class="label">Rental Rates:</span>
                    <div class="value rental-rates">
                        <?php 
                        $has_rates = false;
                        if ($product['daily_rate']): 
                            $has_rates = true;
                        ?>
                            <div class="rate-item">
                                <strong>Daily Rate:</strong> $<?php echo number_format($product['daily_rate'], 2); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['weekly_rate']): 
                            $has_rates = true;
                        ?>
                            <div class="rate-item">
                                <strong>Weekly Rate:</strong> $<?php echo number_format($product['weekly_rate'], 2); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['monthly_rate']): 
                            $has_rates = true;
                        ?>
                            <div class="rate-item">
                                <strong>Monthly Rate:</strong> $<?php echo number_format($product['monthly_rate'], 2); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$has_rates): ?>
                            <div class="rate-item">Contact seller for rental rates</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </span>
            
            <span class="info-item">
                <span class="label">Seller:</span>
                <span class="value"><?php echo htmlspecialchars($product['product_seller']); ?></span>
            </span>

            <?php if (!$is_owner): ?>
                <!-- Add to Cart Section -->
                <div class="cart-section">
                    <form method="post" class="cart-form" id="cartForm" onsubmit="return addToCart(event)">
                        <!-- Hidden rental fields if this is a rental product -->
                        <?php if ($product['listing_type'] === 'rent'): ?>
                            <div class="rental-options" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                                <h4>Rental Options</h4>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="duration_unit">Rental Period:</label>
                                    <select name="duration_unit" id="duration_unit" class="form-control" onchange="updateRentalPeriod()" style="width: 100%; padding: 8px; margin-top: 5px;">
                                        <option value="daily" <?php echo isset($product['daily_rate']) && $product['daily_rate'] > 0 ? '' : 'disabled'; ?>>
                                            Daily ($<?php echo isset($product['daily_rate']) && $product['daily_rate'] > 0 ? number_format($product['daily_rate'], 2) : 'N/A'; ?>)
                                        </option>
                                        <option value="weekly" selected>
                                            Weekly ($<?php echo isset($product['weekly_rate']) && $product['weekly_rate'] > 0 ? number_format($product['weekly_rate'], 2) : number_format($product['product_cost'], 2); ?>)
                                        </option>
                                        <option value="monthly" <?php echo isset($product['monthly_rate']) && $product['monthly_rate'] > 0 ? '' : 'disabled'; ?>>
                                            Monthly ($<?php echo isset($product['monthly_rate']) && $product['monthly_rate'] > 0 ? number_format($product['monthly_rate'], 2) : 'N/A'; ?>)
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="rental_start_date">Start Date:</label>
                                    <input type="date" name="rental_start_date" id="rental_start_date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" 
                                           onchange="updateRentalDuration()" style="width: 100%; padding: 8px; margin-top: 5px;">
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="rental_end_date">End Date:</label>
                                    <input type="date" name="rental_end_date" id="rental_end_date" class="form-control" 
                                           value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                           onchange="updateRentalDuration()" style="width: 100%; padding: 8px; margin-top: 5px;">
                                </div>
                                
                                <div class="rental-summary" style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                    <p><strong>Duration:</strong> <span id="duration_display">7</span> days</p>
                                    <input type="hidden" name="total_days" id="total_days" value="7">
                                    
                                    <p><strong>Rate:</strong> $<span id="rate_display">
                                        <?php echo isset($product['weekly_rate']) && $product['weekly_rate'] > 0 
                                            ? number_format($product['weekly_rate'], 2) 
                                            : number_format($product['product_cost'], 2); ?>
                                    </span></p>
                                    <input type="hidden" name="product_cost" id="product_cost" 
                                           value="<?php echo isset($product['weekly_rate']) && $product['weekly_rate'] > 0 
                                                    ? $product['weekly_rate'] 
                                                    : $product['product_cost']; ?>">
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="product_cost" value="<?php echo $product['product_cost']; ?>">
                        <?php endif; ?>

                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                        
                        <!-- Quantity selection for both purchase and rental -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="quantity" style="display: block; margin-bottom: 10px;">Quantity:</label>
                            <div class="quantity-controls" style="display: flex; align-items: center;">
                                <button type="button" onclick="decrementQuantity()" class="quantity-btn" style="width: 30px; height: 30px;">-</button>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['product_quantity']; ?>" required 
                                       style="width: 60px; text-align: center; margin: 0 10px;">
                                <button type="button" onclick="incrementQuantity()" class="quantity-btn" style="width: 30px; height: 30px;">+</button>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_to_cart" class="btn"
                                style="width: 100%; margin-top: 10px;">
                            Add to Cart
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Comments Section - Moved outside the main container -->
            <div class="comments-container">
                <div class="comments-section">
                    <h2>Comments</h2>
                    
                    <!-- Add Comment Form -->
                    <form method="post" class="comment-form">
                        <div class="form-group">
                            <label for="comment_text">Add a Comment:</label>
                            <textarea name="comment_text" id="comment_text" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="comment" class="btn comment-btn">Post Comment</button>
                    </form>

                    <!-- Display Comments -->
                    <div class="comments-list">
                        <?php if ($comments_result->num_rows > 0): ?>
                            <?php while ($comment = $comments_result->fetch_assoc()): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span>
                                        <span class="comment-date"><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></span>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo htmlspecialchars($comment['comment_text']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="no-comments">No comments yet. Be the first to comment!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                // Image modal functionality
                function openModal(imgSrc) {
                    const modal = document.getElementById('imageModal');
                    const modalImg = document.getElementById('modalImage');
                    modal.style.display = 'flex';
                    modalImg.src = imgSrc;
                }

                // Close modal when clicking the X
                document.querySelector('.close-modal').onclick = function() {
                    document.getElementById('imageModal').style.display = 'none';
                }

                // Close modal when clicking outside the image
                document.getElementById('imageModal').onclick = function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                }

                // Close modal with escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        document.getElementById('imageModal').style.display = 'none';
                    }
                });

                function updateRentalPeriod() {
                    const durationUnitSelect = document.getElementById('duration_unit');
                    const rateDisplay = document.getElementById('rate_display');
                    const productCostInput = document.getElementById('product_cost');
                    const durationUnit = durationUnitSelect.value;

                    if (durationUnit === 'daily') {
                        rateDisplay.textContent = '<?php echo isset($product['daily_rate']) && $product['daily_rate'] > 0 ? number_format($product['daily_rate'], 2) : 'N/A'; ?>';
                        productCostInput.value = '<?php echo isset($product['daily_rate']) && $product['daily_rate'] > 0 ? $product['daily_rate'] : '0'; ?>';
                    } else if (durationUnit === 'weekly') {
                        rateDisplay.textContent = '<?php echo isset($product['weekly_rate']) && $product['weekly_rate'] > 0 ? number_format($product['weekly_rate'], 2) : number_format($product['product_cost'], 2); ?>';
                        productCostInput.value = '<?php echo isset($product['weekly_rate']) && $product['weekly_rate'] > 0 ? $product['weekly_rate'] : $product['product_cost']; ?>';
                    } else if (durationUnit === 'monthly') {
                        rateDisplay.textContent = '<?php echo isset($product['monthly_rate']) && $product['monthly_rate'] > 0 ? number_format($product['monthly_rate'], 2) : 'N/A'; ?>';
                        productCostInput.value = '<?php echo isset($product['monthly_rate']) && $product['monthly_rate'] > 0 ? $product['monthly_rate'] : '0'; ?>';
                    }
                }

                function updateRentalDuration() {
                    const startDateInput = document.getElementById('rental_start_date');
                    const endDateInput = document.getElementById('rental_end_date');
                    const durationDisplay = document.getElementById('duration_display');
                    const totalDaysInput = document.getElementById('total_days');

                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);

                    let duration = Math.round((endDate - startDate) / (1000 * 3600 * 24));

                    durationDisplay.textContent = duration;
                    totalDaysInput.value = duration;
                }

                function incrementQuantity() {
                    const quantityInput = document.getElementById('quantity');
                    const maxQuantity = <?php echo $product['product_quantity']; ?>;
                    let currentValue = parseInt(quantityInput.value);
                    
                    if (currentValue < maxQuantity) {
                        quantityInput.value = currentValue + 1;
                    }
                }
                
                function decrementQuantity() {
                    const quantityInput = document.getElementById('quantity');
                    let currentValue = parseInt(quantityInput.value);
                    
                    if (currentValue > 1) {
                        quantityInput.value = currentValue - 1;
                    }
                }

                function addToCart(event) {
                    event.preventDefault();
                    
                    // Create FormData from the form
                    const form = event.target;
                    const formData = new FormData(form);
                    
                    // Get product type to see if it's a rental
                    const isRental = <?php echo ($product['listing_type'] === 'rent') ? 'true' : 'false'; ?>;
                    
                    // Validate rental fields if this is a rental product
                    if (isRental) {
                        const startDateInput = formData.get('rental_start_date');
                        const endDateInput = formData.get('rental_end_date');
                        const durationUnit = formData.get('duration_unit');
                        
                        if (!startDateInput || !endDateInput) {
                            alert('Please select both rental start and end dates');
                            return false;
                        }
                        
                        // Get today's date in YYYY-MM-DD format (server time)
                        const serverToday = '<?php echo date("Y-m-d"); ?>';
                        
                        console.log('Start Date:', startDateInput);
                        console.log('Today (Server):', serverToday);
                        
                        // Compare dates as strings in YYYY-MM-DD format
                        if (startDateInput < serverToday) {
                            alert('Rental start date cannot be in the past');
                            return false;
                        }
                        
                        if (endDateInput <= startDateInput) {
                            alert('Rental end date must be after the start date');
                            return false;
                        }
                        
                        // Calculate duration in days
                        const start = new Date(startDateInput);
                        const end = new Date(endDateInput);
                        const durationMs = end - start;
                        const durationDays = Math.ceil(durationMs / (1000 * 60 * 60 * 24));
                        
                        if (durationDays <= 0) {
                            alert('Invalid rental duration. Please select different dates.');
                            return false;
                        }
                        
                        // Check if rental duration exceeds maximum allowed period (21 days / 3 weeks)
                        if (durationUnit === 'daily' && durationDays > 12) {
                            alert('Maximum rental period for daily rentals is 12 days. Please adjust your dates.');
                            return false;
                        } else if (durationUnit === 'weekly' && durationDays > 21) {
                            alert('Maximum rental period for weekly rentals is 21 days (3 weeks). Please adjust your dates.');
                            return false;
                        } else if (durationUnit === 'monthly' && durationDays > 122) {
                            alert('Maximum rental period for monthly rentals is 122 days (4 months). Please adjust your dates.');
                            return false;
                        }
                        
                        formData.set('total_days', durationDays);
                        console.log(`Rental duration: ${durationDays} days, unit: ${durationUnit}`);
                    }
                    
                    // Get current quantity
                    const quantity = parseInt(document.getElementById('quantity').value);
                    if (isNaN(quantity) || quantity <= 0) {
                        alert('Please enter a valid quantity');
                        return false;
                    }
                    
                    // Get submit button and show loading state
                    const submitBtn = document.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = 'Adding to Cart...';
                    submitBtn.disabled = true;
                    
                    fetch('product_details.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Reset button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        
                        if (data.error) {
                            alert(data.error);
                        } else if (data.success) {
                            // Show success message
                            const successMessage = document.createElement('div');
                            successMessage.className = 'alert alert-success';
                            successMessage.style.position = 'fixed';
                            successMessage.style.top = '20px';
                            successMessage.style.left = '50%';
                            successMessage.style.transform = 'translateX(-50%)';
                            successMessage.style.padding = '15px 20px';
                            successMessage.style.borderRadius = '5px';
                            successMessage.style.backgroundColor = '#d4edda';
                            successMessage.style.color = '#155724';
                            successMessage.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                            successMessage.style.zIndex = '1000';
                            successMessage.innerHTML = `<strong>Success!</strong> ${data.message}`;
                            document.body.appendChild(successMessage);
                            
                            // Remove after 3 seconds
                            setTimeout(() => {
                                successMessage.remove();
                            }, 3000);
                            
                            // Update cart count if applicable
                            const cartCountElements = document.querySelectorAll('.cart-count');
                            if (cartCountElements.length > 0) {
                                // Fetch updated cart count
                                fetch('admin_dashboard.php?action=get_cart_count')
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.count !== undefined) {
                                            cartCountElements.forEach(element => {
                                                element.textContent = data.count;
                                                element.style.display = data.count > 0 ? 'inline-block' : 'none';
                                            });
                                        }
                                    })
                                    .catch(error => console.error('Error updating cart count:', error));
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        alert('An error occurred while adding to cart. Please try again.');
                    });
                    
                    return false;
                }
            </script>
        </body>
    </html>