<?php
session_start();
include("php/config.php");

// Create uploads directory if it doesn't exist
$upload_dir = "uploads";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/addproduct.css">
    <title>Add Product</title>
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
            $product_cost = $_POST['product_cost'];
            $listing_type = $_POST['listing_type'];

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
                // Prepare the statement
                $stmt = mysqli_prepare($con, "INSERT INTO product (product_seller, product_seller_id, product_name, product_category, product_description, product_quantity, product_cost, product_img, listing_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt) {
                    // Bind parameters with appropriate types
                    mysqli_stmt_bind_param($stmt, "sssssssss", 
                        $product_seller,
                        $product_seller_id,
                        $product_name,
                        $product_category,
                        $product_description,
                        $product_quantity,
                        $product_cost,
                        $product_img,
                        $listing_type
                    );
                    
                    // Execute the statement
                    if (mysqli_stmt_execute($stmt)) {
                        echo "<div class='message success'>
                        <p>Product has been added Successfully!</p><br>";
                        echo "<a href='addproduct.php'><button class='btn'>Add Another Product</button></a></div>";
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

                    <div class="product input">
                        <label for="product_cost" style="width: 150px; display: inline-block; font-weight: bold;">Product Cost</label>
                        <input type="number" name="product_cost" id="product_cost" min="0" step="0.01" autocomplete="off" required
                               style="width: calc(100% - 170px); padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
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