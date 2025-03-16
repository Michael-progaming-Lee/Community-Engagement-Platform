<?php
session_start();
include("php/config.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id'];
$notification_message = "";

// Handle delete selected completed deliveries
if (isset($_POST['delete_selected_deliveries']) && isset($_POST['selected_deliveries']) && is_array($_POST['selected_deliveries'])) {
    $selected_deliveries = array_map('intval', $_POST['selected_deliveries']);
    
    if (!empty($selected_deliveries)) {
        // Prepare placeholders for the IN clause
        $placeholders = str_repeat('?,', count($selected_deliveries) - 1) . '?';
        
        // Make sure the deliveries belong to the user before deleting
        $delete_query = "DELETE FROM delivery_status WHERE id IN ($placeholders) AND (buyer_id = ? OR seller_id = ?) AND status = 'delivered'";
        $delete_stmt = $con->prepare($delete_query);
        
        if ($delete_stmt) {
            // Create parameter types string (i for each delivery ID + i for user_id + i for user_id again)
            $types = str_repeat('i', count($selected_deliveries)) . 'ii';
            
            // Create parameter array with delivery IDs and user_id twice (for buyer and seller)
            $params = $selected_deliveries;
            $params[] = $user_id;
            $params[] = $user_id;
            
            // Bind parameters dynamically
            $delete_stmt->bind_param($types, ...$params);
            
            if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
                $notification_message = "Selected delivery records deleted successfully.";
            } else {
                $notification_message = "Error deleting delivery records or no records found.";
            }
        } else {
            $notification_message = "Error preparing delete statement: " . $con->error;
        }
    } else {
        $notification_message = "No deliveries selected for deletion.";
    }
    
    // Redirect to maintain clean URL and refresh page
    header("Location: notifications.php?tab=completed-deliveries&success=1");
    exit();
}

// Handle delete selected notifications
if (isset($_POST['delete_selected']) && isset($_POST['selected_notifications']) && is_array($_POST['selected_notifications'])) {
    $selected_notifications = array_map('intval', $_POST['selected_notifications']);
    
    if (!empty($selected_notifications)) {
        // Prepare placeholders for the IN clause
        $placeholders = str_repeat('?,', count($selected_notifications) - 1) . '?';
        
        // Make sure the notifications belong to the user before deleting
        $delete_query = "DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?";
        $delete_stmt = $con->prepare($delete_query);
        
        if ($delete_stmt) {
            // Create parameter types string (i for each notification ID + i for user_id)
            $types = str_repeat('i', count($selected_notifications)) . 'i';
            
            // Create parameter array with notification IDs and user_id
            $params = $selected_notifications;
            $params[] = $user_id;
            
            // Bind parameters dynamically
            $delete_stmt->bind_param($types, ...$params);
            
            if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
                $notification_message = "Selected notifications deleted successfully.";
            } else {
                $notification_message = "Error deleting notifications or no notifications found.";
            }
        } else {
            $notification_message = "Error preparing delete statement: " . $con->error;
        }
    } else {
        $notification_message = "No notifications selected for deletion.";
    }
    
    // Redirect to maintain clean URL and refresh page
    $tab = isset($_POST['current_tab']) ? $_POST['current_tab'] : 'notifications';
    header("Location: notifications.php?tab=$tab&success=1");
    exit();
}

// Handle delete notification (legacy code - keeping for compatibility)
if (isset($_GET['delete_notification']) && is_numeric($_GET['delete_notification'])) {
    $notification_id = $_GET['delete_notification'];
    
    // Make sure the notification belongs to the user before deleting
    $delete_query = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $delete_stmt = $con->prepare($delete_query);
    
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $notification_id, $user_id);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            $notification_message = "Notification deleted successfully.";
        } else {
            $notification_message = "Error deleting notification or notification not found.";
        }
    } else {
        $notification_message = "Error preparing delete statement: " . $con->error;
    }
    
    // Redirect to maintain clean URL and refresh page
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'notifications';
    header("Location: notifications.php?tab=$tab&success=1");
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_read_query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    $mark_all_read_stmt = $con->prepare($mark_all_read_query);
    $mark_all_read_stmt->bind_param("i", $user_id);
    
    if ($mark_all_read_stmt->execute()) {
        $notification_message = "All notifications marked as read.";
    } else {
        $notification_message = "Error marking all notifications as read: " . $mark_all_read_stmt->error;
    }
}

// Handle marking notifications as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_read_query = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
    $mark_read_stmt = $con->prepare($mark_read_query);
    $mark_read_stmt->bind_param("ii", $notification_id, $user_id);
    
    if ($mark_read_stmt->execute()) {
        $notification_message = "Notification marked as read.";
    } else {
        $notification_message = "Error marking notification as read: " . $mark_read_stmt->error;
    }
}

// Handle delivery status updates for sellers
if (isset($_GET['ship_product']) && is_numeric($_GET['ship_product'])) {
    $delivery_id = $_GET['ship_product'];
    
    // Update delivery status to shipped
    $ship_query = "UPDATE delivery_status SET sent_for_delivery = TRUE, sent_date = NOW(), status = 'shipped' 
                   WHERE id = ? AND seller_id = ? AND status = 'pending'";
    $ship_stmt = $con->prepare($ship_query);
    $ship_stmt->bind_param("ii", $delivery_id, $user_id);
    
    if ($ship_stmt->execute() && $ship_stmt->affected_rows > 0) {
        // Get buyer ID and product details for notification
        $buyer_query = "SELECT d.buyer_id, d.product_id, p.product_name 
                       FROM delivery_status d 
                       JOIN product p ON d.product_id = p.id 
                       WHERE d.id = ?";
        $buyer_stmt = $con->prepare($buyer_query);
        $buyer_stmt->bind_param("i", $delivery_id);
        $buyer_stmt->execute();
        $buyer_result = $buyer_stmt->get_result();
        
        if ($buyer_data = $buyer_result->fetch_assoc()) {
            $buyer_id = $buyer_data['buyer_id'];
            $product_name = $buyer_data['product_name'];
            
            // Create notification for buyer
            $notification_message = "Your product '{$product_name}' has been shipped. Please confirm when you receive it.";
            $notification_query = "INSERT INTO notifications (user_id, type, message, related_id) 
                                  VALUES (?, 'product_shipped', ?, ?)";
            $notification_stmt = $con->prepare($notification_query);
            $notification_stmt->bind_param("isi", $buyer_id, $notification_message, $delivery_id);
            $notification_stmt->execute();
            
            // Mark the seller's original sale notification as read since action has been taken
            $mark_read_query = "UPDATE notifications SET is_read = TRUE 
                              WHERE related_id = ? AND user_id = ? AND type = 'new_sale'";
            $mark_read_stmt = $con->prepare($mark_read_query);
            $mark_read_stmt->bind_param("ii", $delivery_id, $user_id);
            $mark_read_stmt->execute();
            
            $notification_message = "Product marked as shipped and buyer has been notified.";
            
            // Redirect to refresh the page and update notification count
            header("Location: notifications.php?tab=pending-shipments");
            exit();
        }
    } else {
        $notification_message = "Error updating delivery status: " . $ship_stmt->error;
    }
}

// Handle delivery confirmation by buyer
if (isset($_GET['confirm_delivery']) && is_numeric($_GET['confirm_delivery'])) {
    $delivery_id = $_GET['confirm_delivery'];
    
    // Get the transaction details
    $transaction_query = "SELECT * FROM delivery_status WHERE id = ? AND buyer_id = ? AND status = 'shipped'";
    $transaction_stmt = $con->prepare($transaction_query);
    
    if (!$transaction_stmt) {
        $notification_message = "Error preparing transaction query: " . $con->error;
    } else {
        $transaction_stmt->bind_param("ii", $delivery_id, $user_id);
        
        if (!$transaction_stmt->execute()) {
            $notification_message = "Error executing transaction query: " . $transaction_stmt->error;
        } else {
            $transaction_result = $transaction_stmt->get_result();
            
            if ($transaction_data = $transaction_result->fetch_assoc()) {
                // Start a transaction
                mysqli_begin_transaction($con);
                
                try {
                    $seller_id = $transaction_data['seller_id'];
                    $amount = $transaction_data['amount'];
                    $product_id = $transaction_data['product_id'];
                    
                    // Update delivery status
                    $update_query = "UPDATE delivery_status SET 
                                    received_by_buyer = TRUE, 
                                    received_date = NOW(), 
                                    payment_processed = TRUE, 
                                    status = 'delivered' 
                                    WHERE id = ?";
                    $update_stmt = $con->prepare($update_query);
                    
                    if (!$update_stmt) {
                        throw new Exception("Error preparing update query: " . $con->error);
                    }
                    
                    $update_stmt->bind_param("i", $delivery_id);
                    
                    if (!$update_stmt->execute()) {
                        throw new Exception("Error updating delivery status: " . $update_stmt->error);
                    }
                    
                    // Add the amount to the seller's account
                    $seller_update = "UPDATE users SET AccountBalance = COALESCE(AccountBalance, 0) + ? WHERE Id = ?";
                    $seller_stmt = $con->prepare($seller_update);
                    
                    if (!$seller_stmt) {
                        throw new Exception("Error preparing seller balance update: " . $con->error);
                    }
                    
                    $seller_stmt->bind_param("di", $amount, $seller_id);
                    
                    if (!$seller_stmt->execute()) {
                        throw new Exception("Error updating seller balance: " . $seller_stmt->error);
                    }
                    
                    // Get product name for notification
                    $product_query = "SELECT product_name FROM product WHERE id = ?";
                    $product_stmt = $con->prepare($product_query);
                    
                    if (!$product_stmt) {
                        throw new Exception("Error preparing product query: " . $con->error);
                    }
                    
                    $product_stmt->bind_param("i", $product_id);
                    
                    if (!$product_stmt->execute()) {
                        throw new Exception("Error getting product name: " . $product_stmt->error);
                    }
                    
                    $product_result = $product_stmt->get_result();
                    
                    if (!$product_result) {
                        throw new Exception("Error getting product result");
                    }
                    
                    $product_data = $product_result->fetch_assoc();
                    
                    if (!$product_data) {
                        throw new Exception("Could not find product data");
                    }
                    
                    $product_name = $product_data['product_name'];
                    
                    // Update purchase history status
                    $purchase_id = $transaction_data['purchase_id'];
                    $history_update = "UPDATE purchase_history SET status = 'completed' WHERE id = ?";
                    $history_stmt = $con->prepare($history_update);
                    
                    if (!$history_stmt) {
                        throw new Exception("Error preparing history update: " . $con->error);
                    }
                    
                    $history_stmt->bind_param("i", $purchase_id);
                    
                    if (!$history_stmt->execute()) {
                        throw new Exception("Error updating purchase history: " . $history_stmt->error);
                    }
                    
                    // Create notification for seller
                    $seller_notification = "Product '{$product_name}' delivery confirmed. $" . number_format($amount, 2) . " has been added to your account balance.";
                    $seller_notify_query = "INSERT INTO notifications (user_id, type, message, related_id) 
                                          VALUES (?, 'payment_received', ?, ?)";
                    $seller_notify_stmt = $con->prepare($seller_notify_query);
                    
                    if (!$seller_notify_stmt) {
                        throw new Exception("Error preparing seller notification: " . $con->error);
                    }
                    
                    $seller_notify_stmt->bind_param("isi", $seller_id, $seller_notification, $delivery_id);
                    
                    if (!$seller_notify_stmt->execute()) {
                        throw new Exception("Error creating seller notification: " . $seller_notify_stmt->error);
                    }
                    
                    // Commit the transaction
                    mysqli_commit($con);
                    
                    // Mark the related notification as read since the action has been taken
                    $mark_notification_read_query = "UPDATE notifications SET is_read = TRUE 
                                                   WHERE related_id = ? AND user_id = ? AND type = 'product_shipped'";
                    $mark_notification_stmt = $con->prepare($mark_notification_read_query);
                    
                    if ($mark_notification_stmt) {
                        $mark_notification_stmt->bind_param("ii", $delivery_id, $user_id);
                        $mark_notification_stmt->execute();
                    }
                    
                    $notification_message = "Delivery confirmed successfully. The seller has been paid and notified.";
                    
                    // Determine which tab to return to after confirmation
                    $redirect_tab = isset($_GET['tab']) ? $_GET['tab'] : 'completed-deliveries';
                    
                    // Redirect to refresh the page and update notification count
                    header("Location: notifications.php?tab=" . $redirect_tab . "&success=1");
                    exit();
                    
                } catch (Exception $e) {
                    // Rollback the transaction on error
                    mysqli_rollback($con);
                    $notification_message = "Error during confirmation: " . $e->getMessage();
                }
            } else {
                $notification_message = "Could not find a valid delivery to confirm. Please ensure this is a shipped product that you purchased.";
            }
        }
    }
}

// Get user's notifications, oldest first
$notifications_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$notifications_stmt = $con->prepare($notifications_query);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

// Count unread notifications
$unread_query = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$unread_stmt = $con->prepare($unread_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$unread_count = $unread_data['unread'];

// Get pending deliveries that need to be shipped (for sellers)
$pending_shipments_query = "SELECT ds.*, p.product_name, u.Username as buyer_name, ph.price 
                           FROM delivery_status ds 
                           JOIN product p ON ds.product_id = p.id 
                           JOIN users u ON ds.buyer_id = u.Id 
                           JOIN purchase_history ph ON ds.purchase_id = ph.id 
                           WHERE ds.seller_id = ? AND ds.status = 'pending' 
                           ORDER BY ds.created_at DESC";
$pending_shipments_stmt = $con->prepare($pending_shipments_query);
$pending_shipments_stmt->bind_param("i", $user_id);
$pending_shipments_stmt->execute();
$pending_shipments_result = $pending_shipments_stmt->get_result();

// Get shipped items awaiting confirmation (for buyers)
$pending_confirmations_query = "SELECT ds.*, p.product_name, u.Username as seller_name, ph.price,
                             n.id as notification_id, n.message as notification_message, n.created_at as notification_date  
                             FROM delivery_status ds 
                             JOIN product p ON ds.product_id = p.id 
                             JOIN users u ON ds.seller_id = u.Id 
                             JOIN purchase_history ph ON ds.purchase_id = ph.id 
                             LEFT JOIN notifications n ON ds.id = n.related_id AND n.user_id = ? AND n.type = 'product_shipped'
                             WHERE ds.buyer_id = ? AND ds.status = 'shipped' 
                             ORDER BY ds.sent_date DESC";
$pending_confirmations_stmt = $con->prepare($pending_confirmations_query);
$pending_confirmations_stmt->bind_param("ii", $user_id, $user_id);
$pending_confirmations_stmt->execute();
$pending_confirmations_result = $pending_confirmations_stmt->get_result();

// Get completed deliveries (both buyer and seller)
$completed_deliveries_query = "SELECT ds.*, p.product_name, 
                             u_buyer.Username as buyer_name, 
                             u_seller.Username as seller_name, 
                             ph.price,
                             ds.buyer_id = ? as is_buyer,
                             ds.seller_id = ? as is_seller
                             FROM delivery_status ds 
                             JOIN product p ON ds.product_id = p.id 
                             JOIN users u_buyer ON ds.buyer_id = u_buyer.Id 
                             JOIN users u_seller ON ds.seller_id = u_seller.Id 
                             JOIN purchase_history ph ON ds.purchase_id = ph.id 
                             WHERE (ds.buyer_id = ? OR ds.seller_id = ?) AND ds.status = 'delivered' 
                             ORDER BY ds.received_date DESC
                             LIMIT 15";
$completed_deliveries_stmt = $con->prepare($completed_deliveries_query);
$completed_deliveries_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$completed_deliveries_stmt->execute();
$completed_deliveries_result = $completed_deliveries_stmt->get_result();

// Function to format timestamps to a readable format
function formatTimestamp($timestamp) {
    if (!$timestamp) return "Unknown time";
    
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    // Format with specific date and time for all notifications
    // This ensures each notification shows its exact creation time
    return $date->format('M j, Y') . ' at ' . $date->format('g:i A');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-image: url('Background Images/Home_Background.png');
            background-size: cover;
            background-position: top center;
            font-family: Arial, sans-serif;
        }
        
        .page-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .notifications-container {
            margin-bottom: 30px;
        }
        
        .notification-item {
            background-color: white;
            border: 1px solid #e6e6e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .notification-item:hover {
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        
        .notification-item.unread {
            border-left: 5px solid #3498db;
        }
        
        .notification-item.read {
            opacity: 0.85;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 5px;
            font-style: normal;
            display: block;
        }
        
        /* Make the notification timestamp stand out slightly more */
        .notification-item .notification-time {
            font-weight: 500;
        }
        
        /* Style timestamps consistently across both notifications and deliveries */
        .delivery-date {
            color: #7f8c8d;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .notification-message {
            display: flex;
            align-items: flex-start;
            line-height: 1.5;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .notification-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            justify-content: space-between;
        }
        
        .notification-checkbox {
            display: flex;
            align-items: center;
        }
        
        .notification-actions input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .notifications-list {
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .tab-container {
            margin-bottom: 20px;
        }
        
        .tabs {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            border-bottom: 1px solid #eee;
        }
        
        .tab-item {
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab-item.active {
            border-bottom: 3px solid #3498db;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .delivery-card {
            background-color: white;
            border: 1px solid #e6e6e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
        }
        
        .delivery-card:hover {
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        
        .delivery-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .delivery-info {
            margin: 10px 0;
            line-height: 1.5;
        }
        
        .delivery-price {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .delivery-date {
            color: #7f8c8d;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .notification-checkbox {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-top: 10px;
        }
        
        .delivery-card .notification-checkbox {
            position: static;
            top: auto;
            right: auto;
        }
        
        .status-pending {
            background-color: #fef9e7;
            color: #f39c12;
            border: 1px solid #f39c12;
        }
        
        .status-shipped {
            background-color: #e8f6fd;
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .status-delivered {
            background-color: #eafaf1;
            color: #27ae60;
            border: 1px solid #27ae60;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .notification-message-box {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .notification-icon {
            margin-right: 10px;
        }
        
        .notification-link {
            text-decoration: none;
            color: #333;
            display: block;
        }
        
        .notification-link:hover .notification-message {
            background-color: #f8f8f8;
        }
        
        .notification-link.no-link {
            cursor: default;
        }
        
        .notification-item.read .notification-message {
            opacity: 0.8;
        }
        
        .notifications-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            padding: 10px 15px;
            border-radius: 8px;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        
        .select-all-container label {
            cursor: pointer;
        }
        
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .delivery-list {
            margin-top: 15px;
        }
        
        .delivery-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .delivery-card .notification-checkbox {
            position: static;
            top: auto;
            right: auto;
        }
        
        #select-all-deliveries {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>

    <div class="page-container">
        <a href="home.php" class="back-link">
            <i class="fas fa-chevron-left"></i>
            Back to Home
        </a>

        <div class="notifications-header">
            <h1>Notifications & Delivery Management</h1>
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-secondary">Mark All as Read</a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($notification_message)): ?>
            <div class="alert <?php echo strpos($notification_message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo $notification_message; ?>
            </div>
        <?php endif; ?>

        <div class="tab-container">
            <ul class="tabs">
                <li class="tab-item <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'notifications') ? 'active' : ''; ?>" data-tab="notifications">Notifications <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?></li>
                <li class="tab-item <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'pending-shipments') ? 'active' : ''; ?>" data-tab="pending-shipments">Pending Shipments</li>
                <li class="tab-item <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'pending-confirmations') ? 'active' : ''; ?>" data-tab="pending-confirmations">Awaiting Confirmation</li>
                <li class="tab-item <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'completed-deliveries') ? 'active' : ''; ?>" data-tab="completed-deliveries">Completed Deliveries</li>
            </ul>
            
            <!-- Notifications Tab -->
            <div class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'notifications') ? 'active' : ''; ?>" id="notifications-tab">
                <div class="notifications-container">
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <form action="notifications.php" method="post">
                            <input type="hidden" name="current_tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'notifications'; ?>">
                            <div class="notifications-header-actions">
                                <button type="submit" name="delete_selected" class="btn btn-danger">Delete Selected</button>
                                <div class="select-all-container">
                                    <input type="checkbox" id="select-all">
                                    <label for="select-all">Select All</label>
                                </div>
                            </div>
                            <div class="notifications-list">
                                <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                        <div class="notification-time"><?php echo formatTimestamp($notification['created_at']); ?></div>
                                        <?php
                                        // Determine the link URL based on notification type and related_id
                                        $link_url = '#';
                                        $has_link = false;
                                        
                                        if (!empty($notification['related_id'])) {
                                            $has_link = true;
                                            
                                            // Set the appropriate link based on notification type
                                            switch ($notification['type']) {
                                                case 'new_sale':
                                                case 'payment_received':
                                                    $link_url = "purchase_history.php?order_id=" . $notification['related_id'];
                                                    break;
                                                case 'product_shipped':
                                                    $link_url = "notifications.php?tab=pending-confirmations#delivery-" . $notification['related_id'];
                                                    break;
                                                case 'rental_returned':
                                                case 'rental_returned_late':
                                                    $link_url = "rental.php?view_return=" . $notification['related_id'];
                                                    break;
                                                default:
                                                    // For other types, just link to the tab that might contain it
                                                    $link_url = "notifications.php?tab=completed-deliveries";
                                                    break;
                                            }
                                        }
                                        ?>
                                        
                                        <a href="<?php echo $has_link ? $link_url : 'javascript:void(0)'; ?>" class="notification-link <?php echo !$has_link ? 'no-link' : ''; ?>">
                                            <div class="notification-message">
                                                <?php if ($notification['type'] == 'rental_returned' || $notification['type'] == 'rental_returned_late'): ?>
                                                <span class="notification-icon"><i class="fas fa-undo-alt" style="color: #3498db;"></i></span>
                                                <?php elseif ($notification['type'] == 'payment_received'): ?>
                                                <span class="notification-icon"><i class="fas fa-money-bill-wave" style="color: #27ae60;"></i></span>
                                                <?php elseif ($notification['type'] == 'product_shipped'): ?>
                                                <span class="notification-icon"><i class="fas fa-shipping-fast" style="color: #f39c12;"></i></span>
                                                <?php elseif ($notification['type'] == 'new_sale'): ?>
                                                <span class="notification-icon"><i class="fas fa-shopping-cart" style="color: #e74c3c;"></i></span>
                                                <?php else: ?>
                                                <span class="notification-icon"><i class="fas fa-bell"></i></span>
                                                <?php endif; ?>
                                                
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </div>
                                        </a>
                                        
                                        <div class="notification-actions">
                                            <div class="action-buttons">
                                                <?php if (!$notification['is_read']): ?>
                                                    <a href="?mark_read=<?php echo $notification['id']; ?>&tab=<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'notifications'; ?>" class="btn btn-secondary">Mark as Read</a>
                                                <?php endif; ?>
                                                
                                                <?php if ($notification['type'] == 'product_shipped' && isset($notification['related_id'])): ?>
                                                    <a href="?confirm_delivery=<?php echo $notification['related_id']; ?>&tab=<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'notifications'; ?>" class="btn btn-success">Confirm Delivery</a>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="notification-checkbox">
                                                <input type="checkbox" name="selected_notifications[]" value="<?php echo $notification['id']; ?>" id="notification-<?php echo $notification['id']; ?>">
                                                <label for="notification-<?php echo $notification['id']; ?>" class="sr-only">Select notification</label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-bell-slash"></i>
                            <p>You have no notifications yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Shipments Tab -->
            <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'pending-shipments') ? 'active' : ''; ?>" id="pending-shipments-tab">
                <div class="delivery-container">
                    <?php if ($pending_shipments_result->num_rows > 0): ?>
                        <?php while ($shipment = $pending_shipments_result->fetch_assoc()): ?>
                            <div class="delivery-card" id="shipment-<?php echo $shipment['id']; ?>">
                                <span class="delivery-status status-pending">Pending Shipment</span>
                                <h4><?php echo htmlspecialchars($shipment['product_name']); ?></h4>
                                <div class="delivery-info">
                                    <p>Buyer: <?php echo htmlspecialchars($shipment['buyer_name']); ?></p>
                                    <p class="delivery-price">$<?php echo number_format($shipment['amount'], 2); ?></p>
                                    <p class="delivery-date">Purchased: <?php echo formatTimestamp($shipment['created_at']); ?></p>
                                </div>
                                <a href="?ship_product=<?php echo $shipment['id']; ?>" class="btn btn-primary">Mark as Shipped</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box"></i>
                            <p>You have no pending shipments.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Confirmations Tab -->
            <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'pending-confirmations') ? 'active' : ''; ?>" id="pending-confirmations-tab">
                <div class="delivery-container">
                    <?php if ($pending_confirmations_result->num_rows > 0): ?>
                        <?php while ($confirmation = $pending_confirmations_result->fetch_assoc()): ?>
                            <div class="delivery-card" id="delivery-<?php echo $confirmation['id']; ?>">
                                <span class="delivery-status status-shipped">Shipped</span>
                                <h4><?php echo htmlspecialchars($confirmation['product_name']); ?></h4>
                                
                                <?php if (!empty($confirmation['notification_message'])): ?>
                                <div class="notification-message-box">
                                    <i class="fas fa-info-circle"></i>
                                    <?php echo htmlspecialchars($confirmation['notification_message']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="delivery-info">
                                    <p>Seller: <?php echo htmlspecialchars($confirmation['seller_name']); ?></p>
                                    <p class="delivery-price">$<?php echo number_format($confirmation['amount'], 2); ?></p>
                                    <p class="delivery-date">Shipped: <?php echo formatTimestamp($confirmation['sent_date']); ?></p>
                                </div>
                                <a href="?confirm_delivery=<?php echo $confirmation['id']; ?>&tab=<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'notifications'; ?>" class="btn btn-success">Confirm Delivery</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-truck"></i>
                            <p>You have no pending deliveries to confirm.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Completed Deliveries Tab -->
            <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'completed-deliveries') ? 'active' : ''; ?>" id="completed-deliveries-tab">
                <div class="delivery-container">
                    <?php if ($completed_deliveries_result->num_rows > 0): ?>
                        <form action="notifications.php" method="post">
                            <input type="hidden" name="current_tab" value="completed-deliveries">
                            <div class="notifications-header-actions">
                                <button type="submit" name="delete_selected_deliveries" class="btn btn-danger">Delete Selected</button>
                                <div class="select-all-container">
                                    <input type="checkbox" id="select-all-deliveries">
                                    <label for="select-all-deliveries">Select All</label>
                                </div>
                            </div>
                            <div class="delivery-list">
                                <?php while ($delivery = $completed_deliveries_result->fetch_assoc()): ?>
                                    <div class="delivery-card" id="delivery-<?php echo $delivery['id']; ?>">
                                        <div class="delivery-header">
                                            <span class="delivery-status status-delivered">Delivered</span>
                                            <div class="notification-checkbox">
                                                <input type="checkbox" name="selected_deliveries[]" value="<?php echo $delivery['id']; ?>" id="delivery-check-<?php echo $delivery['id']; ?>">
                                                <label for="delivery-check-<?php echo $delivery['id']; ?>" class="sr-only">Select delivery</label>
                                            </div>
                                        </div>
                                        <h4><?php echo htmlspecialchars($delivery['product_name']); ?></h4>
                                        <div class="delivery-info">
                                            <?php if ($delivery['is_buyer']): ?>
                                                <p>Purchased from: <?php echo htmlspecialchars($delivery['seller_name']); ?></p>
                                            <?php else: ?>
                                                <p>Sold to: <?php echo htmlspecialchars($delivery['buyer_name']); ?></p>
                                            <?php endif; ?>
                                            <p class="delivery-price">$<?php echo number_format($delivery['amount'], 2); ?></p>
                                            <p class="delivery-date">Delivered: <?php echo formatTimestamp($delivery['received_date']); ?></p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>You have no completed deliveries.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabItems = document.querySelectorAll('.tab-item');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabItems.forEach(tab => tab.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to current tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                    
                    // Update URL without reloading page
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);
                });
            });
            
            // Select All checkbox functionality
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
                
                // Update "Select All" state when individual checkboxes change
                const notificationCheckboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
                notificationCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(notificationCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    });
                });
            }
            
            const selectAllDeliveriesCheckbox = document.getElementById('select-all-deliveries');
            if (selectAllDeliveriesCheckbox) {
                selectAllDeliveriesCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selected_deliveries[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
                
                // Update "Select All" state when individual checkboxes change
                const deliveryCheckboxes = document.querySelectorAll('input[name="selected_deliveries[]"]');
                deliveryCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(deliveryCheckboxes).every(cb => cb.checked);
                        selectAllDeliveriesCheckbox.checked = allChecked;
                    });
                });
            }
        });
    </script>
</body>
</html>
