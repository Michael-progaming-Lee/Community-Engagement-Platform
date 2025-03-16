<?php
if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get user information
$id = $_SESSION['id'];
$query = mysqli_query($con, "SELECT * FROM users WHERE Id=$id");
$result = mysqli_fetch_assoc($query);
$res_Uname = $result['Username'];
$res_Email = $result['Email'];
$res_Age = $result['Age'];
$res_id = $result['Id'];
$accountBalance = isset($result['AccountBalance']) ? $result['AccountBalance'] : 0;

// Get cart total if available
$cartTotal = 0;
$cartQuery = mysqli_query($con, "SELECT SUM(product_total) as total FROM users_cart WHERE UserID=$id");
if($cartQuery && mysqli_num_rows($cartQuery) > 0) {
    $cartResult = mysqli_fetch_assoc($cartQuery);
    $cartTotal = isset($cartResult['total']) ? $cartResult['total'] : 0;
}

// Get unread notifications count
$unreadNotificationsCount = 0;

// 1. First, ensure the notifications table exists
$tableCheckQuery = "SHOW TABLES LIKE 'notifications'";
$tableCheckResult = mysqli_query($con, $tableCheckQuery);

if (!$tableCheckResult) {
    // Error in the query
    error_log("Error checking for notifications table: " . mysqli_error($con));
} else if (mysqli_num_rows($tableCheckResult) == 0) {
    // Table doesn't exist, create it
    $createTableQuery = "CREATE TABLE IF NOT EXISTS notifications (
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
    
    $createResult = mysqli_query($con, $createTableQuery);
    if (!$createResult) {
        error_log("Error creating notifications table: " . mysqli_error($con));
    }
} else {
    // Table exists, now get the count using prepared statement for better security
    $countQuery = "SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = FALSE";
    $countStmt = mysqli_prepare($con, $countQuery);
    
    if (!$countStmt) {
        error_log("Error preparing notifications count query: " . mysqli_error($con));
    } else {
        mysqli_stmt_bind_param($countStmt, "i", $id);
        
        if (!mysqli_stmt_execute($countStmt)) {
            error_log("Error executing notifications count query: " . mysqli_stmt_error($countStmt));
        } else {
            $countResult = mysqli_stmt_get_result($countStmt);
            $countData = mysqli_fetch_assoc($countResult);
            $unreadNotificationsCount = $countData['count'];
            
            // Debug log
            error_log("Found $unreadNotificationsCount unread notifications for user $id");
        }
        
        mysqli_stmt_close($countStmt);
    }
}

// Determine balance status
$balanceStatus = ($accountBalance >= $cartTotal) ? 'sufficient' : 'insufficient';

// Determine if we're in a subdirectory
$isSubdir = strpos($_SERVER['PHP_SELF'], '/php/') !== false;
$imgPath = $isSubdir ? '../Background Images/CommUnity Logo.jpeg' : 'Background Images/CommUnity Logo.jpeg';
$homePath = $isSubdir ? '../home.php' : 'home.php';
$purchaseHistoryPath = $isSubdir ? '../purchase_history.php' : 'purchase_history.php';
$cartPath = $isSubdir ? '../users_cart.php' : 'users_cart.php';
$rentalPath = $isSubdir ? '../rental.php' : 'rental.php';
$addFundsPath = $isSubdir ? '../add_funds.php' : 'add_funds.php';
$manageProductPath = $isSubdir ? '../manage_product.php' : 'manage_product.php';
$notificationsPath = $isSubdir ? '../notifications.php' : 'notifications.php';
$logoutPath = $isSubdir ? '../php/logout.php' : 'php/logout.php';

?>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Header CSS -->
<style>
.header-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(147, 163, 178, 0.8);
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    height: 70px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.logo-section {
    display: flex;
    align-items: center;
    flex: 0 0 auto;
}

.logo-section img {
    height: 50px;
}

.title-section {
    text-align: center;
    flex: 1;
}

.title-section h1 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.nav-links {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: nowrap;
    justify-content: flex-end;
    flex: 1;
}

.balance-container {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-right: 10px;
    min-width: 120px;
}

.balance {
    font-weight: bold;
}

.sufficient {
    color: green;
}

.insufficient {
    color: red;
}

.add-funds {
    font-size: 12px;
    text-decoration: underline;
    color: #0066cc;
}

.notification-btn {
    position: relative;
    background-color: transparent;
    border: none;
    cursor: pointer;
    padding: 5px;
    margin-right: 10px;
    text-decoration: none;
    display: inline-block;
}

.notification-icon {
    font-size: 20px;
    color: #333;
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background-color: #f44336;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.nav-btn {
    padding: 8px 12px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    white-space: nowrap;
    display: inline-block;
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

@media (max-width: 1100px) {
    .nav-links {
        gap: 5px;
    }
    
    .nav-btn {
        padding: 6px 8px;
        font-size: 13px;
    }
}

@media (max-width: 800px) {
    .header-container {
        flex-direction: column;
        height: auto;
        padding: 10px;
    }
    
    .title-section {
        margin: 10px 0;
    }
    
    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
        width: 100%;
    }
}
</style>

<div class="header-container">
    <div class="logo-section">
        <a href="<?php echo $homePath; ?>" style="text-decoration: none;">
            <img src="<?php echo $imgPath; ?>" alt="Company Logo">
        </a>
    </div>

    <div class="title-section">
        <a href="<?php echo $homePath; ?>" style="text-decoration: none; pointer-events: auto;">
            <h1>CommUnity Rentals</h1>
        </a>
    </div>

    <div class="nav-links">
        <div class="balance-container">
            <div class="balance <?php echo $balanceStatus; ?>">
                Balance: $<?php echo number_format($accountBalance, 2); ?>
            </div>
            <?php if($balanceStatus === 'insufficient'): ?>
                <a href="<?php echo $addFundsPath; ?>" class="add-funds">Add Funds</a>
            <?php endif; ?>
        </div>
        
        <a href="<?php echo $notificationsPath; ?>" class="notification-btn">
            <i class="fas fa-bell notification-icon"></i>
            <?php if ($unreadNotificationsCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo $manageProductPath; ?>?Id=<?php echo $res_id; ?>" class="nav-btn">Edit Products</a>
        <a href="<?php echo $cartPath; ?>" class="nav-btn">View Cart</a>
        <a href="<?php echo $rentalPath; ?>" class="nav-btn">Rentals</a>
        <a href="<?php echo $purchaseHistoryPath; ?>" class="nav-btn">Purchase History</a>
        <a href="<?php echo $logoutPath; ?>" class="nav-btn nav-btn-blue">Log Out</a>
    </div>
</div>
<div style="height: 70px;"></div>
