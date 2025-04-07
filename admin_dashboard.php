<?php
// Turn off error display for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}

session_start();

// Include database connection
include("php/config.php");

// Function to add notification for user
function add_notification($con, $user_id, $message, $type, $related_id = null) {
    $query = "INSERT INTO notifications (user_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = $con->prepare($query);
    $stmt->bind_param("issi", $user_id, $message, $type, $related_id);
    return $stmt->execute();
}

// Handle rental report admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Save admin notes for a report
    if ($_POST['action'] === 'save_admin_notes') {
        if (!isset($_POST['report_id']) || !isset($_POST['admin_notes'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }
        
        $report_id = $_POST['report_id'];
        $admin_notes = $_POST['admin_notes'];
        
        $update_query = "UPDATE rental_reports SET admin_notes = ? WHERE id = ?";
        $update_stmt = $con->prepare($update_query);
        $update_stmt->bind_param("si", $admin_notes, $report_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $con->error]);
        }
        exit;
    }
    
    // Mark report as reviewed
    if ($_POST['action'] === 'mark_report_reviewed') {
        if (!isset($_POST['report_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing report ID']);
            exit;
        }
        
        $report_id = $_POST['report_id'];
        
        $update_query = "UPDATE rental_reports SET status = 'reviewed' WHERE id = ?";
        $update_stmt = $con->prepare($update_query);
        $update_stmt->bind_param("i", $report_id);
        
        if ($update_stmt->execute()) {
            // Get report details to send notification
            $report_query = "SELECT rr.*, p.product_name, seller.Username as seller_name, buyer.Username as buyer_name
                            FROM rental_reports rr
                            JOIN product p ON rr.product_id = p.id
                            JOIN users seller ON rr.seller_id = seller.Id
                            JOIN users buyer ON rr.buyer_id = buyer.Id
                            WHERE rr.id = ?";
            $report_stmt = $con->prepare($report_query);
            $report_stmt->bind_param("i", $report_id);
            $report_stmt->execute();
            $report_result = $report_stmt->get_result();
            
            if ($report_result && $report = $report_result->fetch_assoc()) {
                // Send notification to seller
                $seller_message = "Your report for overdue rental of '{$report['product_name']}' has been reviewed by an administrator.";
                add_notification($con, $report['seller_id'], $seller_message, 'report_reviewed', $report_id);
                
                // Send notification to buyer
                $buyer_message = "You have been reported for an overdue rental of '{$report['product_name']}'. Please return this item as soon as possible to avoid further penalties.";
                add_notification($con, $report['buyer_id'], $buyer_message, 'rental_reported', $report_id);
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $con->error]);
        }
        exit;
    }
    
    // Mark report as resolved
    if ($_POST['action'] === 'mark_report_resolved') {
        if (!isset($_POST['report_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing report ID']);
            exit;
        }
        
        $report_id = $_POST['report_id'];
        
        $update_query = "UPDATE rental_reports SET status = 'resolved', resolved_date = NOW() WHERE id = ?";
        $update_stmt = $con->prepare($update_query);
        $update_stmt->bind_param("i", $report_id);
        
        if ($update_stmt->execute()) {
            // Get report details to send notification
            $report_query = "SELECT rr.*, p.product_name, seller.Username as seller_name, buyer.Username as buyer_name
                            FROM rental_reports rr
                            JOIN product p ON rr.product_id = p.id
                            JOIN users seller ON rr.seller_id = seller.Id
                            JOIN users buyer ON rr.buyer_id = buyer.Id
                            WHERE rr.id = ?";
            $report_stmt = $con->prepare($report_query);
            $report_stmt->bind_param("i", $report_id);
            $report_stmt->execute();
            $report_result = $report_stmt->get_result();
            
            if ($report_result && $report = $report_result->fetch_assoc()) {
                // Send notification to seller
                $seller_message = "Your report for overdue rental of '{$report['product_name']}' has been marked as resolved by an administrator.";
                add_notification($con, $report['seller_id'], $seller_message, 'report_resolved', $report_id);
                
                // Send notification to buyer
                $buyer_message = "The report regarding your overdue rental of '{$report['product_name']}' has been resolved.";
                add_notification($con, $report['buyer_id'], $buyer_message, 'report_resolved', $report_id);
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $con->error]);
        }
        exit;
    }
    
    // Send warning notification to buyer about potential ban
    if ($_POST['action'] === 'send_warning_notification') {
        if (!isset($_POST['report_id']) || !isset($_POST['buyer_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }
        
        $report_id = $_POST['report_id'];
        $buyer_id = $_POST['buyer_id'];
        
        // Get report details to send notification
        $report_query = "SELECT rr.*, p.product_name, seller.Username as seller_name, buyer.Username as buyer_name
                        FROM rental_reports rr
                        JOIN product p ON rr.product_id = p.id
                        JOIN users seller ON rr.seller_id = seller.Id
                        JOIN users buyer ON rr.buyer_id = buyer.Id
                        WHERE rr.id = ?";
        $report_stmt = $con->prepare($report_query);
        $report_stmt->bind_param("i", $report_id);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();
        
        if ($report_result && $report = $report_result->fetch_assoc()) {
            // Send warning notification to buyer
            $warning_message = "WARNING: You have been reported for an overdue rental of '{$report['product_name']}'. Please be advised that if you receive three more reports, your account will be banned for two weeks. Please return all overdue items promptly to avoid penalties.";
            add_notification($con, $buyer_id, $warning_message, 'rental_warning', $report_id);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not find report details']);
        }
        exit;
    }
}

// Handle cart count API request
if (isset($_GET['action']) && $_GET['action'] === 'get_cart_count') {
    if (!isset($_SESSION['id'])) {
        echo json_encode(['count' => 0]);
        exit;
    }

    $user_id = $_SESSION['id'];

    // Get total number of items in cart
    $query = "SELECT COUNT(*) as item_count FROM users_cart WHERE UserID = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    echo json_encode(['count' => (int)$data['item_count']]);
    exit;
}

// Handle filtered count API request
if (isset($_GET['action']) && $_GET['action'] === 'get_filtered_count') {
    header('Content-Type: application/json');
    
    // Initialize search condition
    $searchCondition = "";
    if (isset($_GET['search']) && isset($_GET['criteria'])) {
        $search = mysqli_real_escape_string($con, $_GET['search']);
        $criteria = mysqli_real_escape_string($con, $_GET['criteria']);
        
        switch ($criteria) {
            case 'id':
                $searchCondition = "WHERE Id = '$search'";
                break;
            case 'username':
                $searchCondition = "WHERE Username LIKE '%$search%'";
                break;
            case 'email':
                $searchCondition = "WHERE Email LIKE '%$search%'";
                break;
        }
    }
    
    // Get count of filtered results
    $query = "SELECT COUNT(*) as count FROM users $searchCondition";
    $result = mysqli_query($con, $query);
    
    if ($result) {
        $count = mysqli_fetch_assoc($result)['count'];
        echo json_encode(['count' => $count]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error counting users: ' . mysqli_error($con)]);
    }
    exit;
}

// Handle filtered products API request
if (isset($_GET['action']) && $_GET['action'] === 'filter_products') {
    // Get seller ID filter
    $seller_id = isset($_GET['seller_id']) ? mysqli_real_escape_string($con, $_GET['seller_id']) : '';
    
    // Build query with filter
    $filter_query = "SELECT p.*, u.Username as seller_name FROM product p 
                    LEFT JOIN users u ON p.product_seller_id = u.Id";
    
    // Add seller filter if provided
    if (!empty($seller_id)) {
        $filter_query .= " WHERE p.product_seller_id = '$seller_id'";
    }
    
    // Add order by
    $filter_query .= " ORDER BY p.id DESC";
    
    // Execute query
    $filter_result = mysqli_query($con, $filter_query);
    
    if ($filter_result && mysqli_num_rows($filter_result) > 0) {
        echo '<div style="overflow-x: auto; width: 100%;">
                <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 2px solid #333;">
                    <thead>
                        <tr style="background-color: #f2f2f2;">
                            <th style="padding: 12px; text-align: center; border: 1px solid #333;">
                                <input type="checkbox" id="select_all_products" style="transform: scale(1.2); cursor: pointer;">
                            </th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">ID</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Image</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">QR Code</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Name</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Category</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Seller</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Description</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Quantity</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Price/Rates</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Listing Type</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #333;">Status</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        while ($product = mysqli_fetch_assoc($filter_result)) {
            $is_banned = isset($product['status']) && $product['status'] === 'banned';
            $row_style = $is_banned ? 'background-color: rgba(255, 200, 200, 0.3);' : '';
            
            echo '<tr class="product-row" data-product-id="' . $product['id'] . '" style="' . $row_style . '">
                    <td style="padding: 12px; text-align: center; border: 1px solid #333;">
                        <input type="checkbox" class="product-checkbox" value="' . $product['id'] . '" style="transform: scale(1.2); cursor: pointer;">
                    </td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['id']) . '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">
                        <img src="' . htmlspecialchars($product['product_img']) . '" alt="Product Image" 
                             class="clickable-image" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; cursor: pointer;"
                             onclick="showImageModal(this.src, \'Product Image\')">
                    </td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">';
            
            if (!empty($product['product_qr_code'])) {
                echo '<img src="' . htmlspecialchars($product['product_qr_code']) . '" alt="QR Code" 
                          class="clickable-image" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; cursor: pointer;"
                          onclick="showImageModal(this.src, \'QR Code\')">'; 
            } else {
                echo 'No QR Code';
            }
            
            echo '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['product_name']) . '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['product_category']) . '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['seller_name']) . '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . 
                        (strlen($product['product_description']) > 50 ? 
                        htmlspecialchars(substr($product['product_description'], 0, 50)) . '...' : 
                        htmlspecialchars($product['product_description'])) . 
                    '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['product_quantity']) . '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">';
            
            if ($product['listing_type'] === 'sell') {
                echo '$' . number_format($product['product_cost'], 2);
            } else {
                echo '<ul style="margin: 0; padding-left: 15px;">';
                if (!empty($product['daily_rate'])) {
                    echo '<li>Daily: $' . number_format($product['daily_rate'], 2) . '</li>';
                }
                if (!empty($product['weekly_rate'])) {
                    echo '<li>Weekly: $' . number_format($product['weekly_rate'], 2) . '</li>';
                }
                if (!empty($product['monthly_rate'])) {
                    echo '<li>Monthly: $' . number_format($product['monthly_rate'], 2) . '</li>';
                }
                echo '</ul>';
            }
            
            echo '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . 
                        ($product['listing_type'] === 'sell' ? 'For Sale' : 'For Rent') . 
                    '</td>
                    <td style="padding: 12px; text-align: left; border: 1px solid #333;">';
            
            if ($is_banned) {
                echo '<span style="color: #dc3545; font-weight: bold;">BANNED</span>';
            } else {
                echo '<span style="color: #28a745;">Active</span>';
            }
            
            echo '</td>
                </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    } else {
        echo '<p style="text-align: center; color: #666; margin: 20px 0;">No products found matching the selected filter.</p>';
    }
    
    exit;
}

// Handle ban products request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ban_products') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['product_ids'])) {
        echo json_encode(['success' => false, 'message' => 'No products selected']);
        exit;
    }
    
    try {
        $product_ids = json_decode($_POST['product_ids'], true);
        
        if (!is_array($product_ids) || empty($product_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid product selection']);
            exit;
        }
        
        // Create a banned_products table if it doesn't exist
        $create_table_query = "CREATE TABLE IF NOT EXISTS banned_products (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            product_name VARCHAR(255),
            product_seller_id INT,
            banned_by INT,
            banned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            unbanned_date DATETIME NULL,
            is_unbanned TINYINT(1) DEFAULT 0,
            reason VARCHAR(255) DEFAULT 'Admin decision',
            FOREIGN KEY (banned_by) REFERENCES users(Id)
        )";
        
        if (!mysqli_query($con, $create_table_query)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create banned products table']);
            exit;
        }
        
        // Start transaction
        mysqli_begin_transaction($con);
        
        $success_count = 0;
        $admin_id = $_SESSION['admin_id'];
        $notifications_sent = 0;
        
        foreach ($product_ids as $product_id) {
            // Get product details before banning
            $product_query = "SELECT product_name, product_seller_id FROM product WHERE id = ?";
            $stmt = $con->prepare($product_query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                // Insert into banned_products table
                $ban_query = "INSERT INTO banned_products (product_id, product_name, product_seller_id, banned_by) VALUES (?, ?, ?, ?)";
                $ban_stmt = $con->prepare($ban_query);
                $ban_stmt->bind_param("isii", $product_id, $product['product_name'], $product['product_seller_id'], $admin_id);
                
                if ($ban_stmt->execute()) {
                    // Update the product status to banned
                    $update_query = "UPDATE product SET status = 'banned' WHERE id = ?";
                    $update_stmt = $con->prepare($update_query);
                    $update_stmt->bind_param("i", $product_id);
                    
                    if ($update_stmt->execute()) {
                        $success_count++;
                        
                        // Add notification for the seller
                        $notification_message = "Your product '{$product['product_name']}' has been banned by an administrator. It will no longer be visible to buyers.";
                        if (add_notification($con, $product['product_seller_id'], $notification_message, 'product_banned', $product_id)) {
                            $notifications_sent++;
                        }
                    }
                }
            }
        }
        
        if ($success_count > 0) {
            mysqli_commit($con);
            echo json_encode([
                'success' => true, 
                'count' => $success_count,
                'notifications' => $notifications_sent
            ]);
        } else {
            mysqli_rollback($con);
            echo json_encode(['success' => false, 'message' => 'Failed to ban products']);
        }
    } catch (Exception $e) {
        mysqli_rollback($con);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Handle unban products request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unban_products') {
    header('Content-Type: application/json');
    
    // Basic validation
    if (!isset($_POST['product_ids'])) {
        echo json_encode(['success' => false, 'message' => 'No products selected']);
        exit;
    }
    
    try {
        // Decode product IDs
        $product_ids = json_decode($_POST['product_ids'], true);
        
        if (!is_array($product_ids) || empty($product_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid product selection']);
            exit;
        }
        
        // Simple approach - just update the status without complex checks
        $success_count = 0;
        $notifications_sent = 0;
        $errors = [];
        
        foreach ($product_ids as $product_id) {
            // Get product details before unbanning
            $product_query = "SELECT product_name, product_seller_id FROM product WHERE id = ?";
            $stmt = $con->prepare($product_query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                // Update the product status to approved
                $update_query = "UPDATE product SET status = 'approved' WHERE id = ?";
                $update_stmt = $con->prepare($update_query);
                $update_stmt->bind_param("i", $product_id);
                
                if ($update_stmt->execute()) {
                    // Check if any rows were affected
                    if ($update_stmt->affected_rows > 0) {
                        $success_count++;
                    }
                    
                    // Update the banned_products table
                    $unban_query = "UPDATE banned_products SET unbanned_date = NOW(), is_unbanned = 1 
                                   WHERE product_id = ? AND (is_unbanned = 0 OR is_unbanned IS NULL)";
                    $unban_stmt = $con->prepare($unban_query);
                    $unban_stmt->bind_param("i", $product_id);
                    $unban_stmt->execute();
                    
                    // Add notification for the seller
                    $notification_message = "Good news! Your product '{$product['product_name']}' has been unbanned and is now visible to buyers again.";
                    if (add_notification($con, $product['product_seller_id'], $notification_message, 'product_unbanned', $product_id)) {
                        $notifications_sent++;
                    }
                } else {
                    $errors[] = "Failed to update product #$product_id: " . $con->error;
                }
            } else {
                $errors[] = "Product #$product_id not found in database";
            }
        }
        
        // Return result
        if ($success_count > 0 || $con->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'count' => $success_count,
                'notifications' => $notifications_sent,
                'message' => "Successfully unbanned $success_count product(s)"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to unban any products. ' . implode(', ', $errors),
                'errors' => $errors
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Handle ban user request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ban_user') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($user_id > 0) {
        // Update user status to banned
        $ban_query = "UPDATE users SET status = 'banned' WHERE Id = ?";
        $ban_stmt = $con->prepare($ban_query);
        $ban_stmt->bind_param("i", $user_id);
        
        if ($ban_stmt->execute() && $ban_stmt->affected_rows > 0) {
            // Success - redirect back with success message
            $_SESSION['admin_message'] = "User has been banned successfully.";
            $_SESSION['admin_message_type'] = "success";
        } else {
            // Error - redirect back with error message
            $_SESSION['admin_message'] = "Failed to ban user. User may already be banned.";
            $_SESSION['admin_message_type'] = "error";
        }
    } else {
        $_SESSION['admin_message'] = "Invalid user ID.";
        $_SESSION['admin_message_type'] = "error";
    }
    
    // Redirect back to the users section
    header("Location: admin_dashboard.php#users");
    exit();
}

// Handle unban user request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unban_user') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($user_id > 0) {
        // Update user status to active
        $unban_query = "UPDATE users SET status = 'active' WHERE Id = ?";
        $unban_stmt = $con->prepare($unban_query);
        $unban_stmt->bind_param("i", $user_id);
        
        if ($unban_stmt->execute() && $unban_stmt->affected_rows > 0) {
            // Success - redirect back with success message
            $_SESSION['admin_message'] = "User has been unbanned successfully.";
            $_SESSION['admin_message_type'] = "success";
        } else {
            // Error - redirect back with error message
            $_SESSION['admin_message'] = "Failed to unban user. User may not be banned.";
            $_SESSION['admin_message_type'] = "error";
        }
    } else {
        $_SESSION['admin_message'] = "Invalid user ID.";
        $_SESSION['admin_message_type'] = "error";
    }
    
    // Redirect back to the users section
    header("Location: admin_dashboard.php#users");
    exit();
}

// Debug database connection and table
if ($con) {
    $test_query = "SHOW TABLES LIKE 'users'";
    $test_result = mysqli_query($con, $test_query);
    if (mysqli_num_rows($test_result) == 0) {
        // Users table doesn't exist, create it
        $create_table_query = "CREATE TABLE IF NOT EXISTS users(
            Id int PRIMARY KEY AUTO_INCREMENT,
            Username varchar(200),
            Email varchar(200),
            Age int,
            Parish varchar(50),
            Password varchar(200)
        )";
        mysqli_query($con, $create_table_query);
        
        // Insert a test user if table is empty
        $insert_test_user = "INSERT INTO users (Username, Email, Age, Parish, Password) 
                            VALUES ('Test User', 'test@example.com', 25, 'Test Parish', 'password123')";
        mysqli_query($con, $insert_test_user);
    } else {
        // Check if status column exists in users table
        $check_status_column = "SHOW COLUMNS FROM users LIKE 'status'";
        $status_column_result = mysqli_query($con, $check_status_column);
        
        if (mysqli_num_rows($status_column_result) == 0) {
            // Add status column if it doesn't exist
            $add_status_column = "ALTER TABLE users ADD COLUMN status varchar(20) DEFAULT 'active'";
            mysqli_query($con, $add_status_column);
        }
    }
}

// Check if admin is logged in
if (!isset($_SESSION['admin_valid'])) {
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Admin Dashboard</title>
    <style>
        .sidebar {
            width: 250px;
            min-height: fit-content;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 90px;
            color: white;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 10px 0;
        }
        
        .nav-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
            border-left: 4px solid transparent;
            user-select: none;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
            transform: translateX(5px);
        }
        
        .nav-item:active {
            transform: translateX(3px);
            background: rgba(102, 153, 204, 0.4);
        }
        
        .nav-item.active {
            background: rgba(102, 153, 204, 0.3);
            border-left: 4px solid #6699CC;
            font-weight: bold;
        }

        .nav-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 4px;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 1.2em;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .content-section {
            display: none;
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .content-section.active {
            display: block;
        }
        
        .welcome-bar {
            background: linear-gradient(to right, #6699CC, #7aa7d3);
            color: white;
            padding: 15px 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body style="background-image: url('Background Images/Background Image.jpeg'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <header style="background: transparent; padding: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <img src="Background Images/CommUnity Logo.jpeg" alt="Company Logo" style="height: 70px;">
            <h1 style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%); color: #6699CCFFFF; background: rgba(147, 163, 178, 0.8)">Admin Dashboard</h1>
            <a href="php/logout.php" style="text-decoration: none; padding: 10px 20px; background-color: #ff4444; color: white; border-radius: 5px;">Logout</a>
        </div>
    </header>

    <!-- Navigation Sidebar -->
    <nav class="sidebar">
        <div class="nav-item active" data-section="dashboard">
            <span class="nav-icon">📊</span>
            Dashboard
        </div>
        <div class="nav-item" data-section="users">
            <span class="nav-icon">👥</span>
            Manage Users
        </div>
        <div class="nav-item" data-section="properties">
            <span class="nav-icon">📦</span>
            View Existing Products
        </div>
        <div class="nav-item" data-section="reports">
            <span class="nav-icon">📈</span>
            Reports
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Dashboard Section -->
        <section id="dashboard" class="content-section active">
            <h2>Dashboard Overview</h2>
            <div class="dashboard-content">
                <h2>Welcome, <?php echo $_SESSION['admin_username']; ?>!</h2>
                
                <!-- Welcome Message Bar -->
                <div id="welcome-bar" class="welcome-bar" style="
                    background: linear-gradient(to right, #6699CC, #7aa7d3);
                    color: white;
                    padding: 15px 25px;
                    margin: 20px 0;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <p style="margin: 0; font-size: 15px; line-height: 1.6;">
                        We're glad to have you on board! Manage Users, track rental transactions, and oversee operations efficiently all in one place. 
                        Stay organized, streamline tasks, and keep CommUnity Rentals running smoothly.
                    </p>
                </div>

                <div class="dashboard-stats">
                    <!-- Add dashboard statistics here -->
                </div>
            </div>
        </section>

        <!-- Users Section -->
        <section id="users" class="content-section">
            <h2>Manage Users</h2>
            
            <!-- Top Bar with Stats and Search -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <!-- Compact Stats Card -->
                <div id="totalUsersCard" style="background: rgba(102, 153, 204, 0.2); padding: 10px 20px; border-radius: 8px; display: flex; align-items: center;">
                    <span style="font-weight: bold; color: #6699CC; margin-right: 10px;">Total Users:</span>
                    <span style="font-size: 18px; color: #333;" id="totalUsersCount">
                        <?php 
                        $countQuery = "SELECT COUNT(*) as total FROM users";
                        $countResult = mysqli_query($con, $countQuery);
                        echo mysqli_fetch_assoc($countResult)['total']; 
                        ?>
                    </span>
                </div>

                <!-- Search Form -->
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select id="searchCriteria" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        <option value="id">Search by ID</option>
                        <option value="username">Search by Name</option>
                        <option value="email">Search by Email</option>
                    </select>
                    <input type="text" id="searchInput" placeholder="Enter search term..." 
                           style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
                    <button onclick="searchUsers()" 
                            style="background: #6699CC; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                        Search
                    </button>
                    <button onclick="resetSearch()" 
                            style="background: #666; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                        Reset
                    </button>
                </div>
            </div>

            <div class="user-list" id="userTableContainer">
                <?php
                // Initialize search condition
                $searchCondition = "";
                if (isset($_GET['search']) && isset($_GET['criteria'])) {
                    $search = mysqli_real_escape_string($con, $_GET['search']);
                    $criteria = mysqli_real_escape_string($con, $_GET['criteria']);
                    
                    switch ($criteria) {
                        case 'id':
                            $searchCondition = "WHERE Id = '$search'";
                            break;
                        case 'username':
                            $searchCondition = "WHERE Username LIKE '%$search%'";
                            break;
                        case 'email':
                            $searchCondition = "WHERE Email LIKE '%$search%'";
                            break;
                    }
                }

                // Fetch and display users with search condition
                $query = "SELECT * FROM users $searchCondition ORDER BY Id DESC";
                $result = mysqli_query($con, $query);

                if (!$result) {
                    echo "<div class='error'>Error loading users: " . mysqli_error($con) . "</div>";
                    exit();
                }

                // Display admin messages if any
                if (isset($_SESSION['admin_message'])) {
                    $message_type = isset($_SESSION['admin_message_type']) ? $_SESSION['admin_message_type'] : 'info';
                    $bg_color = ($message_type == 'error') ? '#f8d7da' : (($message_type == 'success') ? '#d4edda' : '#d1ecf1');
                    $text_color = ($message_type == 'error') ? '#721c24' : (($message_type == 'success') ? '#155724' : '#0c5460');
                    
                    echo "<div style='padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; background-color: $bg_color; color: $text_color;'>";
                    echo $_SESSION['admin_message'];
                    echo "</div>";
                    
                    // Clear the message so it doesn't show again on refresh
                    unset($_SESSION['admin_message']);
                    unset($_SESSION['admin_message_type']);
                }

                if (mysqli_num_rows($result) > 0) {
                    echo "<div style='overflow-x: auto;'>";
                    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                            <thead style='background: rgba(102, 153, 204, 0.2);'>
                                <tr>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>ID</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Username</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Email</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Age</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Parish</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Status</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Balance</th>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Actions</th>
                                </tr>
                            </thead>
                            <tbody>";
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $is_banned = isset($row['status']) && $row['status'] === 'banned';
                        $row_style = $is_banned ? 'background-color: rgba(255, 200, 200, 0.3);' : '';
                        $status_badge = $is_banned 
                            ? '<span style="background-color: #e74c3c; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">Banned</span>' 
                            : '<span style="background-color: #2ecc71; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">Active</span>';
                        
                        // Format account balance
                        $account_balance = isset($row['AccountBalance']) ? number_format((float)$row['AccountBalance'], 2) : '0.00';
                        
                        echo "<tr style='border-bottom: 1px solid rgba(102, 153, 204, 0.2); $row_style'>
                                <td style='padding: 12px;'>" . htmlspecialchars($row['Id']) . "</td>
                                <td style='padding: 12px;'>" . htmlspecialchars($row['Username']) . "</td>
                                <td style='padding: 12px;'>" . htmlspecialchars($row['Email']) . "</td>
                                <td style='padding: 12px;'>" . htmlspecialchars($row['Age']) . "</td>
                                <td style='padding: 12px;'>" . htmlspecialchars($row['Parish']) . "</td>
                                <td style='padding: 12px;'>" . $status_badge . "</td>
                                <td style='padding: 12px;'>$" . $account_balance . "</td>
                                <td style='padding: 12px;'>";
                        
                        // Edit and Delete buttons
                        echo "<button onclick='editUser(" . htmlspecialchars($row['Id']) . ")' style='background: #6699CC; color: white; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer; border-radius: 3px;'>Edit</button>";
                        echo "<button onclick='deleteUser(" . htmlspecialchars($row['Id']) . ")' style='background: #ff4444; color: white; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer; border-radius: 3px;'>Delete</button>";
                        
                        // Ban/Unban button
                        if ($is_banned) {
                            echo "<form method='post' action='admin_dashboard.php' style='display: inline;'>
                                    <input type='hidden' name='action' value='unban_user'>
                                    <input type='hidden' name='user_id' value='" . htmlspecialchars($row['Id']) . "'>
                                    <button type='submit' style='background: #27ae60; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;'>Unban</button>
                                  </form>";
                        } else {
                            echo "<form method='post' action='admin_dashboard.php' style='display: inline;'>
                                    <input type='hidden' name='action' value='ban_user'>
                                    <input type='hidden' name='user_id' value='" . htmlspecialchars($row['Id']) . "'>
                                    <button type='submit' style='background: #e74c3c; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;'>Ban</button>
                                  </form>";
                        }
                        
                        echo "</td>
                            </tr>";
                    }
                    echo "</tbody></table></div>";
                } else {
                    echo "<div style='text-align: center; padding: 20px; background: rgba(255, 255, 255, 0.1); border-radius: 8px;'>
                            <p style='margin: 0; color: #666;'>No users found.</p>
                          </div>";
                }
                ?>
            </div>

            <!-- Add User Button -->
            <div style="margin-top: 20px;">
                <button onclick="addNewUser()" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px;">
                    Add New User
                </button>
            </div>
        </section>

        <!-- Properties Section -->
        <section id="properties" class="content-section">
            <h2>View Existing Products</h2>
            
            <!-- Seller Filter -->
            <div style="margin-bottom: 20px; background: rgba(255, 255, 255, 0.8); padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0; margin-bottom: 10px;">Filter Options</h3>
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <label for="seller_filter" style="font-weight: bold; margin-right: 10px;">Filter by Seller:</label>
                        <select id="seller_filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                            <option value="">All Sellers</option>
                            <?php
                            // Get all unique sellers
                            $sellers_query = "SELECT DISTINCT u.Id, u.Username 
                                             FROM users u 
                                             INNER JOIN product p ON u.Id = p.product_seller_id 
                                             ORDER BY u.Username";
                            $sellers_result = mysqli_query($con, $sellers_query);
                            
                            if ($sellers_result && mysqli_num_rows($sellers_result) > 0) {
                                while ($seller = mysqli_fetch_assoc($sellers_result)) {
                                    echo '<option value="' . $seller['Id'] . '">' . htmlspecialchars($seller['Username']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <button id="apply_filter" style="background: #6699CC; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Apply Filter</button>
                    <button id="reset_filter" style="background: #999; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Reset</button>
                </div>
            </div>
            
            <!-- Ban and Unban Buttons -->
            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button id="unban_selected_products" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; display: none;">
                    <i class="fas fa-check-circle" style="margin-right: 5px;"></i> Unban Selected Products
                </button>
                <button id="ban_selected_products" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; display: none;">
                    <i class="fas fa-ban" style="margin-right: 5px;"></i> Ban Selected Products
                </button>
            </div>
            
            <div class="property-list">
                <div id="products_container">
                <?php
                // Get all products from the database
                $products_query = "SELECT p.*, u.Username as seller_name FROM product p 
                                  LEFT JOIN users u ON p.product_seller_id = u.Id 
                                  ORDER BY p.id DESC";
                $products_result = mysqli_query($con, $products_query);

                if ($products_result && mysqli_num_rows($products_result) > 0) {
                    echo '<div style="overflow-x: auto; width: 100%;">
                            <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 2px solid #333;">
                                <thead>
                                    <tr style="background-color: #f2f2f2;">
                                        <th style="padding: 12px; text-align: center; border: 1px solid #333;">
                                            <input type="checkbox" id="select_all_products" style="transform: scale(1.2); cursor: pointer;">
                                        </th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">ID</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Image</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">QR Code</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Name</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Category</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Seller</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Description</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Quantity</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Price/Rates</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Listing Type</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #333;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>';
                    
                    while ($product = mysqli_fetch_assoc($products_result)) {
                        $is_banned = isset($product['status']) && $product['status'] === 'banned';
                        $row_style = $is_banned ? 'background-color: rgba(255, 200, 200, 0.3);' : '';
                        
                        echo '<tr class="product-row" data-product-id="' . $product['id'] . '" style="' . $row_style . '">
                                <td style="padding: 12px; text-align: center; border: 1px solid #333;">
                                    <input type="checkbox" class="product-checkbox" value="' . $product['id'] . '" style="transform: scale(1.2); cursor: pointer;">
                                </td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['id']) . '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">
                                    <img src="' . htmlspecialchars($product['product_img']) . '" alt="Product" style="width: 60px; height: 60px; object-fit: cover; margin-right: 12px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                </td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">';
                        
                        if (!empty($product['product_qr_code'])) {
                            echo '<img src="' . htmlspecialchars($product['product_qr_code']) . '" alt="QR Code" 
                                      class="clickable-image" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; cursor: pointer;"
                                      onclick="showImageModal(this.src, \'QR Code\')">'; 
                        } else {
                            echo 'No QR Code';
                        }
                        
                        echo '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['product_name']) . '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['product_category']) . '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['seller_name']) . '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . 
                                    (strlen($product['product_description']) > 50 ? 
                                    htmlspecialchars(substr($product['product_description'], 0, 50)) . '...' : 
                                    htmlspecialchars($product['product_description'])) . 
                                '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . htmlspecialchars($product['product_quantity']) . '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">';
                        
                        if ($product['listing_type'] === 'sell') {
                            echo '$' . number_format($product['product_cost'], 2);
                        } else {
                            echo '<ul style="margin: 0; padding-left: 15px;">';
                            if (!empty($product['daily_rate'])) {
                                echo '<li>Daily: $' . number_format($product['daily_rate'], 2) . '</li>';
                            }
                            if (!empty($product['weekly_rate'])) {
                                echo '<li>Weekly: $' . number_format($product['weekly_rate'], 2) . '</li>';
                            }
                            if (!empty($product['monthly_rate'])) {
                                echo '<li>Monthly: $' . number_format($product['monthly_rate'], 2) . '</li>';
                            }
                            echo '</ul>';
                        }
                        
                        echo '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">' . 
                                    ($product['listing_type'] === 'sell' ? 'For Sale' : 'For Rent') . 
                                '</td>
                                <td style="padding: 12px; text-align: left; border: 1px solid #333;">';
                        
                        if ($is_banned) {
                            echo '<span style="color: #dc3545; font-weight: bold;">BANNED</span>';
                        } else {
                            echo '<span style="color: #28a745;">Active</span>';
                        }
                        
                        echo '</td>
                            </tr>';
                    }
                    
                    echo '</tbody>
                        </table>
                    </div>';
                } else {
                    echo '<p style="text-align: center; color: #666; margin: 20px 0;">No products found in the database.</p>';
                }
                ?>
                </div>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const sellerFilter = document.getElementById('seller_filter');
                    const applyFilterBtn = document.getElementById('apply_filter');
                    const resetFilterBtn = document.getElementById('reset_filter');
                    const productsContainer = document.getElementById('products_container');
                    const banSelectedBtn = document.getElementById('ban_selected_products');
                    const unbanSelectedBtn = document.getElementById('unban_selected_products');
                    const selectAllCheckbox = document.getElementById('select_all_products');
                    
                    // Apply filter
                    applyFilterBtn.addEventListener('click', function() {
                        const sellerId = sellerFilter.value;
                        filterProducts(sellerId);
                    });
                    
                    // Reset filter
                    resetFilterBtn.addEventListener('click', function() {
                        sellerFilter.value = '';
                        filterProducts('');
                    });
                    
                    // Filter products function
                    function filterProducts(sellerId) {
                        // Show loading indicator
                        productsContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><p>Loading...</p></div>';
                        
                        // Create AJAX request
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', `admin_dashboard.php?action=filter_products&seller_id=${sellerId}`, true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        
                        xhr.onload = function() {
                            if (this.status === 200) {
                                productsContainer.innerHTML = this.responseText;
                                setupCheckboxListeners(); // Re-setup checkbox listeners after content is loaded
                            } else {
                                productsContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><p>Error loading products. Please try again.</p></div>';
                            }
                        };
                        
                        xhr.onerror = function() {
                            productsContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><p>Error loading products. Please try again.</p></div>';
                        };
                        
                        xhr.send();
                    }
                    
                    // Setup checkbox listeners
                    function setupCheckboxListeners() {
                        // Select all checkbox
                        const newSelectAllCheckbox = document.getElementById('select_all_products');
                        if (newSelectAllCheckbox) {
                            newSelectAllCheckbox.addEventListener('change', function() {
                                const checkboxes = document.querySelectorAll('.product-checkbox');
                                checkboxes.forEach(cb => {
                                    cb.checked = this.checked;
                                });
                                updateButtonVisibility();
                            });
                        }
                        
                        // Individual checkboxes
                        const checkboxes = document.querySelectorAll('.product-checkbox');
                        checkboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', updateButtonVisibility);
                        });
                        
                        // Make rows clickable to toggle checkbox
                        const productRows = document.querySelectorAll('.product-row');
                        productRows.forEach(row => {
                            row.addEventListener('click', function(e) {
                                // Don't toggle if clicking on a link, image, or checkbox directly
                                if (e.target.tagName === 'A' || e.target.tagName === 'IMG' || e.target.tagName === 'INPUT') {
                                    return;
                                }
                                
                                const checkbox = this.querySelector('.product-checkbox');
                                checkbox.checked = !checkbox.checked;
                                
                                // Trigger change event
                                const event = new Event('change');
                                checkbox.dispatchEvent(event);
                            });
                        });
                        
                        updateButtonVisibility();
                    }
                    
                    // Function to update button visibility based on selections
                    function updateButtonVisibility() {
                        const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
                        const count = selectedCheckboxes.length;
                        
                        if (count > 0) {
                            // Check if any banned products are selected
                            let bannedSelected = 0;
                            let unbannedSelected = 0;
                            
                            selectedCheckboxes.forEach(checkbox => {
                                const row = checkbox.closest('tr');
                                const statusCell = row.querySelector('td:last-child');
                                const isBanned = statusCell && statusCell.textContent.trim().includes('BANNED');
                                
                                if (isBanned) {
                                    bannedSelected++;
                                } else {
                                    unbannedSelected++;
                                }
                            });
                            
                            // Show/hide appropriate buttons
                            banSelectedBtn.style.display = unbannedSelected > 0 ? 'block' : 'none';
                            unbanSelectedBtn.style.display = bannedSelected > 0 ? 'block' : 'none';
                            
                            // Update button text with count
                            if (unbannedSelected > 0) {
                                banSelectedBtn.innerHTML = `<i class="fas fa-ban" style="margin-right: 5px;"></i> Ban Selected Products (${unbannedSelected})`;
                            }
                            if (bannedSelected > 0) {
                                unbanSelectedBtn.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 5px;"></i> Unban Selected Products (${bannedSelected})`;
                            }
                        } else {
                            banSelectedBtn.style.display = 'none';
                            unbanSelectedBtn.style.display = 'none';
                        }
                        
                        // Debug - log to console
                        console.log('Selected checkboxes:', count);
                        console.log('Ban button display:', banSelectedBtn.style.display);
                        console.log('Unban button display:', unbanSelectedBtn.style.display);
                    }
                    
                    // Ban selected products
                    banSelectedBtn.addEventListener('click', function() {
                        const selectedProducts = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
                        
                        if (selectedProducts.length === 0) {
                            alert('Please select at least one product to ban.');
                            return;
                        }
                        
                        if (confirm(`Are you sure you want to ban ${selectedProducts.length} selected product(s)? This will make them invisible to buyers.`)) {
                            // Show loading state
                            const originalText = banSelectedBtn.innerHTML;
                            banSelectedBtn.innerHTML = 'Banning...';
                            banSelectedBtn.disabled = true;
                            
                            // Create AJAX request
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', 'admin_dashboard.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            
                            xhr.onload = function() {
                                if (this.status === 200) {
                                    try {
                                        const response = JSON.parse(this.responseText);
                                        
                                        if (response.success) {
                                            alert(`Successfully banned ${response.count} product(s).`);
                                            // Refresh the product list
                                            filterProducts(document.getElementById('seller_filter').value);
                                        } else {
                                            alert('Error: ' + response.message);
                                        }
                                    } catch (e) {
                                        alert('Error processing response.');
                                    }
                                } else {
                                    alert('Request failed. Please try again.');
                                }
                                
                                // Reset button state
                                banSelectedBtn.innerHTML = originalText;
                                banSelectedBtn.disabled = false;
                            };
                            
                            xhr.send(`action=ban_products&product_ids=${JSON.stringify(selectedProducts)}`);
                        }
                    });
                    
                    // Unban selected products
                    unbanSelectedBtn.addEventListener('click', function() {
                        const selectedProducts = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
                        
                        if (selectedProducts.length === 0) {
                            alert('Please select at least one product to unban.');
                            return;
                        }
                        
                        if (confirm(`Are you sure you want to unban ${selectedProducts.length} selected product(s)? This will make them visible to buyers again.`)) {
                            // Show loading state
                            const originalText = unbanSelectedBtn.innerHTML;
                            unbanSelectedBtn.innerHTML = 'Unbanning...';
                            unbanSelectedBtn.disabled = true;
                            
                            // Create AJAX request
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', 'admin_dashboard.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            
                            xhr.onload = function() {
                                console.log("Response status:", this.status);
                                console.log("Response text:", this.responseText);
                                
                                if (this.status === 200) {
                                    try {
                                        const response = JSON.parse(this.responseText);
                                        console.log("Parsed response:", response);
                                        
                                        if (response.success) {
                                            alert(`Successfully unbanned ${response.count} product(s).`);
                                            // Refresh the product list
                                            filterProducts(document.getElementById('seller_filter').value);
                                        } else {
                                            const errorMsg = response.message || 'Unknown error occurred';
                                            alert('Error: ' + errorMsg);
                                            console.error('Error details:', response.errors || []);
                                        }
                                    } catch (e) {
                                        console.error("JSON parse error:", e);
                                        console.error("Raw response:", this.responseText);
                                        alert('Error processing response: ' + e.message);
                                    }
                                } else {
                                    alert('Request failed. Server returned status: ' + this.status);
                                }
                                
                                // Reset button state
                                unbanSelectedBtn.innerHTML = originalText;
                                unbanSelectedBtn.disabled = false;
                            };
                            
                            xhr.onerror = function() {
                                console.error("Network error occurred");
                                alert('Network error occurred. Please check your connection and try again.');
                                unbanSelectedBtn.innerHTML = originalText;
                                unbanSelectedBtn.disabled = false;
                            };
                            
                            const requestData = `action=unban_products&product_ids=${JSON.stringify(selectedProducts)}`;
                            console.log("Sending request:", requestData);
                            xhr.send(requestData);
                        }
                    });
                    
                    // Initial setup
                    setupCheckboxListeners();
                });
            </script>
        </section>

        <!-- Reports Section -->
        <section id="reports" class="content-section">
            <h2 style="color: #2c3e50; font-size: 28px; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Reports</h2>
            <div class="reports-content">
                <!-- Overdue Rental Reports -->
                <div class="report-section" style="background: rgba(255, 255, 255, 0.9); padding: 25px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <h3 style="color: #e74c3c; font-size: 22px; margin-bottom: 20px; display: flex; align-items: center;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i> Overdue Rental Reports
                    </h3>
                    
                    <?php
                    // Get all rental reports
                    $reports_query = "SELECT rr.*, 
                                     rp.rental_start_date, rp.rental_end_date, rp.rental_price, rp.status as rental_status,
                                     p.product_name, p.product_img,
                                     buyer.Username as buyer_name, buyer.Email as buyer_email,
                                     seller.Username as seller_name, seller.Email as seller_email
                                     FROM rental_reports rr
                                     JOIN rental_products rp ON rr.rental_id = rp.id
                                     JOIN product p ON rr.product_id = p.id
                                     JOIN users buyer ON rr.buyer_id = buyer.Id
                                     JOIN users seller ON rr.seller_id = seller.Id
                                     ORDER BY rr.report_date DESC";
                    
                    $reports_result = mysqli_query($con, $reports_query);
                    
                    if ($reports_result && mysqli_num_rows($reports_result) > 0) {
                        echo '<div style="overflow-x: auto; width: 100%;">
                                <table class="table" style="width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                    <thead>
                                        <tr style="background-color: #3498db; color: white;">
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">ID</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Product</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Seller</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Buyer</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Rental Period</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Days Overdue</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Report Date</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Status</th>
                                            <th style="padding: 15px; text-align: left; border: none; font-size: 15px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                        
                        while ($report = mysqli_fetch_assoc($reports_result)) {
                            // Determine row style based on status
                            $row_style = '';
                            $status_badge = '';
                            
                            switch ($report['status']) {
                                case 'pending':
                                    $row_style = 'background-color: rgba(255, 243, 205, 0.5);';
                                    $status_badge = '<span class="badge" style="background-color: #ffc107; color: #212529; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 13px;">Pending</span>';
                                    break;
                                case 'reviewed':
                                    $row_style = 'background-color: rgba(209, 236, 241, 0.5);';
                                    $status_badge = '<span class="badge" style="background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 13px;">Reviewed</span>';
                                    break;
                                case 'resolved':
                                    $row_style = 'background-color: rgba(212, 237, 218, 0.5);';
                                    $status_badge = '<span class="badge" style="background-color: #28a745; color: white; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 13px;">Resolved</span>';
                                    break;
                            }
                            
                            // Calculate estimated late fee
                            $late_fee_rate = $report['rental_price'] * 0.05; // 5% of rental price per day
                            $estimated_late_fee = $report['days_overdue'] * $late_fee_rate;
                            
                            // Cap late fee at the original rental price
                            if ($estimated_late_fee > $report['rental_price']) {
                                $estimated_late_fee = $report['rental_price'];
                            }
                            
                            // Format dates
                            $start_date = date('M d, Y', strtotime($report['rental_start_date']));
                            $end_date = date('M d, Y', strtotime($report['rental_end_date']));
                            $report_date = date('M d, Y g:i A', strtotime($report['report_date']));
                            
                            echo '<tr style="' . $row_style . ' border-bottom: 1px solid #eee;">
                                    <td style="padding: 15px; text-align: left; border: none; font-size: 14px; font-weight: 600;">' . $report['id'] . '</td>
                                    <td style="padding: 15px; text-align: left; border: none;">
                                        <div style="display: flex; align-items: center;">
                                            <img src="' . $report['product_img'] . '" alt="Product" style="width: 60px; height: 60px; object-fit: cover; margin-right: 12px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            <div style="font-size: 15px; font-weight: 500; color: #2c3e50;">' . htmlspecialchars($report['product_name']) . '</div>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; text-align: left; border: none;">
                                        <div style="font-size: 15px; font-weight: 500; color: #2c3e50;">' . htmlspecialchars($report['seller_name']) . '</div>
                                        <div style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">' . htmlspecialchars($report['seller_email']) . '</div>
                                    </td>
                                    <td style="padding: 15px; text-align: left; border: none;">
                                        <div style="font-size: 15px; font-weight: 500; color: #2c3e50;">' . htmlspecialchars($report['buyer_name']) . '</div>
                                        <div style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">' . htmlspecialchars($report['buyer_email']) . '</div>
                                    </td>
                                    <td style="padding: 15px; text-align: left; border: none;">
                                        <div style="font-size: 14px; color: #2c3e50;">' . $start_date . ' to ' . $end_date . '</div>
                                        <div style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">Price: <span style="font-weight: 600; color: #16a085;">$' . number_format($report['rental_price'], 2) . '</span></div>
                                    </td>
                                    <td style="padding: 15px; text-align: left; border: none;">
                                        <div style="color: #e74c3c; font-weight: 600; font-size: 16px;">' . $report['days_overdue'] . ' days</div>
                                        <div style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">Est. Late Fee: <span style="font-weight: 600; color: #e74c3c;">$' . number_format($estimated_late_fee, 2) . '</span></div>
                                    </td>
                                    <td style="padding: 15px; text-align: left; border: none; font-size: 14px; color: #2c3e50;">' . $report_date . '</td>
                                    <td style="padding: 15px; text-align: left; border: none;">' . $status_badge . '</td>
                                    <td style="padding: 15px; text-align: left; border: none;">
                                        <button class="btn btn-primary view-report-btn" 
                                                data-report-id="' . $report['id'] . '"
                                                data-product-name="' . htmlspecialchars($report['product_name']) . '"
                                                data-seller-name="' . htmlspecialchars($report['seller_name']) . '"
                                                data-buyer-name="' . htmlspecialchars($report['buyer_name']) . '"
                                                data-buyer-id="' . $report['buyer_id'] . '"
                                                data-days-overdue="' . $report['days_overdue'] . '"
                                                data-report-reason="' . htmlspecialchars($report['report_reason']) . '"
                                                data-report-date="' . $report_date . '"
                                                data-rental-status="' . $report['rental_status'] . '"
                                                data-admin-notes="' . htmlspecialchars($report['admin_notes'] ?? '') . '"
                                                style="margin-bottom: 8px; width: 100%; border-radius: 6px; font-size: 14px; font-weight: 500; padding: 8px 12px;">
                                            <i class="fas fa-eye" style="margin-right: 5px;"></i> View Details
                                        </button>';
                            
                            if ($report['status'] === 'pending') {
                                echo '<button class="btn btn-info mark-reviewed-btn" 
                                              data-report-id="' . $report['id'] . '"
                                              style="margin-bottom: 5px; width: 100%; border-radius: 6px; font-size: 14px; font-weight: 500; padding: 8px 12px;">
                                            <i class="fas fa-check"></i> Mark Reviewed
                                        </button>';
                            }
                            
                            if ($report['status'] !== 'resolved') {
                                echo '<button class="btn btn-success mark-resolved-btn" 
                                              data-report-id="' . $report['id'] . '"
                                              style="width: 100%; border-radius: 6px; font-size: 14px; font-weight: 500; padding: 8px 12px;">
                                            <i class="fas fa-check-double"></i> Mark Resolved
                                        </button>';
                            }
                            
                            echo '</td>
                                </tr>';
                        }
                        
                        echo '</tbody>
                            </table>
                        </div>';
                    } else {
                        echo '<div class="alert alert-info" style="text-align: center; padding: 20px; border-radius: 8px; background-color: #d1ecf1; color: #0c5460; border: none; font-size: 16px;">
                                <i class="fas fa-info-circle" style="margin-right: 10px; font-size: 20px;"></i> No overdue rental reports found.
                              </div>';
                    }
                    ?>
                </div>
                
                <!-- Report Detail Modal -->
                <div id="reportDetailModal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                            <div class="modal-header bg-primary text-white" style="padding: 15px 20px;">
                                <h5 class="modal-title" style="font-size: 20px; font-weight: 600;">Rental Report Details</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" style="padding: 25px;">
                                <div class="report-details">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Product:</h6>
                                            <p id="modal-product-name" style="font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 20px;"></p>
                                            
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Seller:</h6>
                                            <p id="modal-seller-name" style="font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 20px;"></p>
                                            
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Buyer:</h6>
                                            <p id="modal-buyer-name" style="font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 20px;"></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Days Overdue:</h6>
                                            <p id="modal-days-overdue" style="font-size: 18px; font-weight: 700; color: #e74c3c; margin-bottom: 20px;"></p>
                                            
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Report Date:</h6>
                                            <p id="modal-report-date" style="font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 20px;"></p>
                                            
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Current Rental Status:</h6>
                                            <p id="modal-rental-status" style="font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 20px;"></p>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Seller's Report Reason:</h6>
                                            <div id="modal-report-reason" class="p-4 bg-light rounded" style="border-left: 4px solid #3498db; font-size: 16px; line-height: 1.6; color: #2c3e50; font-weight: 700;"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <h6 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px;">Admin Notes:</h6>
                                            <textarea id="modal-admin-notes" class="form-control" rows="4" style="border-radius: 8px; padding: 12px; font-size: 16px; border-color: #ddd;"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #eee;">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 6px; font-size: 15px; padding: 8px 16px;">Close</button>
                                <button type="button" class="btn btn-primary save-admin-notes" data-report-id="" style="border-radius: 6px; font-size: 15px; padding: 8px 16px; font-weight: 500;">Save Notes</button>
                                <button type="button" class="btn btn-warning send-warning-btn" data-report-id="" data-buyer-id="" style="border-radius: 6px; font-size: 15px; padding: 8px 16px; font-weight: 500; background-color: #f39c12; border-color: #f39c12;">
                                    <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i> Send Warning to Buyer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" style="display: none; position: fixed; z-index: 1000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.9);">
        <span class="close-modal" style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer;" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="modalImage" style="margin: auto; display: block; max-width: 80%; max-height: 80%;">
        <div id="modalCaption" class="modal-caption" style="margin: auto; display: block; width: 80%; max-width: 700px; text-align: center; color: #ccc; padding: 10px 0; height: 150px;"></div>
    </div>
    
    <script>
        // Get all navigation items and content sections
        const navItems = document.querySelectorAll('.nav-item');
        const contentSections = document.querySelectorAll('.content-section');

        // Function to activate a specific section
        function activateSection(sectionId) {
            // Remove active class from all navigation items and content sections
            navItems.forEach(nav => nav.classList.remove('active'));
            contentSections.forEach(section => section.classList.remove('active'));

            // Add active class to the navigation item with matching data-section
            const navItem = document.querySelector(`.nav-item[data-section="${sectionId}"]`);
            if (navItem) {
                navItem.classList.add('active');
            }

            // Show corresponding content section
            const section = document.getElementById(sectionId);
            if (section) {
                section.classList.add('active');
            }
            
            // Show/hide welcome bar based on dashboard selection
            const welcomeBar = document.getElementById('welcome-bar');
            if (welcomeBar) {
                welcomeBar.style.display = sectionId === 'dashboard' ? 'block' : 'none';
            }
            
            // Initialize report-related functionality only when reports section is active
            if (sectionId === 'reports') {
                initializeReportFunctionality();
            }
            
            // Update URL hash
            window.location.hash = sectionId;
        }

        // Check for hash in URL when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Get hash from URL (remove the # symbol)
            let hash = window.location.hash.substring(1);
            
            // If hash exists and corresponds to a valid section, activate that section
            if (hash && document.getElementById(hash)) {
                activateSection(hash);
            } else {
                // Default to dashboard if no valid hash
                activateSection('dashboard');
            }
        });

        // Add click event listeners to navigation items
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                const sectionId = item.getAttribute('data-section');
                activateSection(sectionId);
            });
        });
        
        // Function to initialize report-related functionality
        function initializeReportFunctionality() {
            // View report button click event
            document.querySelectorAll('.view-report-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-report-id');
                    const productName = this.getAttribute('data-product-name');
                    const sellerName = this.getAttribute('data-seller-name');
                    const buyerName = this.getAttribute('data-buyer-name');
                    const daysOverdue = this.getAttribute('data-days-overdue');
                    const reportReason = this.getAttribute('data-report-reason');
                    const reportDate = this.getAttribute('data-report-date');
                    const rentalStatus = this.getAttribute('data-rental-status');
                    const adminNotes = this.getAttribute('data-admin-notes');
                    const buyerId = this.getAttribute('data-buyer-id');
                    
                    showReportDetailModal(reportId, productName, sellerName, buyerName, daysOverdue, reportReason, reportDate, rentalStatus, adminNotes, buyerId);
                });
            });
            
            // Mark reviewed button click event
            document.querySelectorAll('.mark-reviewed-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-report-id');
                    
                    if (confirm('Are you sure you want to mark this report as reviewed?')) {
                        // Create AJAX request
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', 'admin_dashboard.php', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        
                        xhr.onload = function() {
                            if (this.status === 200) {
                                try {
                                    const response = JSON.parse(this.responseText);
                                    
                                    if (response.success) {
                                        alert('Report marked as reviewed successfully.');
                                    } else {
                                        alert('Error: ' + response.message);
                                    }
                                } catch (e) {
                                    alert('Error processing response.');
                                }
                            } else {
                                alert('Request failed. Please try again.');
                            }
                        };
                        
                        xhr.send(`action=mark_report_reviewed&report_id=${reportId}`);
                    }
                });
            });
            
            // Mark resolved button click event
            document.querySelectorAll('.mark-resolved-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-report-id');
                    
                    if (confirm('Are you sure you want to mark this report as resolved?')) {
                        // Create AJAX request
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', 'admin_dashboard.php', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        
                        xhr.onload = function() {
                            if (this.status === 200) {
                                try {
                                    const response = JSON.parse(this.responseText);
                                    
                                    if (response.success) {
                                        alert('Report marked as resolved successfully.');
                                    } else {
                                        alert('Error: ' + response.message);
                                    }
                                } catch (e) {
                                    alert('Error processing response.');
                                }
                            } else {
                                alert('Request failed. Please try again.');
                            }
                        };
                        
                        xhr.send(`action=mark_report_resolved&report_id=${reportId}`);
                    }
                });
            });
            
            // Send warning button click event
            document.querySelector('.send-warning-btn').addEventListener('click', function() {
                const reportId = this.getAttribute('data-report-id');
                const buyerId = this.getAttribute('data-buyer-id');
                
                if (confirm('Are you sure you want to send a warning notification to the buyer? This will inform them that they risk a two-week ban if they receive three more reports.')) {
                    // Create AJAX request
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'admin_dashboard.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    
                    xhr.onload = function() {
                        if (this.status === 200) {
                            try {
                                const response = JSON.parse(this.responseText);
                                
                                if (response.success) {
                                    alert('Warning notification sent to buyer successfully.');
                                } else {
                                    alert('Error: ' + response.message);
                                }
                            } catch (e) {
                                alert('Error processing response.');
                            }
                        } else {
                            alert('Request failed. Please try again.');
                        }
                    };
                    
                    xhr.send(`action=send_warning_notification&report_id=${reportId}&buyer_id=${buyerId}`);
                }
            });
            
            // Save admin notes button click event
            document.querySelector('.save-admin-notes').addEventListener('click', function() {
                const reportId = this.getAttribute('data-report-id');
                const adminNotes = document.getElementById('modal-admin-notes').value;
                
                // Create AJAX request
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'admin_dashboard.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            
                            if (response.success) {
                                alert('Admin notes saved successfully.');
                            } else {
                                alert('Error: ' + response.message);
                            }
                        } catch (e) {
                            alert('Error processing response.');
                        }
                    } else {
                        alert('Request failed. Please try again.');
                    }
                };
                
                xhr.send(`action=save_admin_notes&report_id=${reportId}&admin_notes=${encodeURIComponent(adminNotes)}`);
            });
        }
        
        // Report detail modal functions
        function showReportDetailModal(reportId, productName, sellerName, buyerName, daysOverdue, reportReason, reportDate, rentalStatus, adminNotes, buyerId) {
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-seller-name').textContent = sellerName;
            document.getElementById('modal-buyer-name').textContent = buyerName;
            document.getElementById('modal-days-overdue').textContent = daysOverdue;
            document.getElementById('modal-report-reason').textContent = reportReason;
            document.getElementById('modal-report-date').textContent = reportDate;
            document.getElementById('modal-rental-status').textContent = rentalStatus;
            document.getElementById('modal-admin-notes').value = adminNotes || '';
            
            // Set report ID for save notes button
            document.querySelector('.save-admin-notes').setAttribute('data-report-id', reportId);
            
            // Set report ID and buyer ID for warning button
            document.querySelector('.send-warning-btn').setAttribute('data-report-id', reportId);
            document.querySelector('.send-warning-btn').setAttribute('data-buyer-id', buyerId);
            
            // Show modal
            $('#reportDetailModal').modal('show');
        }
        
        // Initialize reports functionality if reports section is initially active
        if (document.querySelector('.nav-item[data-section="reports"]').classList.contains('active')) {
            initializeReportFunctionality();
        }
        
        // Function to add new user
        function addNewUser() {
            // Add new user functionality
            alert('Add new user functionality will be implemented here');
        }

        // Function to edit user
        function editUser(userId) {
            // Edit user functionality
            alert('Edit user functionality will be implemented for user ID: ' + userId);
        }

        // Function to delete user
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                // Delete user functionality
                alert('Delete user functionality will be implemented for user ID: ' + userId);
            }
        }

        async function searchUsers() {
            const searchInput = document.getElementById('searchInput').value;
            const searchCriteria = document.getElementById('searchCriteria').value;
            
            try {
                // Load filtered users
                const response = await fetch(`php/load_users.php?search=${encodeURIComponent(searchInput)}&criteria=${searchCriteria}`);
                if (!response.ok) throw new Error('Network response was not ok');
                const html = await response.text();
                document.getElementById('userTableContainer').innerHTML = html;

                // Update total count for filtered results
                const countResponse = await fetch(`admin_dashboard.php?action=get_filtered_count&search=${encodeURIComponent(searchInput)}&criteria=${searchCriteria}`);
                if (!countResponse.ok) throw new Error('Network response was not ok');
                const countData = await countResponse.json();
                document.getElementById('totalUsersCount').textContent = countData.count;
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading users. Please try again.');
            }
        }

        async function resetSearch() {
            document.getElementById('searchInput').value = '';
            try {
                // Reset user table
                const response = await fetch('php/load_users.php');
                if (!response.ok) throw new Error('Network response was not ok');
                const html = await response.text();
                document.getElementById('userTableContainer').innerHTML = html;

                // Reset total count
                const countResponse = await fetch('admin_dashboard.php?action=get_filtered_count');
                if (!countResponse.ok) throw new Error('Network response was not ok');
                const countData = await countResponse.json();
                document.getElementById('totalUsersCount').textContent = countData.count;
            } catch (error) {
                console.error('Error:', error);
                alert('Error resetting search. Please try again.');
            }
        }

        // Add enter key support for search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchUsers();
            }
        });

        // Image modal functions
        function showImageModal(src, caption) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const captionText = document.getElementById('modalCaption');
            
            modal.style.display = "block";
            modalImg.src = src;
            captionText.innerHTML = caption;
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = "none";
        }
        
        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
