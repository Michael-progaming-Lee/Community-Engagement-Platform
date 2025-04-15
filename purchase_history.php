<?php
session_start();
require_once 'php/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id']; 

// Get user's current balance with COALESCE to handle NULL values
$balance_query = "SELECT *, COALESCE(AccountBalance, 0.00) as AccountBalance FROM users WHERE Id = ?";
$balance_stmt = mysqli_prepare($con, $balance_query);
mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);
$user_data = mysqli_fetch_assoc($balance_result);
$current_balance = $user_data['AccountBalance'];

// Define low balance threshold
$low_balance_threshold = 10.00; // Show warning when balance is below $10

// Check if purchase_history table exists
$table_check = mysqli_query($con, "SHOW TABLES LIKE 'purchase_history'");
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
        error_log("Error creating purchase_history table: " . mysqli_error($con));
    } else {
        error_log("purchase_history table created successfully");
    }
}

// Initialize purchase_history array
$purchase_history = [];

// Fetch user's purchase history only if the table exists
$table_exists = mysqli_query($con, "SHOW TABLES LIKE 'purchase_history'");
if (mysqli_num_rows($table_exists) > 0) {
    $query = "SELECT ph.*, p.product_name, p.product_img, p.product_cost as unit_price, u.Username as seller_name 
              FROM purchase_history ph
              JOIN product p ON ph.product_id = p.id
              JOIN users u ON p.product_seller_id = u.Id
              WHERE ph.buyer_id = ?
              ORDER BY ph.purchase_date DESC";

    $stmt = mysqli_prepare($con, $query);
    if ($stmt === false) {
        $error_message = "Error preparing statement: " . mysqli_error($con);
        error_log($error_message);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            $error_message = "Error executing query: " . mysqli_stmt_error($stmt);
            error_log($error_message);
        } else {
            $result = mysqli_stmt_get_result($stmt);
            
            // Store results in an array for later use
            while ($row = mysqli_fetch_assoc($result)) {
                $purchase_history[] = $row;
            }
        }
    }
}

// Handle any error/success messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success" role="alert">';
    echo '<i class="fas fa-check-circle"></i> ';
    echo $_SESSION['success_message'];
    echo '</div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger" role="alert">';
    echo '<i class="fas fa-exclamation-triangle"></i> ';
    echo $_SESSION['error_message'];
    echo '</div>';
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-image: url('Background Images/Home_Background.png');
            background-size: cover;
            background-position: top center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            padding-bottom: 40px;
        }
        .container-balance {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            max-width: 1140px;
            margin: 20px auto;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .purchase-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .purchase-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        .balance-display {
            font-size: 1.2rem;
            padding: 10px 15px;
            border-radius: 30px;
            background-color: #f8f9fa;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .balance-amount {
            font-weight: bold;
            margin: 0 5px;
        }
        .balance-warning {
            color: #dc3545;
            padding: 10px 0 0 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        .balance-warning i {
            margin-right: 5px;
        }
        .text-success {
            color: #198754 !important;
        }
        .text-danger {
            color: #dc3545 !important;
        }
        .add-funds-link {
            color: #0d6efd;
            text-decoration: none;
            margin-left: 10px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
        }
        .add-funds-link i {
            margin-right: 3px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'php/header.php'; ?>

    <div class="container-balance">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0">Purchase History</h2>
            <div class="balance-display">
                <i class="fas fa-wallet"></i>
                Current Balance: 
                <span class="balance-amount <?php echo $current_balance > $low_balance_threshold ? 'text-success' : 'text-danger'; ?>">
                    $<?php echo number_format($current_balance, 2); ?>
                </span>
                <?php if ($current_balance <= $low_balance_threshold): ?>
                    <a href="edituserinfo.php" class="add-funds-link">
                        <i class="fas fa-plus-circle"></i> Add Funds
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($current_balance <= $low_balance_threshold): ?>
            <div class="balance-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Your balance is running low. Add funds to continue making purchases.</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="container" style="max-width: 1140px; margin: 0 auto 40px auto;">
        <?php 
        // Check if there are any rental items to return
        $has_rentals = false;
        foreach ($purchase_history as $item) {
            if ($item['status'] === 'rented') {
                $has_rentals = true;
                break;
            }
        }
        ?>
        
        <?php if ($has_rentals): ?>
        <form action="return_rental.php" method="post" id="return-form">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0"><i class="fas fa-clock me-2"></i>Rented Products</h3>
                <button type="submit" class="btn btn-primary" id="return-button" disabled>
                    <i class="fas fa-undo-alt me-1"></i> Return Selected Items
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($purchase_history)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($purchase_history as $row): ?>
                    <div class="col">
                        <div class="card h-100 purchase-card">
                            <?php if ($row['status'] === 'rented'): ?>
                            <div class="form-check position-absolute m-2">
                                <input class="form-check-input rental-checkbox" type="checkbox" name="purchase_ids[]" 
                                       value="<?php echo $row['id']; ?>" id="rental-<?php echo $row['id']; ?>">
                                <label class="form-check-label" for="rental-<?php echo $row['id']; ?>"></label>
                            </div>
                            <?php endif; ?>
                            <img src="<?php echo htmlspecialchars($row['product_img']); ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                 style="height: 200px; object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($row['product_name']); ?></h5>
                                <p class="card-text">
                                    <small class="text-muted">Seller: <?php echo htmlspecialchars($row['seller_name']); ?></small><br>
                                    <small class="text-muted">Date: <?php echo date('M d, Y', strtotime($row['purchase_date'])); ?></small>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">$<?php echo number_format($row['price'], 2); ?></span>
                                    <?php
                                    $status_class = match($row['status']) {
                                        'rented' => 'bg-info',
                                        'bought' => 'bg-success',
                                        'returned' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $status_class; ?> status-badge">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </div>
                                <?php if ($row['status'] === 'rented'): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Rental Duration: <?php echo htmlspecialchars($row['rental_duration']); ?> 
                                            <?php echo htmlspecialchars($row['duration_unit']); ?><br>
                                            <?php if (!empty($row['rental_start_date'])): ?>
                                                Rental Period: <?php echo date('M d, Y', strtotime($row['rental_start_date'])); ?> 
                                                to <?php echo date('M d, Y', strtotime($row['rental_end_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                You haven't made any purchases yet. Check out our <a href="home.php" class="alert-link">products</a> to get started!
            </div>
        <?php endif; ?>
        
        <?php if ($has_rentals): ?>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($has_rentals): ?>
    <script>
    // Enable/disable return button based on checkbox selection
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.rental-checkbox');
        const returnButton = document.getElementById('return-button');
        
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                // Check if any checkbox is selected
                const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
                returnButton.disabled = !anyChecked;
            });
        });
        
        // Make the entire card clickable to toggle checkbox (except other interactive elements)
        const rentalCards = document.querySelectorAll('.rental-checkbox').forEach(function(checkbox) {
            const card = checkbox.closest('.card');
            
            card.addEventListener('click', function(e) {
                // Don't toggle if clicking on a button, link, or the checkbox itself
                if (e.target.tagName === 'A' || 
                    e.target.tagName === 'BUTTON' || 
                    e.target.tagName === 'INPUT') {
                    return;
                }
                
                // Toggle checkbox
                checkbox.checked = !checkbox.checked;
                
                // Trigger change event to update button state
                checkbox.dispatchEvent(new Event('change'));
            });
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>
