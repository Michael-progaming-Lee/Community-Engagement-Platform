<?php
session_start();
require_once 'php/config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id === 0) {
    die("No product ID specified");
}

// Fetch product details
$product_query = "SELECT p.*, u.username as seller_username 
                 FROM product p 
                 JOIN users u ON p.product_seller_id = u.id
                 WHERE p.id = ?";
$stmt = $con->prepare($product_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();

if (!$product) {
    die("Product not found");
}

// Handle new negotiation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['propose_price'])) {
    $proposed_price = floatval($_POST['proposed_price']);
    
    if ($proposed_price <= 0) {
        echo "<script>alert('Please enter a valid price.');</script>";
    } else {
        $insert_query = "INSERT INTO price_Negotiation (product_id, user_id, seller_id, original_price, proposed_price) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $con->prepare($insert_query);
        $stmt->bind_param("iiiss", $product_id, $user_id, $product['product_seller_id'], $product['product_cost'], $proposed_price);
        if (!$stmt->execute()) {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        } else {
            echo "<script>alert('Price proposal submitted successfully!'); window.location.reload();</script>";
        }
    }
}

// Handle seller response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['negotiation_response'])) {
    $negotiation_id = (int)$_POST['negotiation_id'];
    $response = $_POST['response'];
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        // Get the negotiation details
        $get_proposal_query = "SELECT proposed_price FROM price_Negotiation WHERE id = ? AND seller_id = ?";
        $stmt = $con->prepare($get_proposal_query);
        $stmt->bind_param("ii", $negotiation_id, $user_id);
        $stmt->execute();
        $proposal_result = $stmt->get_result();
        $proposal = $proposal_result->fetch_assoc();
        
        if (!$proposal) {
            throw new Exception("Negotiation not found or unauthorized");
        }
        
        $final_price = $response === 'accepted' ? $proposal['proposed_price'] : null;
        
        // Update negotiation status
        $update_query = "UPDATE price_Negotiation 
                       SET seller_response = ?, 
                           final_price = ?
                       WHERE id = ? AND seller_id = ?";
        $stmt = $con->prepare($update_query);
        $stmt->bind_param("sdii", $response, $final_price, $negotiation_id, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update negotiation");
        }
        
        // If accepted, update product price and reject other negotiations
        if ($response === 'accepted') {
            // Update product price
            $update_price_query = "UPDATE product 
                                 SET product_cost = ? 
                                 WHERE id = ? AND product_seller_id = ?";
            $stmt = $con->prepare($update_price_query);
            $stmt->bind_param("dii", $final_price, $product_id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product price");
            }
            
            // Reject other pending negotiations
            $reject_others_query = "UPDATE price_Negotiation 
                                  SET seller_response = 'rejected' 
                                  WHERE product_id = ? 
                                  AND id != ? 
                                  AND seller_response = 'pending'";
            $stmt = $con->prepare($reject_others_query);
            $stmt->bind_param("ii", $product_id, $negotiation_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update other negotiations");
            }
        }
        
        $con->commit();
        echo "<script>alert('" . ($response === 'accepted' ? 'Price negotiation accepted.' : 'Negotiation rejected.') . "'); window.location.reload();</script>";
        exit();
        
    } catch (Exception $e) {
        $con->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// Fetch negotiations
$is_seller = ($product['seller_username'] === $username);

if ($is_seller) {
    $negotiations_query = "SELECT n.*, u.username as buyer_name 
                          FROM price_Negotiation n 
                          JOIN users u ON n.user_id = u.id 
                          WHERE n.product_id = ? AND n.seller_id = ?
                          ORDER BY n.created_at DESC";
    $stmt = $con->prepare($negotiations_query);
    $stmt->bind_param("ii", $product_id, $user_id);
} else {
    $negotiations_query = "SELECT n.* 
                          FROM price_Negotiation n 
                          WHERE n.product_id = ? AND n.user_id = ?
                          ORDER BY n.created_at DESC";
    $stmt = $con->prepare($negotiations_query);
    $stmt->bind_param("ii", $product_id, $user_id);
}
$stmt->execute();
$negotiations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Negotiation</title>
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
        .negotiation-window {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .negotiation-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-pending { color: #f0ad4e; }
        .status-accepted { color: #5cb85c; }
        .status-rejected { color: #d9534f; }
    </style>
</head>
<body>
    <?php include("php/header.php"); ?>
    
    <div class="container">
        <div class="negotiation-window">
            <h2>Price Negotiation for <?php echo htmlspecialchars($product['product_name']); ?></h2>
            <p>Current Price: $<?php echo number_format($product['product_cost'], 2); ?></p>

            <?php if (!$is_seller) { ?>
                <div class="mb-4">
                    <form method="POST" class="form-inline">
                        <div class="form-group mx-sm-3 mb-2">
                            <label for="proposed_price" class="sr-only">Propose Price</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="proposed_price" 
                                   name="proposed_price" placeholder="Enter your proposed price" required>
                        </div>
                        <button type="submit" name="propose_price" class="btn btn-primary mb-2">Submit Proposal</button>
                    </form>
                </div>
            <?php } ?>

            <div class="negotiations-list">
                <?php while ($negotiation = $negotiations->fetch_assoc()) { ?>
                    <div class="negotiation-item">
                        <?php if ($is_seller) { ?>
                            <p>Buyer: <?php echo htmlspecialchars($negotiation['buyer_name']); ?></p>
                            <p>Proposed Price: $<?php echo number_format($negotiation['proposed_price'], 2); ?></p>
                            
                            <?php if ($negotiation['seller_response'] == 'pending') { ?>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="negotiation_id" value="<?php echo $negotiation['id']; ?>">
                                    <div class="form-group">
                                        <select name="response" class="form-control" required>
                                            <option value="">Select Response</option>
                                            <option value="accepted">Accept Price</option>
                                            <option value="rejected">Reject Price</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="negotiation_response" class="btn btn-primary">Submit Response</button>
                                </form>
                            <?php } else { ?>
                                <p class="status-<?php echo $negotiation['seller_response']; ?>">
                                    Status: <?php echo ucfirst($negotiation['seller_response']); ?>
                                    <?php if ($negotiation['seller_response'] == 'accepted') { ?>
                                        - Final Price: $<?php echo number_format($negotiation['final_price'], 2); ?>
                                    <?php } ?>
                                </p>
                            <?php } ?>
                        <?php } else { ?>
                            <p>Your Proposed Price: $<?php echo number_format($negotiation['proposed_price'], 2); ?></p>
                            <p class="status-<?php echo $negotiation['seller_response']; ?>">
                                Status: <?php echo ucfirst($negotiation['seller_response']); ?>
                                <?php if ($negotiation['seller_response'] == 'accepted') { ?>
                                    - Final Price: $<?php echo number_format($negotiation['final_price'], 2); ?>
                                <?php } ?>
                            </p>
                        <?php } ?>
                        <small class="text-muted">Submitted: <?php echo $negotiation['created_at']; ?></small>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
