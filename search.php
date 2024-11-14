<?php
// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the product ID from the submitted form
    $product_id = $_POST['product_id'];

    // Database credentials
    $servername = "localhost"; // or your server address
    $username = "root";
    $password = "";
    $dbname = "community_engagement_db";

    // Create a connection to the database
    $conn = mysqli_connect($servername, $username, $password, $dbname);

    // Check for a connection error
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Prepare and execute a SQL query to search for the product by ID
    $sql = "SELECT id FROM product WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id); // "i" means integer

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if a product was found
    if ($result->num_rows > 0) {
        // Redirect to product_found.php if the product is found
        header("Location: editproductdetail.php");
        exit;
    } else {
        // Redirect to product_not_found.php if the product is not found
        echo "<div class='message'>
                    <p>Product ID not found.</p>
                    </div> <br>";
                    echo "<a href='search.php'><button class='btn'>Go Back</button>";
        exit;
    }

    // Close the database connection
    $stmt->close();
    $conn->close();
} else {
    // Display the form if it has not been submitted yet
    echo '
    <h2>Search for Product</h2>
    <form method="POST">
        <label for="product_id">Enter Product ID:</label>
        <input type="number" id="product_id" name="product_id" required>
        <button type="submit">Search</button>
    </form>
    ';
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
    
</body>
</html>
