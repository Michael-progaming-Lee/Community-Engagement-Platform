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
            $rental_query = "SELECT rp.*, p.product_name, 
                          rp.seller_id as product_seller_id, 
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
                
                // Calculate late fee - 5% of rental price per day overdue
                $end_date = new DateTime($rental_data['rental_end_date']);
                $today = new DateTime($current_date);
                $days_overdue = $today->diff($end_date)->days;
                $late_fee = $days_overdue * ($rental_data['rental_price'] * 0.05); // 5% of rental price per day
                
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
            
            // Get the seller ID for balance update
            $seller_id = isset($rental_data['product_seller_id']) ? $rental_data['product_seller_id'] : $rental_data['seller_id'];
            
            // If late fees apply, deduct from user's balance and add to seller's balance
            if ($late_fee > 0) {
                // Deduct from buyer's balance
                $buyer_fee_query = "UPDATE users SET AccountBalance = COALESCE(AccountBalance, 0) - ? WHERE Id = ?";
                $buyer_fee_stmt = mysqli_prepare($con, $buyer_fee_query);
                mysqli_stmt_bind_param($buyer_fee_stmt, "di", $late_fee, $user_id);
                
                if (!mysqli_stmt_execute($buyer_fee_stmt)) {
                    throw new Exception("Error deducting late fees from buyer: " . mysqli_error($con));
                }
                
                // Add to seller's balance
                $seller_fee_query = "UPDATE users SET AccountBalance = COALESCE(AccountBalance, 0) + ? WHERE Id = ?";
                $seller_fee_stmt = mysqli_prepare($con, $seller_fee_query);
                mysqli_stmt_bind_param($seller_fee_stmt, "di", $late_fee, $seller_id);
                
                if (!mysqli_stmt_execute($seller_fee_stmt)) {
                    throw new Exception("Error adding late fees to seller: " . mysqli_error($con));
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
            if ($seller_id) {
                // Create appropriate notification message based on whether there's a late fee
                if ($late_fee > 0) {
                    $notification_message = "Product '{$rental_data['product_name']}' has been returned by the renter. A late fee of $" . number_format($late_fee, 2) . " was charged and added to your account balance.";
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

// Handle delete rental history request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_rental_history' && isset($_POST['rental_id'])) {
    $rental_id = intval($_POST['rental_id']);
    
    // Verify the rental belongs to the current user and is in 'returned' status
    $verify_query = "SELECT * FROM rental_products WHERE id = ? AND buyer_id = ? AND status = 'returned'";
    $verify_stmt = mysqli_prepare($con, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $rental_id, $user_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) > 0) {
        // Delete the rental record
        $delete_query = "DELETE FROM rental_products WHERE id = ? AND buyer_id = ? AND status = 'returned'";
        $delete_stmt = mysqli_prepare($con, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "ii", $rental_id, $user_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $_SESSION['success_message'] = "Rental history record deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete rental history record: " . mysqli_error($con);
        }
    } else {
        $_SESSION['error_message'] = "Invalid rental record or you don't have permission to delete it.";
    }
    
    header("Location: rental.php");
    exit();
}

// Handle rental report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_overdue') {
    // Validate required fields
    if (!isset($_POST['rental_id'])) {
        $_SESSION['error_message'] = "Missing rental ID for report.";
        header("Location: rental.php");
        exit;
    }
    
    $rental_id = $_POST['rental_id'];
    $report_reason = "Rental overdue by more than 10 days"; // Default reason
    $user_id = $_SESSION['id']; // Current user (seller)
    
    try {
        // Get rental information
        $rental_query = "SELECT rp.*, p.product_name 
                        FROM rental_products rp
                        JOIN product p ON rp.product_id = p.id
                        WHERE rp.id = ? AND rp.seller_id = ? AND rp.status = 'rented'";
        $rental_stmt = mysqli_prepare($con, $rental_query);
        mysqli_stmt_bind_param($rental_stmt, "ii", $rental_id, $user_id);
        mysqli_stmt_execute($rental_stmt);
        $rental_result = mysqli_stmt_get_result($rental_stmt);
        
        if (mysqli_num_rows($rental_result) === 0) {
            throw new Exception("Rental not found or you don't have permission to report it.");
        }
        
        $rental_data = mysqli_fetch_assoc($rental_result);
        
        // Calculate days overdue
        $end_date = new DateTime($rental_data['rental_end_date']);
        $current_date = new DateTime();
        $days_overdue = $current_date->diff($end_date)->days;
        
        // Check if rental is overdue by more than 10 days
        if ($days_overdue < 10 || $rental_data['rental_end_date'] > date('Y-m-d')) {
            throw new Exception("This rental cannot be reported yet. It must be overdue by at least 10 days.");
        }
        
        // Check if this rental has already been reported
        $check_query = "SELECT id FROM rental_reports WHERE product_id = ? AND buyer_id = ? AND seller_id = ?";
        $check_stmt = mysqli_prepare($con, $check_query);
        mysqli_stmt_bind_param($check_stmt, "iii", $rental_data['product_id'], $rental_data['buyer_id'], $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception("This rental has already been reported.");
        }
        
        // Table is assumed to already exist
        
        // Insert report into rental_reports table
        $insert_query = "INSERT INTO rental_reports (product_id, product_name, buyer_id, seller_id, days_overdue) 
                        VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($con, $insert_query);
        
        if (!$insert_stmt) {
            $_SESSION['error_message'] = "Failed to prepare statement: " . mysqli_error($con);
            header("Location: rental.php");
            exit;
        }
        
        // Debug information
        $debug_info = "Product ID: {$rental_data['product_id']}, Product Name: {$rental_data['product_name']}, 
                      Buyer ID: {$rental_data['buyer_id']}, Seller ID: $user_id, 
                      Days Overdue: $days_overdue";
        
        // Try to bind parameters
        if (!mysqli_stmt_bind_param($insert_stmt, "isiii", 
                               $rental_data['product_id'],
                               $rental_data['product_name'],
                               $rental_data['buyer_id'], 
                               $user_id, 
                               $days_overdue)) {
            $_SESSION['error_message'] = "Failed to bind parameters: " . mysqli_stmt_error($insert_stmt) . " - " . $debug_info;
            header("Location: rental.php");
            exit;
        }
        
        // Try to execute the statement
        if (!mysqli_stmt_execute($insert_stmt)) {
            $_SESSION['error_message'] = "Error submitting report: " . mysqli_stmt_error($insert_stmt) . " - " . $debug_info;
            header("Location: rental.php");
            exit;
        }
        
        // Success message with details
        $_SESSION['success_message'] = "Report successfully submitted for rental #$rental_id. Report ID: " . mysqli_insert_id($con);
        
        // Send notification to admin
        $admin_query = "SELECT Id FROM users WHERE UserType = 'admin' LIMIT 1";
        $admin_result = mysqli_query($con, $admin_query);
        
        if ($admin_result && mysqli_num_rows($admin_result) > 0) {
            $admin_id = mysqli_fetch_assoc($admin_result)['Id'];
            $notification_message = "Seller has reported an overdue rental for product '{$rental_data['product_name']}' that is {$days_overdue} days overdue.";
            
            $notification_query = "INSERT INTO notifications (user_id, type, message, related_id, is_read, created_at) 
                                  VALUES (?, 'rental_report', ?, ?, FALSE, NOW())";
            $notification_stmt = mysqli_prepare($con, $notification_query);
            mysqli_stmt_bind_param($notification_stmt, "isi", $admin_id, $notification_message, $rental_id);
            mysqli_stmt_execute($notification_stmt);
        }
        
        $_SESSION['success_message'] = "Report submitted successfully. An administrator will review your report.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header("Location: rental.php");
    exit;
}

// Get user's current balance with COALESCE to handle NULL values
$balance_query = "SELECT *, COALESCE(AccountBalance, 0.00) as AccountBalance FROM users WHERE Id = ?";
$balance_stmt = mysqli_prepare($con, $balance_query);
mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);
$user_data = mysqli_fetch_assoc($balance_result);
$current_balance = $user_data['AccountBalance'];

$low_balance_threshold = 5.00; // Consider low balance if less than $5

// Initialize arrays to hold rentals
$active_rentals = [];
$overdue_rentals = [];
$returned_rentals = []; // New array for returned rentals
$error_message = "";

// Check if the rental_products table exists
$table_check_query = "SHOW TABLES LIKE 'rental_products'";
$table_check_result = mysqli_query($con, $table_check_query);
$table_exists = mysqli_num_rows($table_check_result) > 0;

if ($table_exists) {
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
    
    // Query for returned rentals for the current user
    $returned_query = "SELECT rp.*, p.product_name, p.product_img, u.Username as seller_name
                      FROM rental_products rp
                      JOIN product p ON rp.product_id = p.id
                      JOIN users u ON rp.seller_id = u.Id
                      WHERE rp.buyer_id = ? AND rp.status = 'returned'
                      ORDER BY rp.return_date DESC
                      LIMIT 10"; // Limit to most recent 10 returns
    
    $returned_stmt = mysqli_prepare($con, $returned_query);
    if ($returned_stmt === false) {
        $error_message .= " Error preparing returned query: " . mysqli_error($con);
    } else {
        mysqli_stmt_bind_param($returned_stmt, "i", $user_id);
        $returned_execute_success = mysqli_stmt_execute($returned_stmt);
        
        if (!$returned_execute_success) {
            $error_message .= " Error executing returned query: " . mysqli_stmt_error($returned_stmt);
        } else {
            $returned_result = mysqli_stmt_get_result($returned_stmt);
            
            if ($returned_result === false) {
                $error_message .= " Error getting returned result: " . mysqli_error($con);
            } else {
                // Process returned rentals
                while ($rental = mysqli_fetch_assoc($returned_result)) {
                    // Calculate rental duration
                    $start_date = new DateTime($rental['rental_start_date']);
                    $return_date = new DateTime($rental['return_date']);
                    $rental_duration = $start_date->diff($return_date)->days;
                    
                    $rental['actual_duration'] = $rental_duration;
                    $returned_rentals[] = $rental;
                }
            }
        }
    }
    
    // Query for products rented from the current user (seller view)
    $seller_rentals = [];
    $seller_returned = [];
    
    $seller_query = "SELECT rp.*, p.product_name, p.product_img, u.Username as buyer_name, u.Email as buyer_email
                    FROM rental_products rp
                    JOIN product p ON rp.product_id = p.id
                    JOIN users u ON rp.buyer_id = u.Id
                    WHERE rp.seller_id = ?
                    ORDER BY rp.status, rp.rental_end_date ASC";
    
    $seller_stmt = mysqli_prepare($con, $seller_query);
    if ($seller_stmt === false) {
        $error_message .= " Error preparing seller query: " . mysqli_error($con);
    } else {
        mysqli_stmt_bind_param($seller_stmt, "i", $user_id);
        $seller_execute_success = mysqli_stmt_execute($seller_stmt);
        
        if (!$seller_execute_success) {
            $error_message .= " Error executing seller query: " . mysqli_stmt_error($seller_stmt);
        } else {
            $seller_result = mysqli_stmt_get_result($seller_stmt);
            
            if ($seller_result === false) {
                $error_message .= " Error getting seller result: " . mysqli_error($con);
            } else {
                // Process seller rentals
                while ($rental = mysqli_fetch_assoc($seller_result)) {
                    // Calculate days remaining for active rentals
                    if ($rental['status'] === 'rented') {
                        $end_date = new DateTime($rental['rental_end_date']);
                        $current = new DateTime();
                        $interval = $current->diff($end_date);
                        $days_remaining = $interval->invert ? -$interval->days : $interval->days;
                        
                        $rental['days_remaining'] = $days_remaining;
                        $rental['is_overdue'] = $days_remaining < 0;
                        
                        $seller_rentals[] = $rental;
                    } else if ($rental['status'] === 'returned') {
                        // For returned items, calculate how many days it was rented
                        $start_date = new DateTime($rental['rental_start_date']);
                        $return_date = new DateTime($rental['return_date']);
                        $rental_duration = $start_date->diff($return_date)->days;
                        
                        $rental['actual_duration'] = $rental_duration;
                        $seller_returned[] = $rental;
                    }
                }
            }
        }
    }
}

// Now let's add code to check for overdue rentals and send notifications to buyers
// This will run after the rental display queries but before the HTML output

// Check for overdue rentals that haven't been notified yet
$overdue_check_query = "SELECT rp.*, p.product_name, u.Username as seller_name, u.Email as seller_email
                       FROM rental_products rp
                       JOIN product p ON rp.product_id = p.id
                       JOIN users u ON rp.seller_id = u.Id
                       WHERE rp.buyer_id = ? 
                       AND rp.status = 'rented' 
                       AND rp.rental_end_date < CURDATE()
                       AND (rp.overdue_notified = 0 OR rp.overdue_notified IS NULL)";

$overdue_check_stmt = mysqli_prepare($con, $overdue_check_query);
if ($overdue_check_stmt) {
    mysqli_stmt_bind_param($overdue_check_stmt, "i", $user_id);
    mysqli_stmt_execute($overdue_check_stmt);
    $overdue_check_result = mysqli_stmt_get_result($overdue_check_stmt);
    
    // Process each overdue rental
    while ($overdue_rental = mysqli_fetch_assoc($overdue_check_result)) {
        // Calculate days overdue and late fee
        $end_date = new DateTime($overdue_rental['rental_end_date']);
        $current = new DateTime();
        $days_overdue = $current->diff($end_date)->days;
        $late_fee_rate = $overdue_rental['rental_price'] * 0.05; // 5% of rental price
        $estimated_late_fee = $days_overdue * $late_fee_rate;
        
        // Cap late fee at the original rental price
        if ($estimated_late_fee > $overdue_rental['rental_price']) {
            $estimated_late_fee = $overdue_rental['rental_price'];
        }
        
        // Create notification message for the buyer
        $notification_message = "Your rental of '{$overdue_rental['product_name']}' is overdue by {$days_overdue} days. 
                                A late fee of 5% of the rental price per day (approximately $" . number_format($estimated_late_fee, 2) . " so far) 
                                will be charged when you return the item. Please return it as soon as possible.";
        
        // Insert notification for the buyer
        $buyer_notification_query = "INSERT INTO notifications (user_id, type, message, related_id, is_read, created_at) 
                                    VALUES (?, 'rental_overdue', ?, ?, FALSE, NOW())";
        $buyer_notification_stmt = mysqli_prepare($con, $buyer_notification_query);
        mysqli_stmt_bind_param($buyer_notification_stmt, "isi", $user_id, $notification_message, $overdue_rental['id']);
        mysqli_stmt_execute($buyer_notification_stmt);
        
        // Mark this rental as notified to avoid duplicate notifications
        $mark_notified_query = "UPDATE rental_products SET overdue_notified = 1 WHERE id = ?";
        $mark_notified_stmt = mysqli_prepare($con, $mark_notified_query);
        mysqli_stmt_bind_param($mark_notified_stmt, "i", $overdue_rental['id']);
        mysqli_stmt_execute($mark_notified_stmt);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <title>My Rentals</title>
    <style>
        body {
            background-image: url('Background Images/Home_Background.png');
            background-size: cover;
            background-position: top center;
            background-attachment: fixed;
            font-family: 'Arial', sans-serif;
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
        
        /* Tab styles */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background-color: #fff;
            border-bottom: 2px solid #3498db;
            color: #3498db;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .seller-section {
            margin-bottom: 30px;
        }
        
        .contact-info {
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }
        
        .rental-status {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            display: inline-block;
            margin-left: 10px;
        }
        
        .status-rented {
            background-color: #3498db;
            color: white;
        }
        
        .status-returned {
            background-color: #2ecc71;
            color: white;
        }
        
        .status-overdue {
            background-color: #e74c3c;
            color: white;
        }
        
        .rental-card .delete-button {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        
        .rental-card .delete-button:hover {
            background-color: #c0392b;
        }
        
        .rental-card .delete-button i {
            margin-right: 5px;
        }
        
        .delete-form {
            margin-top: 10px;
        }
        
        .report-button {
            padding: 8px 16px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            display: inline-block;
        }
        
        .report-button:hover {
            background: #c0392b;
        }
        
        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-container {
            background-color: white;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .modal-body {
            padding: 15px;
        }
        
        .modal-footer {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: inherit;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>

    <div class="page-container">
        <div class="rentals-container">
            <h1 class="text-center mb-4">Rental Management</h1>
            
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
            
            <!-- Tab navigation -->
            <div class="tabs">
                <div class="tab active" data-tab="my-rentals">My Rentals</div>
                <div class="tab" data-tab="rentals-from-me">Rentals From Me</div>
            </div>
            
            <!-- My Rentals Tab Content -->
            <div class="tab-content active" id="my-rentals">
                <?php if (empty($active_rentals) && empty($overdue_rentals) && empty($returned_rentals)): ?>
                    <div class="empty-rentals">
                        <i class="fas fa-calendar-times"></i>
                        <h3>You don't have any rental history</h3>
                        <p>Browse available products to rent something!</p>
                        <a href="index.php" class="browse-button">Browse Products</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($overdue_rentals)): ?>
                        <div class="overdue-section mb-5">
                            <h3 class="section-heading text-danger">
                                <i class="fas fa-exclamation-circle"></i> Overdue Rentals (<?php echo count($overdue_rentals); ?>)
                            </h3>
                            <div class="rental-grid">
                                <?php foreach ($overdue_rentals as $rental): ?>
                                    <div class="rental-card overdue">
                                        <div class="rental-img">
                                            <img src="<?php echo !empty($rental['product_img']) ? $rental['product_img'] : 'images/default-product.jpg'; ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>">
                                        </div>
                                        <div class="rental-details">
                                            <h4><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                            <p><strong>From:</strong> <?php echo htmlspecialchars($rental['seller_name']); ?></p>
                                            <p><strong>Rental Price:</strong> $<?php echo number_format($rental['rental_price'], 2); ?></p>
                                            <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?></p>
                                            <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($rental['rental_end_date'])); ?></p>
                                            <p class="days-overdue"><strong>Overdue by:</strong> <?php echo abs($rental['days_remaining']); ?> days</p>
                                            <p class="late-fee-warning"><i class="fas fa-exclamation-triangle"></i> Late fees of 5% of rental price per day will apply (approximately $<?php echo number_format($rental['rental_price'] * 0.05, 2); ?> per day)</p>
                                            
                                            <form method="post" action="rental.php">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <button type="submit" class="return-button">Return Now</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($active_rentals)): ?>
                        <div class="active-section">
                            <h3 class="section-heading">
                                <i class="fas fa-check-circle"></i> Active Rentals (<?php echo count($active_rentals); ?>)
                            </h3>
                            <div class="rental-grid">
                                <?php foreach ($active_rentals as $rental): ?>
                                    <div class="rental-card">
                                        <div class="rental-img">
                                            <img src="<?php echo !empty($rental['product_img']) ? $rental['product_img'] : 'images/default-product.jpg'; ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>">
                                        </div>
                                        <div class="rental-details">
                                            <h4><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                            <p><strong>From:</strong> <?php echo htmlspecialchars($rental['seller_name']); ?></p>
                                            <p><strong>Rental Price:</strong> $<?php echo number_format($rental['rental_price'], 2); ?></p>
                                            <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?></p>
                                            <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($rental['rental_end_date'])); ?></p>
                                            <p class="days-remaining"><strong>Days Remaining:</strong> <?php echo $rental['days_remaining']; ?></p>
                                            
                                            <form method="post" action="rental.php">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <button type="submit" class="return-button">Return Early</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($returned_rentals)): ?>
                        <div class="returned-section mt-5">
                            <h3 class="section-heading">
                                <i class="fas fa-history"></i> Rental History (<?php echo count($returned_rentals); ?>)
                            </h3>
                            <div class="rental-grid">
                                <?php foreach ($returned_rentals as $rental): ?>
                                    <div class="rental-card returned">
                                        <div class="rental-img">
                                            <img src="<?php echo !empty($rental['product_img']) ? $rental['product_img'] : 'images/default-product.jpg'; ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>">
                                        </div>
                                        <div class="rental-details">
                                            <h4><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                            <p><strong>From:</strong> <?php echo htmlspecialchars($rental['seller_name']); ?></p>
                                            <p><strong>Rental Price:</strong> $<?php echo number_format($rental['rental_price'], 2); ?></p>
                                            <p><strong>Rental Period:</strong> <?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?> - <?php echo date('M d, Y', strtotime($rental['return_date'])); ?></p>
                                            <p><strong>Duration:</strong> <?php echo $rental['actual_duration']; ?> days</p>
                                            
                                            <?php if ($rental['late_fee'] > 0): ?>
                                                <p class="late-fee"><strong>Late Fee Paid:</strong> $<?php echo number_format($rental['late_fee'], 2); ?></p>
                                            <?php endif; ?>
                                            
                                            <span class="rental-status status-returned">Returned</span>
                                            
                                            <form method="post" action="rental.php" class="delete-form" onsubmit="return confirmDelete()">
                                                <input type="hidden" name="action" value="delete_rental_history">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <button type="submit" class="delete-button">
                                                    <i class="fas fa-trash"></i> Delete from History
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Rentals From Me Tab Content -->
            <div class="tab-content" id="rentals-from-me">
                <?php if (empty($seller_rentals) && empty($seller_returned)): ?>
                    <div class="empty-rentals">
                        <i class="fas fa-store"></i>
                        <h3>You don't have any products rented out</h3>
                        <p>When someone rents your products, they will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($seller_rentals)): ?>
                        <div class="seller-section">
                            <h3 class="section-heading">
                                <i class="fas fa-handshake"></i> Currently Rented Out (<?php echo count($seller_rentals); ?>)
                            </h3>
                            <div class="rental-grid">
                                <?php foreach ($seller_rentals as $rental): ?>
                                    <div class="rental-card <?php echo $rental['is_overdue'] ? 'overdue' : ''; ?>">
                                        <div class="rental-img">
                                            <img src="<?php echo !empty($rental['product_img']) ? $rental['product_img'] : 'images/default-product.jpg'; ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>">
                                        </div>
                                        <div class="rental-details">
                                            <h4><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                            <p><strong>Rented To:</strong> <?php echo htmlspecialchars($rental['buyer_name']); ?></p>
                                            <div class="contact-info">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($rental['buyer_email']); ?>
                                            </div>
                                            <p><strong>Rental Price:</strong> $<?php echo number_format($rental['rental_price'], 2); ?></p>
                                            <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?></p>
                                            <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($rental['rental_end_date'])); ?></p>
                                            
                                            <?php if ($rental['is_overdue']): ?>
                                                <p class="days-overdue"><strong>Overdue by:</strong> <?php echo abs($rental['days_remaining']); ?> days</p>
                                                <span class="rental-status status-overdue">Overdue</span>
                                                
                                                <?php if (abs($rental['days_remaining']) >= 10): ?>
                                                    <form method="post" action="rental.php" style="margin-top: 10px;">
                                                        <input type="hidden" name="action" value="report_overdue">
                                                        <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                        <button type="submit" class="report-button">
                                                            <i class="fas fa-flag"></i> Report User
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="days-remaining"><strong>Days Remaining:</strong> <?php echo $rental['days_remaining']; ?></p>
                                                <span class="rental-status status-rented">Active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($seller_returned)): ?>
                        <div class="seller-section">
                            <h3 class="section-heading">
                                <i class="fas fa-undo-alt"></i> Recently Returned (<?php echo count($seller_returned); ?>)
                            </h3>
                            <div class="rental-grid">
                                <?php foreach ($seller_returned as $rental): ?>
                                    <div class="rental-card">
                                        <div class="rental-img">
                                            <img src="<?php echo !empty($rental['product_img']) ? $rental['product_img'] : 'images/default-product.jpg'; ?>" alt="<?php echo htmlspecialchars($rental['product_name']); ?>">
                                        </div>
                                        <div class="rental-details">
                                            <h4><?php echo htmlspecialchars($rental['product_name']); ?></h4>
                                            <p><strong>Rented To:</strong> <?php echo htmlspecialchars($rental['buyer_name']); ?></p>
                                            <p><strong>Rental Price:</strong> $<?php echo number_format($rental['rental_price'], 2); ?></p>
                                            <p><strong>Rental Period:</strong> <?php echo date('M d, Y', strtotime($rental['rental_start_date'])); ?> - <?php echo date('M d, Y', strtotime($rental['return_date'])); ?></p>
                                            <p><strong>Duration:</strong> <?php echo $rental['actual_duration']; ?> days</p>
                                            
                                            <?php if ($rental['late_fee'] > 0): ?>
                                                <p class="late-fee"><strong>Late Fee Collected:</strong> $<?php echo number_format($rental['late_fee'], 2); ?></p>
                                            <?php endif; ?>
                                            
                                            <span class="rental-status status-returned">Returned</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Modal event listeners removed
        });
        
        function confirmDelete() {
            return confirm("Are you sure you want to delete this rental history record? This action cannot be undone.");
        }
        
        // Modal functions removed since we're submitting directly
    </script>

</body>
</html>