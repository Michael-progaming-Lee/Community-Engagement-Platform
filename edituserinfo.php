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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/edituserinfo.css">
    <title>Edit Profile</title>
    <script>
        function checkBalance() {
            var currentBalance = parseFloat(document.getElementById('current_balance').value);
            var addAmount = parseFloat(document.getElementById('add_amount').value) || 0;
            
            if (addAmount > 0) {
                var newTotal = currentBalance + addAmount;
                document.getElementById('account_balance').value = newTotal.toFixed(2);
                return confirm("Would you like to add $" + addAmount.toFixed(2) + " to your account?\nNew total will be: $" + newTotal.toFixed(2));
            }
            return true;
        }
    </script>
</head>

<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <?php include("php/header.php"); ?>
    
    <div class="container" style="background: transparent; backdrop-filter: blur(3px); border-radius: 15px; padding: 20px; margin-top: 20px;">
        <div class="box form-box">
            <?php
            if (isset($_POST['submit'])) {
                $username = $_POST['username'];
                $email = $_POST['email'];
                $age = $_POST['age'];
                $id = $_SESSION['id'];
                $parish = $_POST['parish'];
                $current_balance = $_POST['current_balance'];
                $add_amount = floatval($_POST['add_amount']);
                $new_balance = $current_balance + $add_amount;
                
                $edit_query = mysqli_query($con, "UPDATE users
                SET Username='$username', Email='$email',
                Age='$age', Parish='$parish', AccountBalance='$new_balance' WHERE Id=$id ")
                or die("error occurred");

                if ($edit_query) {
                    echo "<div class='message'>";
                    if ($add_amount > 0) {
                        echo "<p>Profile Updated! Added $" . number_format($add_amount, 2) . " to your account.</p>";
                        echo "<p>New balance: $" . number_format($new_balance, 2) . "</p>";
                    } else {
                        echo "<p>Profile Updated!</p>";
                    }
                    echo "</div> <br>";
                    echo "<a href='home.php'><button class='btn'>Go Home</button>";
                }
            } else {
                $id = $_SESSION['id'];
                $query = mysqli_query($con, "SELECT*FROM users WHERE Id=$id ");

                while ($result = mysqli_fetch_assoc($query)) {
                    $res_Uname = $result['Username'];
                    $res_Email = $result['Email'];
                    $res_Age = $result['Age'];
                    $res_Parish = $result['Parish'];
                    $res_Balance = $result['AccountBalance'];
                }
            ?>
                <header>Change Profile</header>
                <form action="" method="post" onsubmit="return checkBalance();">
                    <div class="field input">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo $res_Uname; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="email">Email</label>
                        <input type="text" name="email" id="email" value="<?php echo $res_Email; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="age">Age</label>
                        <input type="number" name="age" id="age" value="<?php echo $res_Age; ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="parish">Parish</label>
                        <select name="parish" id="parish" required>
                            <option value="<?php echo $res_Parish; ?>" autocomplete="off" required> <?php echo $res_Parish; ?> </option>
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
                        <label for="account_balance">Current Balance: $<?php echo number_format($res_Balance, 2); ?></label>
                        <input type="hidden" id="current_balance" name="current_balance" value="<?php echo $res_Balance; ?>">
                        <input type="hidden" id="account_balance" name="account_balance" value="<?php echo $res_Balance; ?>">
                        <label for="add_amount">Add Amount:</label>
                        <input type="number" step="0.01" name="add_amount" id="add_amount" value="0" min="0" autocomplete="off">
                    </div>

                    <div class="field">
                        <input type="submit" class="btn" name="submit" value="Update" required>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</body>

</html>