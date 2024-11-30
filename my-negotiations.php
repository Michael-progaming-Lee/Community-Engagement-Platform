<?php
session_start();
require_once 'php/config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle deletion of negotiations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_negotiations'])) {
    $negotiation_ids = $_POST['negotiation_ids'] ?? [];
    if (!empty($negotiation_ids)) {
        $ids_string = implode(',', array_map('intval', $negotiation_ids));
        $delete_query = "DELETE FROM price_Negotiation 
                        WHERE id IN ($ids_string) 
                        AND (seller_response = 'rejected' OR seller_response = 'accepted')
                        AND (user_id = ? OR 
                            product_id IN (SELECT id FROM product WHERE product_seller = ?))";
        $delete_stmt = $con->prepare($delete_query);
        $username = $_SESSION['username'];
        $user_id = $_SESSION['id'];
        $delete_stmt->bind_param("is", $user_id, $username);
        $delete_stmt->execute();
        
        if ($delete_stmt->affected_rows > 0) {
            $_SESSION['success_message'] = "Selected negotiations have been deleted successfully.";
        } else {
            $_SESSION['error_message'] = "No negotiations were deleted. Please try again.";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

$username = $_SESSION['username'];
$user_id = $_SESSION['id'];

// Fetch all negotiations where user is either buyer or seller
$negotiations_query = "SELECT n.*, 
                             p.product_name,
                             p.product_cost as original_price,
                             p.product_seller,
                             CASE 
                                WHEN p.product_seller = ? THEN 'seller'
                                ELSE 'buyer'
                             END as user_role,
                             CASE 
                                WHEN p.product_seller = ? THEN u_buyer.username
                                ELSE p.product_seller
                             END as other_party
                      FROM price_Negotiation n
                      JOIN product p ON n.product_id = p.id
                      JOIN users u_buyer ON n.user_id = u_buyer.id
                      WHERE p.product_seller = ? OR n.user_id = ?
                      ORDER BY n.created_at DESC";

$stmt = $con->prepare($negotiations_query);
$stmt->bind_param("sssi", $username, $username, $username, $user_id);
$stmt->execute();
$negotiations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Price Negotiations</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-image: url('Background Images/Home_Background.png');
            background-size: cover;
            background-position: top center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .negotiations-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .negotiation-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        .negotiation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .status-pending { color: #f0ad4e; }
        .status-accepted { color: #5cb85c; }
        .status-rejected { color: #d9534f; }
        .role-badge {
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }
        .role-seller {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .role-buyer {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>

    <div class="container negotiations-container">
        <h2 class="mb-4">My Price Negotiations</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($negotiations->num_rows === 0): ?>
            <div class="alert alert-info">
                You don't have any price negotiations yet.
            </div>
        <?php else: ?>
            <form id="deleteForm" method="POST">
                <button type="submit" name="delete_negotiations" class="btn btn-danger mb-3" id="deleteSelected" style="display: none;">
                    Delete Selected
                </button>
                <?php while ($neg = $negotiations->fetch_assoc()): ?>
                    <div class="negotiation-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <?php if ($neg['seller_response'] === 'rejected' || $neg['seller_response'] === 'accepted'): ?>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input negotiation-checkbox" 
                                               name="negotiation_ids[]" value="<?php echo $neg['id']; ?>" 
                                               id="neg<?php echo $neg['id']; ?>">
                                        <label class="custom-control-label" for="neg<?php echo $neg['id']; ?>">
                                <?php endif; ?>
                                <h5>
                                    <?php echo htmlspecialchars($neg['product_name']); ?>
                                    <span class="role-badge role-<?php echo $neg['user_role']; ?>">
                                        <?php echo ucfirst($neg['user_role']); ?>
                                    </span>
                                </h5>
                                <?php if ($neg['seller_response'] === 'rejected' || $neg['seller_response'] === 'accepted'): ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                                <p class="mb-2">
                                    <?php echo $neg['user_role'] === 'seller' ? 'Buyer' : 'Seller'; ?>: 
                                    <?php echo htmlspecialchars($neg['other_party']); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="status-<?php echo $neg['seller_response']; ?>">
                                    Status: <?php echo ucfirst($neg['seller_response']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <strong>Original Price:</strong> 
                                $<?php echo number_format($neg['original_price'], 2); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Proposed Price:</strong> 
                                $<?php echo number_format($neg['proposed_price'], 2); ?>
                            </div>
                            <?php if ($neg['seller_response'] === 'accepted'): ?>
                                <div class="col-md-4">
                                    <strong>Final Price:</strong> 
                                    $<?php echo number_format($neg['final_price'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                Submitted: <?php echo date('F j, Y, g:i a', strtotime($neg['created_at'])); ?>
                            </small>
                            <a href="negotiate-price.php?product_id=<?php echo $neg['product_id']; ?>" 
                               class="btn btn-sm btn-primary float-right">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Show/hide delete button based on checkbox selection
            $('.negotiation-checkbox').change(function() {
                const checkedBoxes = $('.negotiation-checkbox:checked').length;
                $('#deleteSelected').toggle(checkedBoxes > 0);
            });

            // Confirm deletion
            $('#deleteForm').submit(function(e) {
                if (!confirm('Are you sure you want to delete the selected negotiations?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
