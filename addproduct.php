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

    <div class="container" style="background: transparent; backdrop-filter: blur(3px); border-radius: 15px; padding: 20px;">
        <!-- CRUD for Product Begin -->
        <?php

        $id = $_SESSION['id'];
        $query = mysqli_query($con, "SELECT*FROM users WHERE Id=$id");

        while ($result = mysqli_fetch_assoc($query)) {
            //user logins
            $res_Uname = $result['Username'];
            $res_Email = $result['Email'];
            $res_Age = $result['Age'];
            $res_id = $result['Id'];
        }

        include("php/config.php");
        if (isset($_POST['submit'])) {
            $product_seller = $res_Uname;
            $product_name = $_POST['product_name'];
            $product_category = $_POST['product_category'];
            $product_description = $_POST['product_description'];
            $product_quantity = $_POST['product_quantity'];
            $product_cost = $_POST['product_cost'];

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
                mysqli_query($con, "INSERT INTO product(product_seller,product_name,product_category,product_description,product_quantity,product_cost,product_img)
                VALUES('$product_seller','$product_name','$product_category','$product_description','$product_quantity','$product_cost','$product_img')") or die("Error Occurred");

                echo "<div class='message success'>
                <p>Product has been added Successfully!</p><br>";
                echo "<a href='addproduct.php'><button class='btn'>Add Another Product</button></a></div>";
            }
        } else {
        ?>
            <h1>Add New Product</h1>

            <form action="" method="post" enctype="multipart/form-data">
                <p>Your name: <b><?php echo $res_Uname ?></b></p>

                <div class="product input">
                    <label for="product_name" style="width: 150px; display: Inline-block;">Product Name</label>
                    <input type="text" name="product_name" id="product_name" autocomplete="off" required>
                </div>

                <label for="product_category" style="width: 150px; display: Inline-block;">Select Category</label>
                <select id="product_category" name="product_category" required>
                    <option value="">--Choose Category--</option>
                    <option value="Vehicle">Vehicle</option>
                    <option value="Tool">Tool</option>
                    <option value="Appliances">Appliances</option>
                    <option value="House">House</option>
                    <option value="Other">Other</option>
                </select>
                <br><br>

                <div class="product input">
                    <label for="product_description" style="width: 150px; display: Inline-block;">Product Description</label>
                    <textarea name="product_description" id="product_description" rows="4" required></textarea>
                </div>

                <div class="product input">
                    <label for="product_quantity" style="width: 150px; display: Inline-block;">Product Quantity</label>
                    <input type="number" name="product_quantity" id="product_quantity" min="1" autocomplete="off" required>
                </div>

                <div class="product input">
                    <label for="product_cost" style="width: 150px; display: Inline-block;">Product Cost</label>
                    <input type="number" name="product_cost" id="product_cost" min="0" step="0.01" autocomplete="off" required>
                </div>

                <div class="product input">
                    <label for="product_img" style="width: 150px; display: Inline-block;">Product Image</label>
                    <input type="file" name="product_img" id="product_img" accept=".jpg, .jpeg, .png" required>
                    <small>Accepted formats: JPG, JPEG, PNG</small>
                </div>

                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Add Product">
                </div>
            </form>
        <?php } ?>
    </div>
</body>

</html>