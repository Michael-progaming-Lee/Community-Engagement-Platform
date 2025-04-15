<?php
session_start();

include("php/config.php");
// Check if admin is logged in
if (!isset($_SESSION['admin_valid'])) {
    header("Location: admin_login.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('User ID is required'); window.location.href='admin_dashboard.php';</script>";
    exit;
}

$userId = intval($_GET['id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/edituserinfo.css">
    <title>Admin - Edit User</title>
    <script>
        function checkBalance() {
            var currentBalance = parseFloat(document.getElementById('current_balance').value);
            var newBalance = parseFloat(document.getElementById('account_balance').value) || 0;
            
            if (newBalance !== currentBalance) {
                var change = newBalance - currentBalance;
                var action = change > 0 ? "add" : "remove";
                var amount = Math.abs(change);
                
                return confirm("You are about to " + action + " $" + amount.toFixed(2) + 
                              " " + (action === "add" ? "to" : "from") + " this user's account.\n" +
                              "New balance will be: $" + newBalance.toFixed(2) + 
                              "\n\nDo you want to proceed?");
            }
            return true;
        }
    </script>
</head>

<body style="background-image: url('Background Images/Home_Background.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <header style="background: transparent; padding: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <img src="Background Images/CommUnity Logo.jpeg" alt="Company Logo" style="height: 70px;">
            <h1 style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%); color: #6699CCFFFF; background: rgba(147, 163, 178, 0.8)">CommUnity Rentals - Admin</h1>
        </div>
    </header>
    
    <div class="container" style="background: transparent; backdrop-filter: blur(3px); border-radius: 15px; padding: 20px; margin-top: 20px;">
        <div class="box form-box">
            <?php
            if (isset($_POST['submit'])) {
                $username = mysqli_real_escape_string($con, $_POST['username']);
                $email = mysqli_real_escape_string($con, $_POST['email']);
                $age = intval($_POST['age']);
                $parish = mysqli_real_escape_string($con, $_POST['parish']);
                $account_balance = floatval($_POST['account_balance']);
                $userId = intval($_POST['user_id']);
                
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo "<div class='message'><p>Invalid email format</p></div> <br>";
                    echo "<a href='admin_edit_user.php?id=$userId'><button class='btn'>Go Back</button>";
                    exit;
                }
                
                // Check if email exists for other users
                $check_email = mysqli_query($con, "SELECT Id FROM users WHERE Email='$email' AND Id != $userId");
                if (mysqli_num_rows($check_email) > 0) {
                    echo "<div class='message'><p>Email already in use by another user</p></div> <br>";
                    echo "<a href='admin_edit_user.php?id=$userId'><button class='btn'>Go Back</button>";
                    exit;
                }
                
                $edit_query = mysqli_query($con, "UPDATE users
                SET Username='$username', Email='$email',
                Age='$age', Parish='$parish', AccountBalance='$account_balance' WHERE Id=$userId") 
                or die("Error occurred: " . mysqli_error($con));

                if ($edit_query) {
                    echo "<div class='message'>";
                    echo "<p>User Updated Successfully!</p>";
                    echo "</div> <br>";
                    echo "<a href='admin_dashboard.php'><button class='btn'>Back to Dashboard</button></a>";
                }
            } else {
                // Get user data
                $query = mysqli_query($con, "SELECT * FROM users WHERE Id=$userId");
                
                if (mysqli_num_rows($query) == 0) {
                    echo "<div class='message'><p>User not found</p></div> <br>";
                    echo "<a href='admin_dashboard.php'><button class='btn'>Back to Dashboard</button>";
                    exit;
                }
                
                $user = mysqli_fetch_assoc($query);
            ?>
                <header>Edit User: <?php echo htmlspecialchars($user['Username']); ?></header>
                <form action="" method="post" onsubmit="return checkBalance();">
                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                    
                    <div class="field input">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['Username']); ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['Email']); ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="age">Age</label>
                        <input type="number" name="age" id="age" value="<?php echo intval($user['Age']); ?>" autocomplete="off" required>
                    </div>

                    <div class="field input">
                        <label for="parish">Parish</label>
                        <select name="parish" id="parish" required>
                            <option value="<?php echo htmlspecialchars($user['Parish']); ?>"><?php echo htmlspecialchars($user['Parish']); ?></option>
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
                        <label for="account_balance">Account Balance ($)</label>
                        <input type="hidden" id="current_balance" name="current_balance" value="<?php echo floatval($user['AccountBalance']); ?>">
                        <input type="number" step="0.01" id="account_balance" name="account_balance" value="<?php echo floatval($user['AccountBalance']); ?>" autocomplete="off" required>
                    </div>

                    <div class="field">
                        <input type="submit" class="btn" name="submit" value="Update User" required>
                    </div>
                </form>
                
                <div class="field" style="margin-top: 20px;">
                    <a href="admin_dashboard.php"><button class="btn" style="background-color: #6c757d;">Cancel</button></a>
                </div>
            <?php } ?>
        </div>
    </div>
</body>

</html>
