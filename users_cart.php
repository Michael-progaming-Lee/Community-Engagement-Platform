<?php
session_start();
include("php/config.php");

if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

$user_id = $_SESSION['id'];
$user_query = mysqli_query($con, "SELECT *, COALESCE(AccountBalance, 0.00) as AccountBalance FROM users WHERE Id=$user_id");
$user_data = mysqli_fetch_assoc($user_query);
$username = $user_data['Username'];

// Handle remove from cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_item' && isset($_POST['cart_id'])) {
    $cart_id = $_POST['cart_id'];
    
    // Remove item from cart without affecting product quantity
    $delete_query = "DELETE FROM users_cart WHERE Id = ? AND UserID = ?";
    $delete_stmt = $con->prepare($delete_query);
    $delete_stmt->bind_param("ii", $cart_id, $user_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Item removed from cart successfully.";
    } else {
        $_SESSION['error_message'] = "Error removing item from cart: " . $delete_stmt->error;
    }
    
    // Redirect to refresh the page after removal
    header("Location: users_cart.php");
    exit();
}

// Handle quantity updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_quantity') {
    $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
    $new_quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if ($cart_id > 0 && $new_quantity > 0) {
        // First get the current cart item
        $get_item = $con->prepare("SELECT *, (SELECT product_quantity FROM product WHERE id = users_cart.product_id) AS available_quantity FROM users_cart WHERE Id = ? AND UserID = ?");
        $get_item->bind_param("ii", $cart_id, $user_id);
        $get_item->execute();
        $result = $get_item->get_result();
        
        if ($item = $result->fetch_assoc()) {
            // Check if enough stock is available
            if ($item['available_quantity'] >= $new_quantity) {
                // Calculate new total based on product cost and quantity
                $new_total = $item['product_cost'] * $new_quantity;
                
                // Update the cart
                $update = $con->prepare("UPDATE users_cart SET product_quantity = ?, product_total = ? WHERE Id = ? AND UserID = ?");
                $update->bind_param("idii", $new_quantity, $new_total, $cart_id, $user_id);
                $update->execute();
                
                echo json_encode(['success' => true, 'message' => 'Quantity updated', 'new_total' => number_format($new_total, 2), 'cart_id' => $cart_id]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Determine cart type view (rent or purchase)
$view_type = isset($_GET['view']) ? $_GET['view'] : 'all';

// Fetch cart items using prepared statement with filter
$cart_query = "SELECT uc.*, p.product_img, p.product_description, p.product_category 
               FROM users_cart uc
               LEFT JOIN product p ON uc.product_id = p.id
               WHERE uc.UserID = ?";

// Add filter based on view type
if ($view_type === 'rent') {
    $cart_query .= " AND uc.listing_type = 'rent'";
} elseif ($view_type === 'purchase') {
    $cart_query .= " AND (uc.listing_type = 'purchase' OR uc.listing_type = 'sell')";
}

// Add error handling for prepared statement
$stmt = $con->prepare($cart_query);
if ($stmt === false) {
    // Log the error for debugging
    error_log("Prepare failed: " . $con->error);
    // Set an empty result set
    $cart_items = array();
    $total_cost = 0;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();

    // Calculate total before displaying
    $total_cost = 0;
    $cart_items = array();
    while ($row = $cart_result->fetch_assoc()) {
        $total_cost += $row['product_total'];
        $cart_items[] = $row;
    }
}

// Clear messages after displaying
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Check if user has sufficient balance
$has_sufficient_balance = $user_data['AccountBalance'] >= $total_cost;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/users_cart.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Shopping Cart</title>
    <style>
        body {
            background-image: url('Background Images/Home_Background.png');
            background-size: cover;
            background-position: top center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: Arial, sans-serif;
        }
        .container {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto 20px;
            max-width: 1200px;
            width: calc(100% - 40px);
        }
        .container-balance {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 0 auto 20px;
            max-width: 1200px;
            width: calc(100% - 40px);
        }
        .table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: 100%;
            table-layout: auto;
        }
        .table thead th {
            background: rgba(44, 62, 80, 0.05);
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid rgba(44, 62, 80, 0.1);
            padding: 15px;
        }
        /* Fixed column widths to prevent overlapping */
        .product-col { width: 25%; }
        .category-col { width: 15%; }
        .quantity-col { width: 8%; }
        .rental-col { width: 20%; min-width: 200px; }
        .price-col { width: 12%; }
        .total-col { width: 10%; }
        .action-col { width: 10%; }
        
        /* Rental details styling */
        .rental-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9em;
            color: #666;
            line-height: 1.6;
        }
        .rental-details strong {
            color: #2c3e50;
            display: inline-block;
            width: 60px;
        }
        .rental-date-row {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            margin-bottom: 4px;
        }
        .rental-date-value {
            font-weight: 500;
            color: #27ae60;
        }
        .duration-unit {
            font-size: 0.8em;
            color: #999;
            margin-left: 4px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .toggle-buttons {
                flex-direction: column;
            }
            .toggle-btn {
                width: 100%;
                margin: 5px 0;
            }
            .cart-header {
                flex-direction: column;
            }
            .balance-display {
                margin-top: 10px;
            }
            .table {
                display: block;
                overflow-x: auto;
            }
            .table thead {
                position: relative;
            }
            .rental-details {
                white-space: normal;
                min-width: 150px;
            }
        }
        
        .d-flex {
            display: flex;
        }
        .align-items-center {
            align-items: center;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 12px;
            border: 1px solid #eee;
        }
        .product-info {
            display: flex;
            flex-direction: column;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .rental-badge {
            font-size: 0.8em;
            background-color: #27ae60;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
            display: inline-block;
        }
        .cart-view-toggle {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }
        .toggle-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            max-width: fit-content;
            margin: 0 auto;
        }
        .toggle-btn {
            flex: 0 1 auto;
            text-align: center;
            padding: 12px 25px;
            margin: 0 5px;
            border-radius: 6px;
            background: rgba(44, 62, 80, 0.05);
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            white-space: nowrap;
        }
        .toggle-btn:hover {
            background: rgba(44, 62, 80, 0.1);
            transform: translateY(-2px);
        }
        .toggle-btn.active {
            background: #27ae60;
            color: white;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.2);
        }
        .cashout-button {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .cashout-button:hover {
            background-color: #219653;
        }
        .cashout-button:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }
        .message {
            padding: 10px 20px;
            margin: 10px auto;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
            max-width: 900px;
            width: calc(100% - 40px);
        }
        .success-message {
            background-color: rgba(39, 174, 96, 0.2);
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }
        .error-message {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        .balance-warning {
            color: #e74c3c;
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            font-weight: bold;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 6px;
            border-left: 4px solid #e74c3c;
            display: <?php echo !$has_sufficient_balance ? 'flex' : 'none'; ?>;
            align-items: center;
        }
        .cart-total {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            padding: 8px 15px;
            border-radius: 6px;
            background: rgba(44, 62, 80, 0.05);
        }
        .balance-display {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            padding: 8px 15px;
            border-radius: 6px;
            background: rgba(44, 62, 80, 0.05);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table td {
            vertical-align: middle;
            border-bottom: 1px solid rgba(44, 62, 80, 0.05);
            padding: 15px;
        }
        .rental-item {
            background-color: rgba(240, 247, 240, 0.7);
        }
        .purchase-item {
            background-color: rgba(255, 255, 255, 0.7);
        }
        .balance-amount {
            color: <?php echo $has_sufficient_balance ? '#27ae60' : '#e74c3c'; ?>;
            margin-left: 8px;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .add-funds-link {
            font-size: 0.9em;
            color: #3498db;
            text-decoration: none;
            padding: 4px 12px;
            border-radius: 4px;
            background: rgba(52, 152, 219, 0.1);
            transition: all 0.3s ease;
            margin-left: 10px;
            white-space: nowrap;
            display: <?php echo !$has_sufficient_balance ? 'inline-block' : 'none'; ?>;
        }
        .add-funds-link:hover {
            background: rgba(52, 152, 219, 0.2);
            color: #2980b9;
        }
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        .message i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        /* Additional CSS for new quantity controls */
        .quantity-container {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 120px;
            margin: 0 auto;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: #f8f8f8;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .quantity-btn:hover {
            background: #e9e9e9;
        }
        
        .quantity-input {
            width: 40px;
            height: 30px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 0 5px;
            padding: 0;
        }
        
        /* Remove spinners from number input */
        .quantity-input::-webkit-inner-spin-button, 
        .quantity-input::-webkit-outer-spin-button { 
            -webkit-appearance: none;
            margin: 0;
        }
        .quantity-input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>
    
    <h1 style="color: #333; text-align: center; margin: 20px 0;">Your Cart</h1>

    <!-- Cart View Toggle -->
    <div class="cart-view-toggle">
        <div class="toggle-buttons">
            <a href="users_cart.php?view=all" class="toggle-btn <?php echo $view_type === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-basket"></i> All Items
            </a>
            <a href="users_cart.php?view=purchase" class="toggle-btn <?php echo $view_type === 'purchase' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i> Purchase Items
            </a>
            <a href="users_cart.php?view=rent" class="toggle-btn <?php echo $view_type === 'rent' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Rental Items
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="message success-message">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message error-message">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="container-balance">
        <div class="cart-header">
            <div class="cart-total">
                <i class="fas fa-shopping-cart"></i> Cart Total: $<?php echo number_format($total_cost, 2); ?>
            </div>
            <div class="balance-display">
                <i class="fas fa-wallet"></i>
                Balance: 
                <span class="balance-amount">
                    $<?php echo number_format($user_data['AccountBalance'], 2); ?>
                </span>
                <?php if (!$has_sufficient_balance): ?>
                    <a href="edituserinfo.php" class="add-funds-link">
                        <i class="fas fa-plus-circle"></i> Add Funds
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$has_sufficient_balance): ?>
            <div class="balance-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Your balance is insufficient for the cart total. Please add funds to continue.</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <?php if (!empty($cart_items)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th class="product-col">Product</th>
                        <th class="category-col">Category</th>
                        <th class="quantity-col">Quantity</th>
                        <?php if ($view_type === 'rent' || $view_type === 'all'): ?>
                            <th class="rental-col">Rental Period</th>
                        <?php endif; ?>
                        <th class="price-col">Price</th>
                        <th class="total-col">Total</th>
                        <th class="action-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <?php 
                        $is_rental = !empty($item['rental_start_date']);
                        if (($view_type === 'rent' && !$is_rental) || ($view_type === 'purchase' && $is_rental)) {
                            continue; // Skip items that don't match the view filter
                        }
                        ?>
                        <tr class="<?php echo $is_rental ? 'rental-item' : 'purchase-item'; ?>" data-cart-id="<?php echo $item['Id']; ?>">
                            <td class="product-col">
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($item['product_img'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['product_img']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                             class="product-image" style="object-fit: cover; width: 60px; height: 60px;">
                                    <?php else: ?>
                                        <img src="style/default-product.png" 
                                             alt="No image available"
                                             class="product-image" style="object-fit: cover; width: 60px; height: 60px;">
                                    <?php endif; ?>
                                    <div class="product-info">
                                        <span class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        <?php if ($is_rental): ?>
                                            <span class="rental-badge">RENTAL</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="category-col"><?php echo htmlspecialchars($item['product_category']); ?></td>
                            <td class="quantity-col">
                                <div class="quantity-container">
                                    <button class="quantity-btn minus" data-cart-id="<?php echo $item['Id']; ?>">-</button>
                                    <input type="number" class="quantity-input" value="<?php echo $item['product_quantity']; ?>" 
                                           min="1" max="99" data-cart-id="<?php echo $item['Id']; ?>">
                                    <button class="quantity-btn plus" data-cart-id="<?php echo $item['Id']; ?>">+</button>
                                </div>
                            </td>
                            <?php if ($is_rental): ?>
                                <td class="rental-col">
                                    <div class="rental-details">
                                        <div class="rental-date-row">
                                            <strong>From:</strong> 
                                            <span class="rental-date-value">
                                                <?php 
                                                if (isset($item['rental_start_date']) && !empty($item['rental_start_date'])) {
                                                    try {
                                                        // Ensure we have a valid date before formatting
                                                        $start_date = strtotime($item['rental_start_date']);
                                                        if ($start_date !== false) {
                                                            echo date('M d, Y', $start_date);
                                                        } else {
                                                            echo "Invalid date";
                                                            error_log("Invalid rental start date for cart item {$item['Id']}: {$item['rental_start_date']}");
                                                        }
                                                    } catch (Exception $e) {
                                                        echo "Date error";
                                                        error_log("Error formatting start date: " . $e->getMessage());
                                                    }
                                                } else {
                                                    echo "N/A";
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="rental-date-row">
                                            <strong>To:</strong> 
                                            <span class="rental-date-value">
                                                <?php 
                                                if (isset($item['rental_end_date']) && !empty($item['rental_end_date'])) {
                                                    try {
                                                        // Ensure we have a valid date before formatting
                                                        $end_date = strtotime($item['rental_end_date']);
                                                        if ($end_date !== false) {
                                                            echo date('M d, Y', $end_date);
                                                        } else {
                                                            echo "Invalid date";
                                                            error_log("Invalid rental end date for cart item {$item['Id']}: {$item['rental_end_date']}");
                                                        }
                                                    } catch (Exception $e) {
                                                        echo "Date error";
                                                        error_log("Error formatting end date: " . $e->getMessage());
                                                    }
                                                } else {
                                                    echo "N/A";
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="rental-date-row">
                                            <strong>Duration:</strong> 
                                            <span class="rental-date-value">
                                                <?php echo $item['rental_duration']; ?> days
                                                <span class="duration-unit">(<?php 
                                                    // Format the duration unit to be more user-friendly
                                                    $unit_display = ucfirst($item['duration_unit']);
                                                    // Remove 'ly' suffix if present
                                                    $unit_display = str_replace('ly', '', $unit_display);
                                                    echo $unit_display; 
                                                ?> Rate)</span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            <?php elseif ($view_type === 'all'): ?>
                                <td class="rental-col">-</td>
                            <?php endif; ?>
                            <td class="price-col">
                                <?php if ($is_rental): ?>
                                    $<?php echo number_format($item['product_cost'], 2); ?> / <?php echo substr($item['duration_unit'], 0, -2); ?>
                                <?php else: ?>
                                    $<?php echo number_format($item['product_cost'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <td class="total-col">$<?php echo number_format($item['product_total'], 2); ?></td>
                            <td class="action-col">
                                <form action="users_cart.php" method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['Id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="width: 100%; min-width: 80px; white-space: nowrap; padding: 6px 10px; font-size: 14px;">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> Your cart is empty. 
                <a href="home.php" class="alert-link">Continue shopping</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($cart_items)): ?>
    <div style="text-align: center; margin: 20px 0;">
        <button type="button" class="cashout-button" <?php echo !$has_sufficient_balance ? 'disabled' : ''; ?> onclick="window.location.href='checkout.php'" style="display: inline-block; margin: 0 auto;">
            <i class="fas fa-shopping-cart"></i> Proceed to Checkout
        </button>
    </div>
    <?php endif; ?>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quantity buttons
    const minusBtns = document.querySelectorAll('.quantity-btn.minus');
    const plusBtns = document.querySelectorAll('.quantity-btn.plus');
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    // Function to update quantity
    function updateQuantity(cartId, newQuantity) {
        // Send AJAX request to update quantity
        fetch('users_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_quantity&cart_id=${cartId}&quantity=${newQuantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the displayed total for this item
                const totalCell = document.querySelector(`tr[data-cart-id="${cartId}"] .total-col`);
                if (totalCell) {
                    totalCell.textContent = `$${data.new_total}`;
                }
                
                // Recalculate page total
                updateCartTotal();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Function to update the cart total displayed on the page
    function updateCartTotal() {
        let total = 0;
        document.querySelectorAll('.total-col').forEach(cell => {
            // Extract the numeric value from the price string
            const value = parseFloat(cell.textContent.replace('$', '').replace(',', ''));
            if (!isNaN(value)) {
                total += value;
            }
        });
        
        // Update the total display
        const totalElement = document.querySelector('.cart-total');
        if (totalElement) {
            totalElement.textContent = `Total: $${total.toFixed(2)}`;
        }
        
        // Update balance validation
        const balanceElement = document.querySelector('.balance-amount');
        const balanceValue = parseFloat(balanceElement.textContent.replace('$', '').replace(',', ''));
        const isSufficient = balanceValue >= total;
        
        balanceElement.style.color = isSufficient ? '#27ae60' : '#e74c3c';
        
        // Show/hide warning message and add funds link
        const warningMsg = document.querySelector('.message.warning-message');
        const addFundsLink = document.querySelector('.add-funds-link');
        const checkoutBtn = document.querySelector('.cashout-button');
        
        if (warningMsg) warningMsg.style.display = isSufficient ? 'none' : 'flex';
        if (addFundsLink) addFundsLink.style.display = isSufficient ? 'none' : 'inline-block';
        if (checkoutBtn) checkoutBtn.disabled = !isSufficient;
    }
    
    // Handle minus button
    minusBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const cartId = this.getAttribute('data-cart-id');
            const input = document.querySelector(`.quantity-input[data-cart-id="${cartId}"]`);
            let value = parseInt(input.value);
            if (value > 1) {
                value--;
                input.value = value;
                updateQuantity(cartId, value);
            }
        });
    });
    
    // Handle plus button
    plusBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const cartId = this.getAttribute('data-cart-id');
            const input = document.querySelector(`.quantity-input[data-cart-id="${cartId}"]`);
            let value = parseInt(input.value);
            if (value < 99) {
                value++;
                input.value = value;
                updateQuantity(cartId, value);
            }
        });
    });
    
    // Handle direct input changes
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const cartId = this.getAttribute('data-cart-id');
            let value = parseInt(this.value);
            
            // Ensure value is within valid range
            if (isNaN(value) || value < 1) value = 1;
            if (value > 99) value = 99;
            
            this.value = value;
            updateQuantity(cartId, value);
        });
    });
});
</script>
</html>