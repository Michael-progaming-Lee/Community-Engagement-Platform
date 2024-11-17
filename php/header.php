<?php
if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

// Get user information
$id = $_SESSION['id'];
$query = mysqli_query($con, "SELECT * FROM users WHERE Id=$id");
$result = mysqli_fetch_assoc($query);
$res_Uname = $result['Username'];
$res_Email = $result['Email'];
$res_Age = $result['Age'];
$res_id = $result['Id'];
?>

<div class="nav">
    <div class="logo">
        <p><a href="home.php">Logo</a></p>
    </div>

    <div class="right-links">
        <?php echo "<a href='manage_product.php?Id=$res_id'>Edit Previously Added Products</a>"; ?>
        <a href="users_cart.php"><button class="btn">View Cart</button></a>
        <a href="php/logout.php"><button class="btn">Log Out</button></a>
    </div>
</div>
