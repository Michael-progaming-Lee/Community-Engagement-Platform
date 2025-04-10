<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Admin Login</title>
</head>

<body style="background-image: url('Background Images/Background Image.jpeg'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <header style="background: transparent; padding: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <img src="Background Images/CommUnity Logo.jpeg" alt="Company Logo" style="height: 70px;">
            <h1 style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%); color: #6699CCFFFF; background: rgba(147, 163, 178, 0.8)">CommUnity Rentals</h1>
        </div>
    </header>
    <div class="container">
        <div class="box form-box">
            <?php

            include("php/config.php");
            if (isset($_POST['submit'])) {
                $email = mysqli_real_escape_string($con, $_POST['email']);
                $password = mysqli_real_escape_string($con, $_POST['password']);

                $result = mysqli_query($con, "SELECT * FROM admin_credentials WHERE Email='$email' AND Password='$password' ") or die("Select Error");
                $row = mysqli_fetch_assoc($result);

                if (is_array($row) && !empty($row)) {
                    $_SESSION['admin_valid'] = $row['email'];
                    $_SESSION['admin_username'] = $row['username'];
                    $_SESSION['admin_id'] = $row['id'];
                } else {
                    echo "<div class='message'>
                    <p>Wrong Admin Credentials</p>
                    </div> <br>";
                    echo "<a href='admin_login.php'><button class='btn'>Go Back</button>";
                }
                if (isset($_SESSION['admin_valid'])) {
                    header("Location: admin_dashboard.php");
                }
            } else {
            ?>
                <header>Admin Login</header>
                <form action="" method="post">
                    <div class="field input">
                        <label for="email">Admin Email</label>
                        <input type="text" name="email" id="email" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="password">Admin Password</label>
                        <input type="password" name="password" id="password" autocomplete="off" required>
                    </div>

                    <div class="field">
                        <input type="submit" class="btn" name="submit" value="Login" required>
                    </div>

                    <div class="links">
                        Regular User? <a href="index.php">User Login</a>
                    </div>
                </form>
        </div>

    <?php } ?>
    </div>
</body>

</html>
