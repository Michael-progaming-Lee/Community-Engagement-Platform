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

// Determine if we're in a subdirectory
$isSubdir = strpos($_SERVER['PHP_SELF'], '/php/') !== false;
$imgPath = $isSubdir ? '../Background Images/CommUnity Logo.png' : 'Background Images/CommUnity Logo.png';
$homePath = $isSubdir ? '../home.php' : 'home.php';
?>

<div class="nav" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(147, 163, 178, 0.8); backdrop-filter: blur(10px);">
    <div class="logo">
        <a href="<?php echo $homePath; ?>" style="text-decoration: none;">
            <img src="<?php echo $imgPath; ?>" alt="Company Logo" style="height: 50px; margin-left: -150px;">
        </a>
    </div>

    <div style="position: absolute; text-align: left; top: 50%; transform: translateY(-50%); pointer-events: none; left: 250px;">
        <a href="<?php echo $homePath; ?>" style="text-decoration: none; pointer-events: auto;">
            <h1 style="margin: 0; font-size: 24px; color: #333;">CommUnity Rentals</h1>
        </a>
    </div>

    <div class="right-links">
        <?php echo "<a href='manage_product.php?Id=$res_id'>Edit Previously Added Products</a>"; ?>
        <a href="users_cart.php"><button class="btn">View Cart</button></a>
        <a href="php/logout.php"><button class="btn">Log Out</button></a>
    </div>
</div>
<div style="height: 70px;"></div>
