<?php
session_start();
require_once 'php/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id']; 

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Process rental return request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['rental_id']) || isset($_POST['purchase_ids']))) {
    // Process both single rental_id and multiple purchase_ids
    $purchase_ids = [];

    // Handle single rental_id from the form
    if (isset($_POST['rental_id']) && !empty($_POST['rental_id'])) {
        $purchase_ids[] = intval($_POST['rental_id']);
    }
    // Handle multiple purchase_ids (for compatibility or future use)
    elseif (isset($_POST['purchase_ids']) && is_array($_POST['purchase_ids']) && !empty($_POST['purchase_ids'])) {
        $purchase_ids = array_map('intval', $_POST['purchase_ids']);
    } 
    // No valid IDs provided
    else {
        $_SESSION['error_message'] = "No rentals selected for return.";
        header("Location: rental.php");
        exit();
    }

    $successful_returns = [];
    $failed_returns = [];
    $total_late_fees = 0.00;

    // Get current date for comparison and late fee calculation
    $current_date = date('Y-m-d');

    // Begin transaction to ensure data integrity
    mysqli_begin_transaction($con);

    try {
        foreach ($purchase_ids as $purchase_id) {
            // First, try to find the item in the rental_products table
            $rental_query = "SELECT rp.*, p.product_name, rp.seller_id as product_seller_id, 
                          rp.rental_price, rp.rental_end_date, rp.rental_start_date
                          FROM rental_products rp
                          JOIN product p ON rp.product_id = p.id
                          WHERE rp.id = ? AND rp.buyer_id = ? AND rp.status = 'rented'";
            
            $rental_stmt = mysqli_prepare($con, $rental_query);
            mysqli_stmt_bind_param($rental_stmt, "ii", $purchase_id, $user_id);
            mysqli_stmt_execute($rental_stmt);
            $rental_result = mysqli_stmt_get_result($rental_stmt);
            
            $found_in_rental_products = false;
            $rental_data = null;
            
            if (mysqli_num_rows($rental_result) > 0) {
                $found_in_rental_products = true;
                $rental_data = mysqli_fetch_assoc($rental_result);
            } else {
                // If not found in rental_products, try purchase_history as fallback
                $verify_query = "SELECT ph.*, p.product_name, p.product_seller_id, ph.price as rental_price, 
                              ph.rental_end_date, ph.rental_start_date
                              FROM purchase_history ph
                              JOIN product p ON ph.product_id = p.id
                              WHERE ph.id = ? AND ph.buyer_id = ? AND ph.status = 'rented'";

                $verify_stmt = mysqli_prepare($con, $verify_query);
                mysqli_stmt_bind_param($verify_stmt, "ii", $purchase_id, $user_id);
                mysqli_stmt_execute($verify_stmt);
                $result = mysqli_stmt_get_result($verify_stmt);

                if (mysqli_num_rows($result) === 0) {
                    // Skip this item and continue with the next one
                    $failed_returns[] = "Item #" . $purchase_id;
                    continue;
                }
                
                $rental_data = mysqli_fetch_assoc($result);
            }

            // Process the rental return, regardless of which table it came from
            $late_fee = 0.00;
            $is_overdue = false;
            
            // Check if the rental is overdue
            if ($rental_data['rental_end_date'] < $current_date) {
                $is_overdue = true;
                
                // Calculate late fee - $2 per day overdue
                $end_date = new DateTime($rental_data['rental_end_date']);
                $today = new DateTime($current_date);
                $days_overdue = $today->diff($end_date)->days;
                $late_fee = $days_overdue * 2.00; // $2 per day late fee
                
                // Cap late fee at the original rental price to be fair
                if ($late_fee > $rental_data['rental_price']) {
                    $late_fee = $rental_data['rental_price'];
                }
                
                // Add to the total late fees
                $total_late_fees += $late_fee;
            }
            
            // Update the rental item status based on where it was found
            if ($found_in_rental_products) {
                // Update the rental_products table - mark as returned
                $update_query = "UPDATE rental_products SET 
                               status = 'returned', 
                               return_date = ?, 
                               late_fee = ?,
                               notes = CONCAT(IFNULL(notes, ''), '\nReturned on: " . $current_date . "'),
                               updated_at = NOW()
                               WHERE id = ?";
                
                $update_stmt = mysqli_prepare($con, $update_query);
                mysqli_stmt_bind_param($update_stmt, "sdi", $current_date, $late_fee, $purchase_id);
                
                if (!mysqli_stmt_execute($update_stmt)) {
                    throw new Exception("Error updating rental product status: " . mysqli_error($con));
                }
                
                // Also update the purchase_history record if it exists
                $ph_query = "UPDATE purchase_history SET 
                            status = 'returned'
                            WHERE product_id = ? AND buyer_id = ? AND status = 'rented'";
                
                $ph_stmt = mysqli_prepare($con, $ph_query);
                mysqli_stmt_bind_param($ph_stmt, "ii", $rental_data['product_id'], $user_id);
                mysqli_stmt_execute($ph_stmt); // No need to check - this is just a secondary update
            } else {
                // Update just the purchase_history table
                $update_query = "UPDATE purchase_history SET status = 'returned' WHERE id = ?";
                $update_stmt = mysqli_prepare($con, $update_query);
                mysqli_stmt_bind_param($update_stmt, "i", $purchase_id);
                
                if (!mysqli_stmt_execute($update_stmt)) {
                    throw new Exception("Error updating purchase history status: " . mysqli_error($con));
                }
            }
            
            // If late fees apply, deduct from user's balance
            if ($late_fee > 0) {
                $fee_query = "UPDATE users SET AccountBalance = COALESCE(AccountBalance, 0) - ? WHERE Id = ?";
                $fee_stmt = mysqli_prepare($con, $fee_query);
                mysqli_stmt_bind_param($fee_stmt, "di", $late_fee, $user_id);
                
                if (!mysqli_stmt_execute($fee_stmt)) {
                    throw new Exception("Error deducting late fees: " . mysqli_error($con));
                }
            }
            
            // Increment product quantity in the product table to make it available again
            $increment_query = "UPDATE product SET product_quantity = product_quantity + 1 WHERE id = ?";
            $increment_stmt = mysqli_prepare($con, $increment_query);
            mysqli_stmt_bind_param($increment_stmt, "i", $rental_data['product_id']);
            
            if (!mysqli_stmt_execute($increment_stmt)) {
                throw new Exception("Error updating product quantity: " . mysqli_error($con));
            }
            
            // Send notification to the seller about the returned product
            $seller_id = isset($rental_data['product_seller_id']) ? $rental_data['product_seller_id'] : $rental_data['seller_id'];
            
            if ($seller_id) {
                // Create appropriate notification message based on whether there's a late fee
                if ($late_fee > 0) {
                    $notification_message = "Product '{$rental_data['product_name']}' has been returned by the renter. A late fee of $" . number_format($late_fee, 2) . " was charged.";
                    $notification_type = "rental_returned_late";
                } else {
                    $notification_message = "Product '{$rental_data['product_name']}' has been returned by the renter.";
                    $notification_type = "rental_returned";
                }
                
                // Insert notification for the seller
                $notification_query = "INSERT INTO notifications (user_id, type, message, related_id, is_read, created_at) 
                                      VALUES (?, ?, ?, ?, FALSE, NOW())";
                $notification_stmt = mysqli_prepare($con, $notification_query);
                mysqli_stmt_bind_param($notification_stmt, "issi", $seller_id, $notification_type, $notification_message, $rental_data['product_id']);
                
                if (!mysqli_stmt_execute($notification_stmt)) {
                    // Log the error but don't throw exception to avoid rolling back the entire transaction
                    error_log("Error sending notification to seller: " . mysqli_error($con));
                }
            }
            
            // Add to successful returns list
            $successful_returns[] = htmlspecialchars($rental_data['product_name']);
        }
        
        // Commit the transaction if everything was successful
        mysqli_commit($con);
        
        // Set success and/or warning messages
        if (!empty($successful_returns)) {
            if (count($successful_returns) === 1) {
                $_SESSION['success_message'] = "You have successfully returned: " . $successful_returns[0];
            } else {
                $_SESSION['success_message'] = "You have successfully returned " . count($successful_returns) . " items: " . implode(", ", $successful_returns);
            }
            
            if ($total_late_fees > 0) {
                $_SESSION['success_message'] .= ". Late fees of $" . number_format($total_late_fees, 2) . " have been deducted from your account.";
            }
        }
        
        if (!empty($failed_returns)) {
            $_SESSION['error_message'] = "Failed to return: " . implode(", ", $failed_returns) . ". These items may have already been returned or do not belong to you.";
        }
        
        // Redirect to refresh the page
        header("Location: rental.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback the transaction in case of errors
        mysqli_rollback($con);
        
        // Log the error and set error message
        error_log("Return rental error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while processing your return. Please try again or contact support.";
        
        // Redirect back to the rental management page
        header("Location: rental.php");
        exit();
    }
}

// Get user's current balance with COALESCE to handle NULL values
$balance_query = "SELECT *, COALESCE(AccountBalance, 0.00) as AccountBalance FROM users WHERE Id = ?";
$balance_stmt = mysqli_prepare($con, $balance_query);
if ($balance_stmt === false) {
    $error_message = "Error preparing balance query: " . mysqli_error($con);
}
mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);
$user_data = mysqli_fetch_assoc($balance_result);
$current_balance = $user_data['AccountBalance'];

// Define low balance threshold
$low_balance_threshold = 10.00; // Show warning when balance is below $10

// Initialize arrays for rental tracking
$active_rentals = [];
$overdue_rentals = [];

// Get current date for comparison
$current_date = date('Y-m-d');

// Debug info - check if tables exist
$table_check_query = "SHOW TABLES LIKE 'rental_products'";
$table_check_result = mysqli_query($con, $table_check_query);
if (mysqli_num_rows($table_check_result) == 0) {
    // Table doesn't exist
}

// Check if there are any records in the rental_products table
$count_query = "SELECT COUNT(*) as count FROM rental_products";
$count_result = mysqli_query($con, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$rental_products_count = $count_row['count'];

// Check if there are any rental records for the current user
$user_count_query = "SELECT COUNT(*) as count FROM rental_products WHERE buyer_id = $user_id";
$user_count_result = mysqli_query($con, $user_count_query);
$user_count_row = mysqli_fetch_assoc($user_count_result);
$user_rental_count = $user_count_row['count'];

// Direct database check (bypassing prepared statements for debugging)
$direct_check = mysqli_query($con, "SELECT * FROM rental_products WHERE buyer_id = $user_id");

// Query the rental_products table for active rentals with better debugging
$rental_query = "SELECT rp.*, p.product_name, p.product_img, u.Username as seller_name
                FROM rental_products rp
                JOIN product p ON rp.product_id = p.id
                JOIN users u ON rp.seller_id = u.Id
                WHERE rp.buyer_id = ? AND rp.status = 'rented'
                ORDER BY rp.rental_end_date ASC";

$rental_stmt = mysqli_prepare($con, $rental_query);
if ($rental_stmt === false) {
    $error_message = "Error preparing rental query: " . mysqli_error($con);
} else {
    mysqli_stmt_bind_param($rental_stmt, "i", $user_id);
    $execute_success = mysqli_stmt_execute($rental_stmt);
    
    if (!$execute_success) {
        $error_message = "Error executing rental query: " . mysqli_stmt_error($rental_stmt);
    } else {
        $rental_result = mysqli_stmt_get_result($rental_stmt);
        
        if ($rental_result === false) {
            $error_message = "Error getting result: " . mysqli_error($con);
        } else {
            // Query returned rows
            
            while ($rental = mysqli_fetch_assoc($rental_result)) {
                // Calculate days remaining
                $end_date = new DateTime($rental['rental_end_date']);
                $current = new DateTime();
                $interval = $current->diff($end_date);
                $days_remaining = $interval->invert ? -$interval->days : $interval->days;
                
                // Add rental status
                $rental['days_remaining'] = $days_remaining;
                $rental['is_overdue'] = $days_remaining < 0;
                
                // Add to appropriate array
                if ($rental['is_overdue']) {
                    $overdue_rentals[] = $rental;
                } else {
                    $active_rentals[] = $rental;
                }
            }
        }
    }
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
    <title>My Rentals</title>
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
        .rentals-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .balance-section {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(248, 249, 250, 0.7);
            border-radius: 8px;
        }
        .balance-label {
            font-weight: 600;
            color: #2c3e50;
            margin-right: 10px;
        }
        .balance-amount {
            font-size: 1.3rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 4px;
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
            margin-left: 15px;
            padding: 5px 10px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .add-funds-link:hover {
            background: #2980b9;
            color: white;
        }
        .section-heading {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .rental-card {
            background: rgba(248, 249, 250, 0.7);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }
        .rental-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .rental-image {
            width: 100px;
            height: 100px;
            border-radius: 6px;
            object-fit: cover;
            margin-right: 20px;
        }
        .rental-title {
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .rental-seller {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 5px;
        }
        .rental-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        .detail-item {
            flex: 1;
            min-width: 150px;
        }
        .detail-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        .detail-value {
            font-weight: 600;
            color: #2c3e50;
        }
        .rental-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .return-button {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .return-button:hover {
            background: #2980b9;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
        }
        .status-active {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
        }
        .status-overdue {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        .days-remaining {
            font-weight: 600;
        }
        .days-positive {
            color: #27ae60;
        }
        .days-warning {
            color: #f39c12;
        }
        .days-negative {
            color: #e74c3c;
        }
        .empty-rentals {
            text-align: center;
            padding: 50px 0;
        }
        .empty-rentals i {
            font-size: 3rem;
            color: #95a5a6;
            margin-bottom: 20px;
        }
        .empty-rentals h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .browse-button {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
            margin-top: 15px;
        }
        .browse-button:hover {
            background: #2980b9;
            color: white;
        }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>

    <div class="page-container">
        <div class="rentals-container">
            <h1 class="text-center mb-4">My Rentals</h1>
            
            <div class="balance-section">
                <span class="balance-label">Your Balance:</span>
                <span class="balance-amount <?php echo $current_balance >= $low_balance_threshold ? 'balance-sufficient' : 'balance-insufficient'; ?>">
                    $<?php echo number_format($current_balance, 2); ?>
                </span>
                
                <?php if ($current_balance < $low_balance_threshold): ?>
                    <a href="add_funds.php" class="add-funds-link">
                        <i class="fas fa-plus-circle"></i> Add Funds
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($active_rentals) && empty($overdue_rentals)): ?>
                <div class="empty-rentals">
                    <i class="fas fa-calendar-times"></i>
                    <h3>You don't have any active rentals</h3>
                    <p>Start renting products to see them here!</p>
                    <a href="home.php" class="browse-button">
                        <i class="fas fa-search"></i> Browse Products
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($overdue_rentals)): ?>
                    <div class="overdue-section mb-5">
                        <h3 class="section-heading text-danger">
                            <i class="fas fa-exclamation-circle"></i> Overdue Rentals (<?php echo count($overdue_rentals); ?>)
                        </h3>
                        
                        <?php foreach ($overdue_rentals as $rental): ?>
                            <div class="rental-card">
                                <div class="rental-header">
                                    <img src="<?php echo htmlspecialchars($rental['product_img']); ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>" class="rental-image">
                                    <div>
                                        <h4 class="rental-title"><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                        <p class="rental-seller">Rented from: <?php echo htmlspecialchars($rental['seller_name']); ?></p>
                                        <span class="status-badge status-overdue">OVERDUE</span>
                                        <span class="days-remaining days-negative">
                                            <?php echo abs($rental['days_remaining']); ?> days late
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="rental-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Rental Price</div>
                                        <div class="detail-value">$<?php echo number_format($rental['rental_price'], 2); ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Start Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">End Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($rental['rental_end_date'])); ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Duration</div>
                                        <div class="detail-value"><?php echo $rental['rental_duration'] . ' ' . $rental['duration_unit']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="rental-actions">
                                    <form action="rental.php" method="post">
                                        <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                        <button type="submit" name="return_rental" class="return-button">
                                            <i class="fas fa-undo-alt"></i> Return Now
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($active_rentals)): ?>
                    <div class="active-section">
                        <h3 class="section-heading">
                            <i class="fas fa-check-circle"></i> Active Rentals (<?php echo count($active_rentals); ?>)
                        </h3>
                        
                        <?php foreach ($active_rentals as $rental): ?>
                            <div class="rental-card">
                                <div class="rental-header">
                                    <img src="<?php echo htmlspecialchars($rental['product_img']); ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>" class="rental-image">
                                    <div>
                                        <h4 class="rental-title"><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                        <p class="rental-seller">Rented from: <?php echo htmlspecialchars($rental['seller_name']); ?></p>
                                        <span class="status-badge status-active">ACTIVE</span>
                                        <span class="days-remaining <?php echo $rental['days_remaining'] <= 2 ? 'days-warning' : 'days-positive'; ?>">
                                            <?php echo $rental['days_remaining']; ?> days remaining
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="rental-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Rental Price</div>
                                        <div class="detail-value">$<?php echo number_format($rental['rental_price'], 2); ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Start Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">End Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($rental['rental_end_date'])); ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Duration</div>
                                        <div class="detail-value"><?php echo $rental['rental_duration'] . ' ' . $rental['duration_unit']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="rental-actions">
                                    <form action="rental.php" method="post">
                                        <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                        <button type="submit" name="return_rental" class="return-button">
                                            <i class="fas fa-undo-alt"></i> Return Early
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>