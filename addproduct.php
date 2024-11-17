<?php
session_start();

include("php/config.php");
if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Add Product</title>
</head>

<body>
    <!-- CRUD for Product Begin -->
    <header>
        <div class="nav">
            <div class="logo">
                <p><a href="home.php">Logo</a> </p>
            </div>

            <div class="right-links">

                <?php

                $id = $_SESSION['id'];
                $query = mysqli_query($conn, "SELECT*FROM users WHERE Id=$id");

                while ($result = mysqli_fetch_assoc($query)) {
                    //user logins
                    $res_Uname = $result['Username'];
                    $res_Email = $result['Email'];
                    $res_Age = $result['Age'];
                    $res_id = $result['Id'];
                }

                echo "<a href='manage_product.php?Id=$res_id'>Edit Product Detail</a>";
                ?>

                <a href="php/logout.php"> <button class="btn">Log Out</button> </a>

            </div>
    </header>

    <?php

    include("php/config.php");
    if (isset($_POST['submit'])) {
        $product_seller = $res_Uname;
        $product_name = $_POST['product_name'];
        $product_category = $_POST['product_category'];
        $product_description = $_POST['product_description'];
        $product_quantity = $_POST['product_quantity'];
        $product_cost = $_POST['product_cost'];
        $product_img = $_POST['product_img'];

        mysqli_query($conn, "INSERT INTO product(product_seller,product_name,product_category,product_description,product_quantity,product_cost,product_img)
        VALUES('$product_seller','$product_name','$product_category','$product_description','$product_quantity','$product_cost','$product_img')") or die("Error Occured");

        echo "<div class='message'>
        <p>Product has been added Successfully!</p> </div> <br>";
        echo "<a href='home.php'><button class='btn'>Home Page</button> <a href='addproduct.php'><button class='btn'>Add Another Product</button>";
    } else {

    ?>

        <header>Add New Product</header>

        <form action="" method="post">

            <p>Your name: <b><?php echo $res_Uname ?></b> </p>

            <div class="product input">
                <label for="product_name">Product Name</label>
                <input type="text" name="product_name" id="product_name" autocomplete="off" required>
            </div>

            <label for="product_category">Select Category:</label>
            <select id="product_category" name="product_category" required>
                <option value="">--Choose Category--</option>
                <option value="Vehicle">Vehicle</option>
                <option value="Tool">Tool</option>
                <option value="Other">Other</option>
            </select>
            <br><br>

            <div class="product input">
                <label for="product_description">Product Description</label>
                <input type="Text" name="product_description" id="product_description" autocomplete="off" required>
            </div>

            <div class="product input">
                <label for="product_quantity">Product Quantity</label>
                <input type="Number" name="product_quantity" id="product_quantity" autocomplete="off" required>
            </div>

            <div class="product input">
                <label for="product_cost">Product Cost</label>
                <input type="Number" name="product_cost" id="product_cost" autocomplete="off" required>
            </div>

            <div class="product input">
                <label for="product_img">Product Image</label>
                <input type="file" name="product_img" id="product_img" accept=".jpg, .jpeg, .png" autocomplete="off" required>
            </div>


            <div class="field">
                <input type="submit" class="btn" name="submit" value="Add Product" required>
            </div>

        </form>
    <?php } ?>
</body>

</html>