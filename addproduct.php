<?php
session_start();
include("php/config.php");

// Create uploads directory if it doesn't exist
$upload_dir = "uploads";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Create qrcodes directory if it doesn't exist
$qr_dir = "qrcodes";
if (!file_exists($qr_dir)) {
    mkdir($qr_dir, 0777, true);
}

// Add QR code column if it doesn't exist
$alter_query = "ALTER TABLE product ADD COLUMN IF NOT EXISTS product_qr_code VARCHAR(255) AFTER product_img";
mysqli_query($con, $alter_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/addproduct.css">
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <title>Add Product</title>
    <script>
        function toggleRentalOptions() {
            const listingType = document.querySelector('input[name="listing_type"]:checked')?.value;
            const rentalOptions = document.getElementById('rental_options');
            const regularPrice = document.getElementById('regular_price');
            const productCost = document.getElementById('product_cost');
            
            if (listingType === 'rent') {
                rentalOptions.style.display = 'grid';
                regularPrice.style.display = 'none';
                productCost.required = false;
                // Make at least one rental rate required
                document.querySelectorAll('.rental-rate').forEach(input => {
                    input.addEventListener('input', validateRentalRates);
                });
            } else {
                rentalOptions.style.display = 'none';
                regularPrice.style.display = 'block';
                productCost.required = true;
                document.querySelectorAll('.rental-rate').forEach(input => {
                    input.required = false;
                });
            }
        }

        function validateRentalRates() {
            const dailyRate = document.getElementById('daily_rate');
            const weeklyRate = document.getElementById('weekly_rate');
            const monthlyRate = document.getElementById('monthly_rate');
            const listingType = document.querySelector('input[name="listing_type"]:checked')?.value;

            if (listingType === 'rent') {
                const hasValue = dailyRate.value || weeklyRate.value || monthlyRate.value;
                [dailyRate, weeklyRate, monthlyRate].forEach(input => {
                    input.required = !hasValue;
                });
            }
        }

        window.onload = function() {
            document.querySelectorAll('input[name="listing_type"]').forEach(radio => {
                radio.addEventListener('change', toggleRentalOptions);
            });
            toggleRentalOptions();
        }
    </script>
</head>
<body style="background-image: url('Background Images/Add-Product.jpg'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <?php include("php/header.php"); ?>

    <div style="text-align: center; margin: 20px 0;">
        <h1 style="color: #333; text-shadow: 1px 1px 2px rgba(255,255,255,0.8);">Add New Product</h1>
    </div>

    <div class="container" style="background: rgba(255, 255, 255, 0.8); border-radius: 15px; padding: 20px; max-width: 800px; margin: 0 auto;">
        <!-- CRUD for Product Begin -->
        <?php

        $id = $_SESSION['id'];
        $query = mysqli_query($con, "SELECT*FROM users WHERE Id=$id");

        while ($result = mysqli_fetch_assoc($query)) {
            $res_Uname = $result['Username'];
            $res_Email = $result['Email'];
            $res_Age = $result['Age'];
            $res_id = $result['Id'];
        }

        include("php/config.php");
        if (isset($_POST['submit'])) {
            $product_seller = $res_Uname;
            $product_seller_id = $res_id;
            $product_name = $_POST['product_name'];
            $product_category = $_POST['product_category'];
            $product_description = $_POST['product_description'];
            $product_quantity = $_POST['product_quantity'];
            $listing_type = $_POST['listing_type'];
            
            // Handle pricing based on listing type
            if ($listing_type === 'rent') {
                $daily_rate = !empty($_POST['daily_rate']) ? $_POST['daily_rate'] : NULL;
                $weekly_rate = !empty($_POST['weekly_rate']) ? $_POST['weekly_rate'] : NULL;
                $monthly_rate = !empty($_POST['monthly_rate']) ? $_POST['monthly_rate'] : NULL;
                
                // Validate that at least one rate is provided
                if (!$daily_rate && !$weekly_rate && !$monthly_rate) {
                    echo "<div class='message error'>
                    <p>Please provide at least one rental rate (daily, weekly, or monthly).</p></div>";
                    exit;
                }
                $product_cost = NULL;
            } else {
                $product_cost = $_POST['product_cost'];
                $daily_rate = NULL;
                $weekly_rate = NULL;
                $monthly_rate = NULL;
            }

            // Handle file upload
            $upload_status = true;
            $target_file = "";

            if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['product_img']['name'], PATHINFO_EXTENSION));
                $allowed_types = array('jpg', 'jpeg', 'png');

                if (in_array($file_extension, $allowed_types)) {
                    // Generate unique filename
                    $new_filename = uniqid() . '.' . $file_extension;
                    $target_file = $upload_dir . '/' . $new_filename;

                    if (move_uploaded_file($_FILES['product_img']['tmp_name'], $target_file)) {
                        $product_img = $target_file;
                    } else {
                        $upload_status = false;
                        echo "<div class='message error'>
                        <p>Sorry, there was an error uploading your file.</p></div>";
                    }
                } else {
                    $upload_status = false;
                    echo "<div class='message error'>
                    <p>Sorry, only JPG, JPEG & PNG files are allowed.</p></div>";
                }
            } else {
                $upload_status = false;
                echo "<div class='message error'>
                <p>Please select an image file.</p></div>";
            }

            if ($upload_status) {
                // First insert the product without QR code
                $stmt = mysqli_prepare($con, "INSERT INTO product (product_seller_id, product_name, product_category, product_description, product_quantity, product_cost, product_img, listing_type, daily_rate, weekly_rate, monthly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt) {
                    // Bind parameters with appropriate types
                    mysqli_stmt_bind_param($stmt, "ssssssssddd", 
                        $product_seller_id,
                        $product_name,
                        $product_category,
                        $product_description,
                        $product_quantity,
                        $product_cost,
                        $product_img,
                        $listing_type,
                        $daily_rate,
                        $weekly_rate,
                        $monthly_rate
                    );
                    
                    // Execute the statement
                    if (mysqli_stmt_execute($stmt)) {
                        $product_id = mysqli_insert_id($con);
                        
                        // Generate QR code and save as image
                        $qr_filename = 'qrcodes/product_' . $product_id . '.png';
                        $qr_filepath = __DIR__ . '/' . $qr_filename;
                        $product_url = "http://" . $_SERVER['HTTP_HOST'] . "/Community%20Engagement%20Platform/product_details.php?id=" . $product_id;
                        
                        // Update the product with QR code path
                        $update_stmt = mysqli_prepare($con, "UPDATE product SET product_qr_code = ? WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "si", $qr_filename, $product_id);
                        mysqli_stmt_execute($update_stmt);
                        
                        echo "<div class='message success'>
                            <p>Product Added Successfully!</p>
                            <div id='qrcode' style='margin: 20px auto; display: flex; justify-content: center; align-items: center;'></div>
                            <p style='text-align: center;'>Scan this QR code to view product details</p>
                            <script>
                                new QRCode(document.getElementById('qrcode'), {
                                    text: '" . $product_url . "',
                                    width: 200,
                                    height: 200
                                });
                                
                                // Save QR code as image
                                setTimeout(function() {
                                    var qrCanvas = document.querySelector('#qrcode canvas');
                                    var qrImage = qrCanvas.toDataURL('image/png');
                                    
                                    // Send QR code image to server
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('POST', 'save_qr.php', true);
                                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                    xhr.send('qr_image=' + encodeURIComponent(qrImage) + '&filename=" . urlencode($qr_filename) . "');
                                }, 500);
                            </script>
                            <a href='addproduct.php' style='display: block; margin-top: 15px;'>
                                <button class='btn'>Add Another Product</button>
                            </a>
                            </div>";
                    } else {
                        echo "<div class='message error'>
                        <p>Error occurred: " . mysqli_stmt_error($stmt) . "</p></div>";
                    }
                    
                    // Close the statement
                    mysqli_stmt_close($stmt);
                } else {
                    echo "<div class='message error'>
                    <p>Error in preparing statement: " . mysqli_error($con) . "</p></div>";
                }
            }
        } else {
        ?>
            <div style="display: grid; gap: 20px;">
                <div style="background: rgba(255, 255, 255, 0.9); padding: 15px; border-radius: 10px;">
                    <p style="margin: 5px 0;">Your name: <b><?php echo $res_Uname ?></b></p>
                    <p style="margin: 5px 0;">Your ID#: <b><?php echo $res_id ?></b></p>
                </div>

                <form action="" method="post" enctype="multipart/form-data" style="display: grid; gap: 15px;">
                    <div style="margin-bottom: 15px;">
                        <label for="listing_type" style="display: block; margin-bottom: 5px; font-weight: bold;">Listing Type:</label>
                        <div style="display: flex; gap: 20px;">
                            <label style="cursor: pointer;">
                                <input type="radio" name="listing_type" value="sell" required> Sell
                            </label>
                            <label style="cursor: pointer;">
                                <input type="radio" name="listing_type" value="rent" required> Rent
                            </label>
                        </div>
                    </div>

                    <div class="product input">
                        <label for="product_name" style="width: 150px; display: inline-block; font-weight: bold;">Product Name</label>
                        <input type="text" name="product_name" id="product_name" autocomplete="off" required 
                               style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    </div>

                    <div class="product input">
                        <label for="product_category" style="width: 150px; display: inline-block; font-weight: bold;">Select Category</label>
                        <select id="product_category" name="product_category" required 
                                style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                            <option value="">--Choose Category--</option>
                            <option value="Vehicle">Vehicle</option>
                            <option value="Tool">Tool</option>
                            <option value="Appliances">Appliances</option>
                            <option value="House">House</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="product input">
                        <label for="product_description" style="width: 150px; display: inline-block; font-weight: bold;">Product Description</label>
                        <textarea name="product_description" id="product_description" rows="4" required
                                  style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;"></textarea>
                    </div>

                    <div class="product input">
                        <label for="product_quantity" style="width: 150px; display: inline-block; font-weight: bold;">Product Quantity</label>
                        <input type="number" name="product_quantity" id="product_quantity" min="1" autocomplete="off" required
                               style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    </div>

                    <div id="regular_price" class="product input">
                        <label for="product_cost" style="width: 150px; display: inline-block; font-weight: bold;">Price ($)</label>
                        <input type="number" step="0.01" name="product_cost" id="product_cost" autocomplete="off" required 
                               style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    </div>

                    <div id="rental_options" style="display: none; gap: 15px;">
                        <div class="product input">
                            <label for="daily_rate" style="width: 150px; display: inline-block; font-weight: bold;">Daily Rate ($)</label>
                            <input type="number" step="0.01" name="daily_rate" id="daily_rate" min="0" autocomplete="off"
                                   class="rental-rate" style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                            <small style="margin-left: 150px; color: #666;">Leave empty if not offering daily rentals</small>
                        </div>
                        
                        <div class="product input">
                            <label for="weekly_rate" style="width: 150px; display: inline-block; font-weight: bold;">Weekly Rate ($)</label>
                            <input type="number" step="0.01" name="weekly_rate" id="weekly_rate" min="0" autocomplete="off"
                                   class="rental-rate" style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                            <small style="margin-left: 150px; color: #666;">Leave empty if not offering weekly rentals</small>
                        </div>
                        
                        <div class="product input">
                            <label for="monthly_rate" style="width: 150px; display: inline-block; font-weight: bold;">Monthly Rate ($)</label>
                            <input type="number" step="0.01" name="monthly_rate" id="monthly_rate" min="0" autocomplete="off"
                                   class="rental-rate" style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                            <small style="margin-left: 150px; color: #666;">Leave empty if not offering monthly rentals</small>
                        </div>
                    </div>

                    <div class="product input">
                        <label for="product_img" style="width: 150px; display: inline-block; font-weight: bold;">Product Image</label>
                        <div style="display: inline-block; width: calc(100% - 170px);">
                            <input type="file" name="product_img" id="product_img" accept=".jpg, .jpeg, .png" required
                                   style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                            <small style="color: #666;">Accepted formats: JPG, JPEG, PNG</small>
                        </div>
                    </div>

                    <div class="field" style="text-align: center; margin-top: 20px;">
                        <input type="submit" class="btn" name="submit" value="Add Product" 
                               style="padding: 10px 30px; font-size: 16px;">
                    </div>
                </form>
            </div>
        <?php } ?>
    </div>
</body>

</html>