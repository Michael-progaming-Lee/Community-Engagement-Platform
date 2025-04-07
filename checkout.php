<?php
session_start();
include("php/config.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// Function to process checkout
function processCheckout($con, $user_id, $items_to_checkout, $selected_total) {
    try {
        // Enable error reporting for detailed debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Start a transaction
        mysqli_begin_transaction($con);
        
        // 1. Deduct the total cost from the user's balance
        // Use COALESCE to handle NULL values in AccountBalance
        $balance_query = "UPDATE users SET AccountBalance = COALESCE(AccountBalance, 0) - ? WHERE Id = ?";
        $balance_stmt = $con->prepare($balance_query);
        
        if ($balance_stmt === false) {
            throw new Exception("Error preparing balance update: " . $con->error);
        }
        
        $balance_stmt->bind_param("di", $selected_total, $user_id);
        
        if (!$balance_stmt->execute()) {
            throw new Exception("Error updating balance: " . $balance_stmt->error);
        }
        
        // Check if purchase_history table exists
        $table_check = mysqli_query($con, "SHOW TABLES LIKE 'purchase_history'");
        
        // Create the purchase_history table if it doesn't exist
        if (mysqli_num_rows($table_check) == 0) {
            $create_table_query = "CREATE TABLE IF NOT EXISTS purchase_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                buyer_id INT NOT NULL,
                product_id INT NOT NULL,
                status VARCHAR(50) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                rental_start_date DATE NULL,
                rental_end_date DATE NULL,
                rental_duration INT NULL,
                duration_unit VARCHAR(20) NULL
            )";
            
            if (!mysqli_query($con, $create_table_query)) {
                throw new Exception("Error creating purchase_history table: " . mysqli_error($con));
            }
        }
        
        // Check if rental_products table exists
        $rental_table_check = mysqli_query($con, "SHOW TABLES LIKE 'rental_products'");
        
        // Create the rental_products table if it doesn't exist
        if (mysqli_num_rows($rental_table_check) == 0) {
            $create_rental_table_query = "CREATE TABLE IF NOT EXISTS rental_products (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id INT(11) NOT NULL,
                buyer_id INT(11) NOT NULL,
                seller_id INT(11) NOT NULL,
                rental_price DECIMAL(10,2) NOT NULL,
                rental_start_date DATE NOT NULL,
                rental_end_date DATE NOT NULL,
                rental_duration INT(11) NOT NULL,
                duration_unit VARCHAR(20) NOT NULL DEFAULT 'days',
                status VARCHAR(20) NOT NULL DEFAULT 'rented',
                return_date DATE NULL,
                late_fee DECIMAL(10,2) DEFAULT 0.00,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX buyer_idx (buyer_id),
                INDEX product_idx (product_id),
                INDEX status_idx (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!mysqli_query($con, $create_rental_table_query)) {
                throw new Exception("Error creating rental_products table: " . mysqli_error($con));
            }
        }
        
        // Check if notifications table exists
        $notifications_table_check = mysqli_query($con, "SHOW TABLES LIKE 'notifications'");
        
        // Create the notifications table if it doesn't exist
        if (mysqli_num_rows($notifications_table_check) == 0) {
            $create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                related_id INT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (type),
                INDEX (is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!mysqli_query($con, $create_notifications_table)) {
                throw new Exception("Error creating notifications table: " . mysqli_error($con));
            }
        }
        
        // Check if delivery_status table exists
        $delivery_table_check = mysqli_query($con, "SHOW TABLES LIKE 'delivery_status'");
        
        // Create the delivery_status table if it doesn't exist
        if (mysqli_num_rows($delivery_table_check) == 0) {
            $create_delivery_status_table = "CREATE TABLE IF NOT EXISTS delivery_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                purchase_id INT NOT NULL COMMENT 'ID from purchase_history table',
                product_id INT NOT NULL,
                buyer_id INT NOT NULL,
                seller_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                is_rental BOOLEAN DEFAULT FALSE,
                sent_for_delivery BOOLEAN DEFAULT FALSE,
                sent_date TIMESTAMP NULL,
                received_by_buyer BOOLEAN DEFAULT FALSE,
                received_date TIMESTAMP NULL,
                payment_processed BOOLEAN DEFAULT FALSE,
                status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, shipped, delivered, cancelled',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (purchase_id),
                INDEX (buyer_id),
                INDEX (seller_id),
                INDEX (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!mysqli_query($con, $create_delivery_status_table)) {
                throw new Exception("Error creating delivery status table: " . mysqli_error($con));
            }
        }
        
        // Get buyer information for notifications
        $buyer_query = "SELECT Username FROM users WHERE Id = ?";
        $buyer_stmt = $con->prepare($buyer_query);
        
        if ($buyer_stmt === false) {
            throw new Exception("Error preparing buyer query: " . $con->error);
        }
        
        $buyer_stmt->bind_param("i", $user_id);
        
        if (!$buyer_stmt->execute()) {
            throw new Exception("Error getting buyer information: " . $buyer_stmt->error);
        }
        
        $buyer_result = $buyer_stmt->get_result();
        $buyer_data = $buyer_result->fetch_assoc();
        $buyer_name = $buyer_data['Username'];
        
        // 2. Add items to purchase history and update product quantities
        foreach ($items_to_checkout as $item) {
            // Determine if this is a rental or a purchase based on whether rental information is present
            $is_rental = isset($item['rental_duration']) && $item['rental_duration'] > 0;
            $status = $is_rental ? 'rented' : 'bought';
            
            // Set rental information if applicable
            $rental_start = null;
            $rental_end = null;
            $rental_duration = null;
            $duration_unit = null;
            
            if ($is_rental) {
                // Use the rental dates directly from the users_cart table
                $rental_start = $item['rental_start_date'];
                $rental_end = $item['rental_end_date'];
                $rental_duration = $item['rental_duration'] ?? 7; // Default to 7 if not specified
                $duration_unit = $item['duration_unit'] ?? 'days'; // Default to days
                
                // Only calculate the end date if it's not already set
                if (empty($rental_end)) {
                    $rental_start = date('Y-m-d');
                    $rental_end = date('Y-m-d', strtotime("+{$rental_duration} {$duration_unit}"));
                }
            }
            
            // Add to purchase history
            $history_query = "INSERT INTO purchase_history (buyer_id, product_id, status, price, rental_start_date, rental_end_date, rental_duration, duration_unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $history_stmt = $con->prepare($history_query);
            
            if ($history_stmt === false) {
                throw new Exception("Error preparing history insert: " . $con->error);
            }
            
            $history_stmt->bind_param("iisdsssi", $user_id, $item['product_id'], $status, $item['product_total'], $rental_start, $rental_end, $rental_duration, $duration_unit);
            
            if (!$history_stmt->execute()) {
                throw new Exception("Error adding to purchase history: " . $history_stmt->error);
            }
            
            $purchase_history_id = $con->insert_id;
            
            // Get the seller ID from the product table
            $seller_query = "SELECT product_seller_id, product_name FROM product WHERE id = ?";
            $seller_stmt = $con->prepare($seller_query);
            
            if ($seller_stmt === false) {
                throw new Exception("Error preparing seller query: " . $con->error);
            }
            
            $seller_stmt->bind_param("i", $item['product_id']);
            
            if (!$seller_stmt->execute()) {
                throw new Exception("Error getting seller ID: " . $seller_stmt->error);
            }
            
            $seller_result = $seller_stmt->get_result();
            $seller_data = $seller_result->fetch_assoc();
            
            if (!$seller_data || !isset($seller_data['product_seller_id'])) {
                throw new Exception("Could not find seller ID for product ID: " . $item['product_id']);
            }
            
            $seller_id = $seller_data['product_seller_id'];
            $product_name = $seller_data['product_name'];
            
            // Create a delivery status record
            $delivery_query = "INSERT INTO delivery_status (
                purchase_id, product_id, buyer_id, seller_id, amount, is_rental, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $delivery_stmt = $con->prepare($delivery_query);
            
            if ($delivery_stmt === false) {
                throw new Exception("Error preparing delivery status insert: " . $con->error);
            }
            
            $delivery_stmt->bind_param("iiiidi", $purchase_history_id, $item['product_id'], $user_id, $seller_id, $item['product_total'], $is_rental);
            
            if (!$delivery_stmt->execute()) {
                throw new Exception("Error adding to delivery status: " . $delivery_stmt->error);
            }
            
            $delivery_id = $con->insert_id;
            
            // Create notification for seller
            $transaction_type = $is_rental ? 'rental' : 'purchase';
            $notification_message = "New {$transaction_type}: {$buyer_name} has made a {$transaction_type} of your product '{$product_name}' for $" . number_format($item['product_total'], 2) . ". Please arrange delivery.";
            
            $notification_query = "INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'new_sale', ?, ?)";
            $notification_stmt = $con->prepare($notification_query);
            
            if ($notification_stmt === false) {
                throw new Exception("Error preparing notification insert: " . $con->error);
            }
            
            $notification_stmt->bind_param("isi", $seller_id, $notification_message, $delivery_id);
            
            if (!$notification_stmt->execute()) {
                throw new Exception("Error adding seller notification: " . $notification_stmt->error);
            }
            
            // If it's a rental, also add to rental_products table
            if ($is_rental) {
                // Log information before inserting rental
                error_log("Attempting to insert rental product - Product ID: " . $item['product_id'] . 
                         ", Buyer ID: " . $user_id . 
                         ", Seller ID: " . $seller_id . 
                         ", Price: " . $item['product_total'] . 
                         ", Start Date: " . $rental_start . 
                         ", End Date: " . $rental_end);
                
                // Manually construct the query to avoid type binding issues
                $direct_query = "INSERT INTO rental_products 
                                (product_id, buyer_id, seller_id, rental_price, 
                                rental_start_date, rental_end_date, rental_duration, 
                                duration_unit, status, notes) 
                                VALUES 
                                ({$item['product_id']}, {$user_id}, {$seller_id}, {$item['product_total']}, 
                                '{$rental_start}', '{$rental_end}', {$rental_duration}, 
                                '{$duration_unit}', 'rented', 'Purchase History ID: {$purchase_history_id}')";
                
                $insert_result = mysqli_query($con, $direct_query);
                
                if (!$insert_result) {
                    error_log("Error adding to rental_products: " . mysqli_error($con));
                    throw new Exception("Error adding to rental_products: " . mysqli_error($con) . " Query: " . $direct_query);
                }
                
                $rental_id = mysqli_insert_id($con);
                error_log("Successfully inserted rental product with ID: " . $rental_id);
                
                // Verify the insert worked by checking if we can retrieve the record
                $verify_query = "SELECT id FROM rental_products WHERE id = " . $rental_id;
                $verify_result = mysqli_query($con, $verify_query);
                
                if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
                    error_log("Failed to verify rental product insertion - cannot find ID: " . $rental_id);
                    throw new Exception("Failed to verify rental product insertion");
                } else {
                    error_log("Verified rental product ID " . $rental_id . " was inserted correctly");
                }
            }
            
            // Update product quantity
            $quantity_query = "UPDATE product SET product_quantity = product_quantity - ? WHERE id = ?";
            $quantity_stmt = $con->prepare($quantity_query);
            
            if ($quantity_stmt === false) {
                throw new Exception("Error preparing quantity update: " . $con->error);
            }
            
            $quantity_stmt->bind_param("ii", $item['product_quantity'], $item['product_id']);
            
            if (!$quantity_stmt->execute()) {
                throw new Exception("Error updating product quantity: " . $quantity_stmt->error);
            }
            
            // Remove item from cart
            $delete_query = "DELETE FROM users_cart WHERE Id = ?";
            $delete_stmt = $con->prepare($delete_query);
            
            if ($delete_stmt === false) {
                throw new Exception("Error preparing cart delete: " . $con->error);
            }
            
            $delete_stmt->bind_param("i", $item['Id']);
            
            if (!$delete_stmt->execute()) {
                throw new Exception("Error removing item from cart: " . $delete_stmt->error);
            }
        }
        
        // Commit the transaction
        mysqli_commit($con);
        
        return [
            'success' => true,
            'message' => "Checkout successful!"
        ];
    } catch (Exception $e) {
        // Rollback the transaction on error
        mysqli_rollback($con);
        
        // Log the error
        error_log("Checkout process error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ];
    }
}

// Handle AJAX request from process_cashout.php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selectedProducts'])) {
    $user_id = $_SESSION['id'];
    $selected_products = json_decode($_POST['selectedProducts']);
    
    if (empty($selected_products)) {
        $_SESSION['error_message'] = "No products selected";
        header("Location: users_cart.php");
        exit();
    }

    // Calculate total cost of selected items
    $total_cost = 0;
    $items_to_process = [];
    
    // Prepare and execute query to get selected items
    $placeholders = str_repeat('?,', count($selected_products) - 1) . '?';
    $query = "SELECT c.*, p.listing_type FROM users_cart c 
             JOIN product p ON c.product_id = p.id 
             WHERE c.UserID = ? AND c.product_id IN ($placeholders)";
    
    $stmt = $con->prepare($query);
    $types = str_repeat('i', count($selected_products) + 1);
    $params = array_merge([$types, $user_id], $selected_products);
    call_user_func_array([$stmt, 'bind_param'], $params);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $total_cost += $row['product_total'];
        $items_to_process[] = $row;
    }

    // Check if user has sufficient balance
    $user_query = mysqli_query($con, "SELECT AccountBalance FROM users WHERE Id = $user_id");
    $user_data = mysqli_fetch_assoc($user_query);
    $current_balance = $user_data['AccountBalance'];
    
    if ($current_balance < $total_cost) {
        $_SESSION['error_message'] = "Insufficient balance";
        header("Location: users_cart.php");
        exit();
    }

    // Process checkout
    $checkout_result = processCheckout($con, $user_id, $items_to_process, $total_cost);
    
    if ($checkout_result['success']) {
        $_SESSION['success_message'] = "Successfully purchased " . count($items_to_process) . " items!";
    } else {
        $_SESSION['error_message'] = $checkout_result['message'];
    }
    
    header("Location: users_cart.php");
    exit();
}

// Process checkout if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    // Get the selected items from the form
    if (!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
        $checkout_error = "No items selected for checkout.";
    } else {
        $selected_item_ids = $_POST['selected_items'];
        $items_to_checkout = [];
        $selected_total = 0;
        
        // Retrieve the user's current cart items
        $cart_query = "SELECT uc.*, p.product_name, p.product_cost, p.product_img, p.product_seller_id, p.product_quantity as available_quantity,
                        uc.rental_duration, uc.duration_unit, uc.rental_start_date, uc.rental_end_date
                       FROM users_cart uc
                       JOIN product p ON uc.product_id = p.id
                       WHERE uc.UserID = ?";
        $cart_stmt = $con->prepare($cart_query);
        
        if ($cart_stmt === false) {
            die("Error preparing cart query: " . $con->error);
        }
        
        $cart_stmt->bind_param("i", $_SESSION['id']);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        
        while ($item = $cart_result->fetch_assoc()) {
            // Check if this item was selected
            if (in_array($item['Id'], $selected_item_ids)) {
                // Validate available quantity
                if ($item['available_quantity'] < $item['product_quantity']) {
                    $checkout_error = "Not enough quantity available for " . $item['product_name'];
                    break;
                }
                
                // Use the stored product_total directly from the cart
                // This ensures consistency between cart and checkout prices
                $items_to_checkout[] = $item;
                $selected_total += $item['product_total'];
            }
        }
        
        // Get user's current balance
        $user_query = "SELECT COALESCE(AccountBalance, 0.00) as AccountBalance FROM users WHERE Id = ?";
        $user_stmt = $con->prepare($user_query);
        $user_stmt->bind_param("i", $_SESSION['id']);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $current_balance = $user_data['AccountBalance'];
        
        // Check if balance is sufficient
        if ($current_balance < $selected_total) {
            $checkout_error = "Insufficient balance. You need $" . number_format($selected_total - $current_balance, 2) . " more.";
        } else if (!isset($checkout_error)) {
            // Process the checkout
            $checkout_result = processCheckout($con, $_SESSION['id'], $items_to_checkout, $selected_total);
            
            if ($checkout_result['success']) {
                $_SESSION['checkout_success'] = $checkout_result['message'];
                header("Location: purchase_history.php");
                exit();
            } else {
                $checkout_error = $checkout_result['message'];
            }
        }
    }
}

$user_id = $_SESSION['id'];

// Get user data including balance
$user_query = mysqli_query($con, "SELECT *, COALESCE(AccountBalance, 0.00) as AccountBalance FROM users WHERE Id=$user_id");
$user_data = mysqli_fetch_assoc($user_query);
$current_balance = $user_data['AccountBalance'];

// Get cart items
$cart_query = "SELECT uc.*, p.product_name, p.product_cost, p.product_img, p.product_seller_id, u.Username as seller_name,
               uc.rental_duration, uc.duration_unit, uc.rental_start_date, uc.rental_end_date
               FROM users_cart uc
               JOIN product p ON uc.product_id = p.id
               JOIN users u ON p.product_seller_id = u.Id
               WHERE uc.UserID = ?
               ORDER BY uc.Id DESC";
$cart_stmt = $con->prepare($cart_query);
if ($cart_stmt === false) {
    die("Error preparing cart query: " . $con->error);
}
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

$cart_items = [];
$cart_total = 0;

while ($item = $cart_result->fetch_assoc()) {
    // Use the stored product_total directly from the cart instead of recalculating
    // This ensures consistency with the cart display
    $cart_total += $item['product_total'];
    $cart_items[] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Checkout</title>
    <style>
        body {
            background-image: url('Background Images/Home_Background.png');
            background-size: cover;
            background-position: top center;
            background-attachment: fixed;
            padding-bottom: 40px;
        }
        .page-container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .back-link {
            display: inline-block;
            color: #3498db;
            margin-bottom: 20px;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            color: #2980b9;
        }
        .checkout-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .balance-section {
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(248, 249, 250, 0.7);
            border-radius: 8px;
        }
        .balance-display {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .balance-label {
            font-weight: 600;
            color: #2c3e50;
        }
        .balance-amount {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 5px 15px;
            border-radius: 6px;
        }
        .balance-sufficient {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
        }
        .balance-insufficient {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        .add-funds-link {
            display: inline-block;
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .add-funds-link:hover {
            background: #2980b9;
            color: white;
            transform: translateY(-2px);
        }
        .balance-warning {
            color: #e74c3c;
            font-weight: 500;
            margin-top: 10px;
        }
        .order-summary {
            margin-bottom: 30px;
        }
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .cart-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2c3e50;
        }
        .checkout-items {
            margin-bottom: 30px;
        }
        .checkout-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(248, 249, 250, 0.5);
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .checkout-item:hover {
            background: rgba(240, 242, 245, 0.8);
        }
        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 5px;
            object-fit: cover;
            margin-right: 15px;
        }
        .item-details {
            flex: 1;
        }
        .item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .item-seller {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .item-meta {
            display: flex;
            gap: 15px;
        }
        .item-price, .item-quantity, .item-total {
            font-size: 0.9rem;
        }
        .item-price span, .item-quantity span, .item-total span {
            font-weight: 600;
        }
        .item-rental {
            display: inline-block;
            padding: 2px 8px;
            background: #3498db;
            color: white;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .item-select {
            flex-shrink: 0;
            margin-left: 15px;
        }
        .checkout-button {
            display: block;
            width: 100%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .checkout-button:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .checkout-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        .empty-cart {
            text-align: center;
            padding: 30px 0;
        }
        .empty-cart i {
            font-size: 3rem;
            color: #95a5a6;
            margin-bottom: 15px;
        }
        .empty-cart h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .shop-button {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .shop-button:hover {
            background: #2980b9;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>

    <div class="page-container">
        <a href="users_cart.php" class="back-link">
            <i class="fas fa-chevron-left"></i>
            Back to Cart
        </a>
        
        <div class="checkout-container">
            <h1 class="text-center mb-4">Checkout</h1>

            <div class="balance-section">
                <div class="balance-display">
                    <span class="balance-label">Your Balance:</span>
                    <span class="balance-amount <?php echo $current_balance >= $cart_total ? 'balance-sufficient' : 'balance-insufficient'; ?>">
                        $<?php echo number_format($current_balance, 2); ?>
                    </span>
                    
                    <?php if ($current_balance < $cart_total): ?>
                        <a href="edituserinfo.php" class="add-funds-link">
                            <i class="fas fa-plus-circle"></i> Add Funds
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($current_balance < $cart_total): ?>
                    <div class="balance-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Your balance is insufficient to complete the purchase. You need $<?php echo number_format($cart_total - $current_balance, 2); ?> more.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($checkout_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $checkout_error; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your Cart is Empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="home.php" class="shop-button">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <form method="post" id="checkout-form">
                    <div class="order-summary">
                        <div class="summary-header">
                            <h4 class="mb-0">Order Summary</h4>
                            <div class="cart-total">Total: $<?php echo number_format($cart_total, 2); ?></div>
                        </div>
                        
                        <div class="checkout-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="checkout-item">
                                    <input type="checkbox" name="selected_items[]" value="<?php echo $item['Id']; ?>" class="item-checkbox form-check-input" checked style="margin-right: 12px;">
                                    
                                    <img src="<?php echo htmlspecialchars($item['product_img']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image" style="margin-left: 8px; margin-right: 15px;">
                                    
                                    <div class="item-details">
                                        <div class="item-name">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                            <?php if (isset($item['rental_duration']) && $item['rental_duration'] > 0): ?>
                                                <span class="item-rental">Rental</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-seller">Seller: <?php echo htmlspecialchars($item['seller_name']); ?></div>
                                        
                                        <div class="item-meta">
                                            <?php if (!isset($item['rental_duration']) || $item['rental_duration'] <= 0): ?>
                                            <div class="item-price">
                                                Price: <span>$<?php echo number_format($item['product_cost'], 2); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="item-quantity">
                                                Quantity: <span><?php echo $item['product_quantity']; ?></span>
                                            </div>
                                            
                                            <div class="item-total">
                                                Total: <span>$<?php echo number_format($item['product_total'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="checkout" id="checkout-button" class="checkout-button" <?php echo $current_balance < $cart_total ? 'disabled' : ''; ?>>
                        <?php if ($current_balance < $cart_total): ?>
                            <i class="fas fa-lock"></i> Insufficient Balance
                        <?php else: ?>
                            <i class="fas fa-check-circle"></i> Complete Purchase
                        <?php endif; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const checkoutButton = document.getElementById('checkout-button');
            
            // Function to update the checkout button state
            function updateCheckoutButton() {
                let anyChecked = false;
                let totalSelected = 0;
                
                checkboxes.forEach(function(checkbox) {
                    if (checkbox.checked) {
                        anyChecked = true;
                        
                        // Find the price element in this item
                        const item = checkbox.closest('.checkout-item');
                        const totalElement = item.querySelector('.item-total span');
                        const totalText = totalElement.textContent.replace('$', '').trim();
                        const itemTotal = parseFloat(totalText.replace(',', ''));
                        
                        totalSelected += itemTotal;
                    }
                });
                
                // Get current balance
                const balanceElement = document.querySelector('.balance-amount');
                const balanceText = balanceElement.textContent.replace('$', '').trim();
                const currentBalance = parseFloat(balanceText.replace(',', ''));
                
                // Update button state
                if (anyChecked && currentBalance >= totalSelected) {
                    checkoutButton.disabled = false;
                    checkoutButton.innerHTML = '<i class="fas fa-check-circle"></i> Complete Purchase';
                } else if (!anyChecked) {
                    checkoutButton.disabled = true;
                    checkoutButton.innerHTML = '<i class="fas fa-times-circle"></i> Select Items';
                } else {
                    checkoutButton.disabled = true;
                    checkoutButton.innerHTML = '<i class="fas fa-lock"></i> Insufficient Balance';
                }
            }
            
            // Add event listeners to checkboxes
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', updateCheckoutButton);
            });
            
            // Initial check
            updateCheckoutButton();
        });
    </script>
</body>
</html>
