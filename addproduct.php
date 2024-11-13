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
    <title>Document</title>
</head>

<body>
    <!-- CRUD for Product Begin -->
    <div class="nav">
        <div class="logo">
            <p><a href="home.php">Logo</a> </p>
        </div>

        <div class="right-links">

            <?php

            $id = $_SESSION['id'];
            $query = mysqli_query($con, "SELECT*FROM users WHERE Id=$id");

            while ($result = mysqli_fetch_assoc($query)) {
                $res_Uname = $result['Username'];
                $res_Email = $result['Email'];
                $res_Age = $result['Age'];
                $res_id = $result['Id'];
            }

            echo "<a href='editproductdetail.php?Id=$res_id'>Edit Product Detail</a>";
            ?>

            <a href="php/logout.php"> <button class="btn">Log Out</button> </a>

        </div>
    </div>

    <header>Add New Product</header>

    <form action="" method="post">

        <div class="product input">
            <label for="Product-Name">Product Name</label>
            <input type="text" name="Product_Name" id="Product_Name" autocomplete="off" required>
        </div>

        <p>Your name: <b><?php echo $res_Uname ?></b> </p>
        <!--
                <div class="product input">
                    <label for="email">Email</label>
                    <input type="text" name="email" id="email" autocomplete="off" required>
                </div>-->

        <div class="product input">
            <label for="product-description">Product Description</label>
            <input type="Text" name="product_description" id="product_description" autocomplete="off" required>
        </div>

        <div class="field">
            <input type="submit" class="btn" name="Add-product" value="Add Product" required>
        </div>
        
    </form>
</body>

</html>