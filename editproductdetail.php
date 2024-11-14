<?php
include("php/config.php");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Change Profile</title>
</head>

<body>
    <div class="nav">
        <div class="logo">
            <p><a href="home.php"> Logo</a></p>
        </div>
    </div>

    <div class="container">
        <div class="box form-box">
            <?php
            if (isset($_POST['submit'])) {
                $product_seller = $res_Uname;
                $product_name = $_POST['product_name'];
                $product_description = $_POST['product_description'];
                $product_quantity = $_POST['product_quantity'];
                $product_cost = $_POST['product_cost'];
                $product_img = $_POST['product_img'];

                $id = $_POST['product_id'];

                $edit_query = mysqli_query($con, "UPDATE product SET product_name='$product_name', product_description='$product_description',
                product_quantity='$product_quantity', product_cost='$product_cost' WHERE id=$id ") or die("error occurred");

                if ($edit_query) {
                    echo "<div class='message'>
                    <p>Product Detail Successfully Updated!</p>
                </div> <br>";
                    echo "<a href='home.php'><button class='btn'>Go Home</button>";
                }
            

            ?>
                <header>Change Profile</header>
                <form action="" method="post">
                    <div class="field input">
                        <label for="product_name">Product Name</label>
                        <input type="text" name="product_name" id="product_name" value="<?php echo $product_name; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="product_discription">Product Description</label>
                        <input type="text" name="product_discription" id="product_discription" value="<?php echo $product_discription; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="product_quantity">Product Quantity</label>
                        <input type="number" name="product_quantity" id="product_quantity" value="<?php echo $product_quantity; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="product_cost">Product Cost</label>
                        <input type="number" name="product_cost" id="product_cost" value="<?php echo $product_cost; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="product_img">Product Image</label>
                        <input type="file" name="product_img" id="product_img" accept=".jpg, .jpeg, .png" value="<?php echo $product_img; ?>" autocomplete="off" required>
                    </div>

                    <div class="field">
                        <input type="submit" class="btn" name="submit" value="Update" required>
                    </div>

                </form>
        </div>
    <?php } ?>
    </div>
</body>

</html>