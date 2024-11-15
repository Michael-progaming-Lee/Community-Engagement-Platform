<?php
session_start();

include("php/config.php");

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "community_engagement_db";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}

// Fetch products from the database
$query = "SELECT id, product_name, product_cost, product_img FROM product";
$resultd = $conn->query($query);

if ($resultd ->num_rows > 0):
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="style/style.css">
        <script src="scripts.js" defer></script>
        <title>Home</title>

        <style>
            /* Styling for the product grid */
            .product-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
            }

            .product-item {
                border: 1px solid #ccc;
                padding: 10px;
                width: 200px;
                text-align: center;
            }

            .product-img {
                width: 100%;
                align-items: center;
                height: auto;
            }

            .product-name {
                font-size: 18px;
                margin: 10px 0;
            }

            .product-cost {
                font-size: 16px;
                color: #333;
            }
        </style>

    </head>

    <body>
        <div class="nav">
            <div class="logo">
                <p><a href="home.php">Logo</a> </p>
            </div>

            <div class="right-links">

                <?php

                $id = $_SESSION['id'];
                $query = mysqli_query($conn, "SELECT*FROM users WHERE Id=$id");

                while ($result = mysqli_fetch_assoc($query)) {
                    $res_Uname = $result['Username'];
                    $res_Email = $result['Email'];
                    $res_Age = $result['Age'];
                    $res_id = $result['Id'];
                }

                echo "<a href='edituserinfo.php?Id=$res_id'>Change Profile Information</a>";
                ?>

                <a href="php/logout.php"> <button class="btn">Log Out</button> </a>

            </div>
        </div>

        <main>
            <!-- Welcome Message-->
            <div class="main-box top">
                <div class="top">
                    <div class="box">
                        <p>Hello <b><?php echo $res_Uname ?></b>, Welcome</p>
                    </div>
                    <div class="box">
                        <p>Your email is <b><?php echo $res_Email ?></b>.</p>
                    </div>
                </div>
            </div>
        </main>

        <a href="addproduct.php"> <button class="btn">Add New Product</button> </a>
        <a href="users_cart.php"> <button class="btn">View Yoour Cart</button> </a>

        <h1>Product Store</h1>
        <div class="product-grid">
            <?php while ($row = $resultd ->fetch_assoc()): ?>
                <div class="product-item">
                    <a href="product_details.php?id=<?php echo $row['id']; ?>">
                    <img src="<?php echo $row['product_img']; ?>" alt="<?php echo $row['product_img']; ?>" class="product-img">
                        <div class="product-name"><?php echo $row['product_name']; ?></div>
                        <div class="product-cost">$<?php echo number_format($row['product_cost'], 2); ?></div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>


    </body>

    </html>
<?php
else:
    echo "<p>No products found.</p>";
endif;

// Close the database connection
$conn->close();
?>