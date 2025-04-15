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
    <title>Login</title>
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

                // Use prepared statement for login
                $stmt = $con->prepare("SELECT * FROM users WHERE Email = ?");
                if (!$stmt) {
                    echo "<div class='message error'>
                    <p>Database error: " . $con->error . "</p> </div> <br>";
                    echo "<a href='index.php'><button class='btn'>Go Back</button>";
                    exit();
                }
                
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();

                if (is_array($row) && !empty($row)) {
                    $login_successful = false;
                    
                    // First try password_verify for hashed passwords (new method)
                    if (password_verify($password, $row['Password'])) {
                        $login_successful = true;
                    }                    
                    else if ($password === $row['Password']) {
                        $login_successful = true;
                        
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $update_stmt = $con->prepare("UPDATE users SET Password = ? WHERE Id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param("si", $hashed_password, $row['Id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                            // Log the password upgrade
                            error_log("Password upgraded to hashed version for user ID: " . $row['Id']);
                        }
                    }
                    
                    if ($login_successful) {
                        // Check if user is banned
                        if (isset($row['status']) && $row['status'] === 'banned') {
                            echo "<div class='message error' style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
                            <p><strong>Account Suspended</strong></p>
                            <p>Your account has been suspended by an administrator. Please contact support for more information.</p>
                            </div> <br>";
                            echo "<a href='index.php'><button class='btn'>Go Back</button>";
                            
                            // Add JavaScript popup alert for banned users
                            echo "<script>
                                window.onload = function() {
                                    alert('Your account has been suspended by an administrator. Please contact support for more information.');
                                }
                            </script>";
                        } else {
                            $_SESSION['valid'] = $row['Email'];
                            $_SESSION['username'] = $row['Username'];
                            $_SESSION['age'] = $row['Age'];
                            $_SESSION['id'] = $row['Id'];
                            
                            // Also store account balance in session for easy access
                            $_SESSION['account_balance'] = isset($row['AccountBalance']) ? $row['AccountBalance'] : 0;
                            
                            // Redirect after setting session variables
                            header("Location: home.php");
                            exit();
                        }
                    } else {
                        echo "<div class='message'>
                        <p>Wrong Email or Password</p>
                        </div> <br>";
                        echo "<a href='index.php'><button class='btn'>Go Back</button>";
                    }
                } else {
                    echo "<div class='message'>
                    <p>Wrong Email or Password</p>
                    </div> <br>";
                    echo "<a href='index.php'><button class='btn'>Go Back</button>";
                }
                
                $stmt->close();
            } else {
            ?>
                <header>Login</header>
                <form action="" method="post">
                    <div class="field input">
                        <label for="email">Email</label>
                        <input type="text" name="email" id="email" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" autocomplete="off" required>
                    </div>

                    <div class="field">
                        <input type="submit" class="btn" name="submit" value="Login" required>
                    </div>

                    <div class="links">
                        Don't have account? <a href="register.php">Sign Up Now</a>
                    </div>
                    <div class="links">
                        Admin? <a href="admin_login.php">Sign In</a>
                    </div>
                </form>
        </div>

    <?php } ?>
    </div>
</body>

</html>