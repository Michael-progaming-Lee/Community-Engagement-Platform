<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/register.css">
    <title>Sign Up</title>
</head>

<body style="background-image: url('Background Images/Background Image.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
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
                $username = $_POST['username'];
                $email = $_POST['email'];
                $age = $_POST['age'];
                $parish = $_POST['parish'];
                $password = $_POST['password'];

                //verifying the unique email

                $verify_query = mysqli_query($con, "SELECT Email FROM users WHERE Email='$email'");

                if (mysqli_num_rows($verify_query) != 0) {
                    echo "<div class='message'>
                    <p>This email is used, Try another One Please!</p> </div> <br>";
                    echo "<a href='javascript:self.history.back()'><button class='btn'>Go Back</button></a>";
                } else {

                    mysqli_query($con, "INSERT INTO users(Username,Email,Age,Parish,Password) VALUES('$username','$email','$age','$parish','$password')") or die("Error Occured");

                    echo "<div class='message'>
                    <p>Registration successfully!</p> </div> <br>";
                    echo "<a href='index.php'><button class='btn'>Login Now</button></a>";
                }
            } else {
            ?>
                <header>Sign Up</header>
                <form action="" method="post">
                    <div class="field input">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="email">Email</label>
                        <input type="text" name="email" id="email" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="age">Age</label>
                        <input type="number" name="age" id="age" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="parish">Parish</label>
                        <select name="parish" id="parish" required>
                            <option value="">Select a parish</option>
                            <option value="Kingston">Kingston</option>
                            <option value="St. Andrew">St. Andrew</option>
                            <option value="St. Catherine">St. Catherine</option>
                            <option value="Clarendon">Clarendon</option>
                            <option value="Manchester">Manchester</option>
                            <option value="St. Elizabeth">St. Elizabeth</option>
                            <option value="Westmoreland">Westmoreland</option>
                            <option value="Hanover">Hanover</option>
                            <option value="St. James">St. James</option>
                            <option value="Trelawny">Trelawny</option>
                            <option value="St. Ann">St. Ann</option>
                            <option value="St. Mary">St. Mary</option>
                            <option value="Portland">Portland</option>
                            <option value="St. Thomas">St. Thomas</option>
                        </select>
                    </div>

                    <div class="field input">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" autocomplete="off" required>
                    </div>

                    <div class="field">
                        <input type="submit" class="btn" name="submit" value="Register" required>
                    </div>

                    <div class="links">
                        Already a member? <a href="index.php">Sign In</a>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</body>

</html>