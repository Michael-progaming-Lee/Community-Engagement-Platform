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

            // Function to check if a column exists in a table
            function column_exists($con, $table, $column) {
                try {
                    $result = $con->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                    return ($result && $result->num_rows > 0);
                } catch (Exception $e) {
                    error_log("Error checking column: " . $e->getMessage());
                    return false;
                }
            }

            // Function to get the next available ID
            function get_next_available_id($con, $table, $id_column = 'Id') {
                try {
                    // First try to get the AUTO_INCREMENT value
                    $auto_increment_query = "SELECT `AUTO_INCREMENT` 
                                            FROM INFORMATION_SCHEMA.TABLES 
                                            WHERE TABLE_SCHEMA = DATABASE() 
                                            AND TABLE_NAME = '$table'";
                    $result = $con->query($auto_increment_query);
                    
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        if (isset($row['AUTO_INCREMENT']) && $row['AUTO_INCREMENT'] > 0) {
                            return $row['AUTO_INCREMENT'];
                        }
                    }
                    
                    // If AUTO_INCREMENT value is not available, find the max ID and add 1
                    $max_id_query = "SELECT MAX(`$id_column`) as max_id FROM `$table`";
                    $result = $con->query($max_id_query);
                    
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        return (isset($row['max_id']) && $row['max_id'] > 0) ? ($row['max_id'] + 1) : 1;
                    }
                    
                    return 1; // Default to 1 if no records exist
                } catch (Exception $e) {
                    error_log("Error getting next available ID: " . $e->getMessage());
                    return null; // Let the database handle it if we can't determine the next ID
                }
            }

            // Verify database structure before processing registration
            try {
                // Check if users table exists
                $result = $con->query("SHOW TABLES LIKE 'users'");
                if (!$result || $result->num_rows == 0) {
                    // Create users table with all required columns
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS `users` (
                        `Id` int(11) NOT NULL AUTO_INCREMENT,
                        `Username` varchar(200) NOT NULL,
                        `Email` varchar(200) NOT NULL,
                        `Age` int(11) NOT NULL,
                        `Parish` varchar(100) NOT NULL,
                        `Password` varchar(255) NOT NULL,
                        `AccountBalance` decimal(10,2) DEFAULT '0.00',
                        `status` varchar(50) DEFAULT NULL,
                        PRIMARY KEY (`Id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1";
                    
                    if (!$con->query($create_table_sql)) {
                        // Log error for debugging
                        error_log("Failed to create users table: " . $con->error);
                    }
                } else {
                    // Check for AccountBalance column
                    if (!column_exists($con, 'users', 'AccountBalance')) {
                        // Add AccountBalance column
                        $add_column_sql = "ALTER TABLE `users` ADD COLUMN `AccountBalance` decimal(10,2) DEFAULT '0.00'";
                        $con->query($add_column_sql);
                    }
                    
                    // Check if Password column is long enough for hashed passwords
                    $result = $con->query("SHOW COLUMNS FROM `users` LIKE 'Password'");
                    if ($result && $result->num_rows > 0) {
                        $column_info = $result->fetch_assoc();
                        if (strpos(strtolower($column_info['Type']), 'varchar') !== false) {
                            $length = (int)preg_replace('/[^0-9]/', '', $column_info['Type']);
                            if ($length < 255) {
                                // Alter Password column
                                $alter_column_sql = "ALTER TABLE `users` MODIFY COLUMN `Password` varchar(255) NOT NULL";
                                $con->query($alter_column_sql);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Database structure verification error: " . $e->getMessage());
                // Continue with registration process even if table verification fails
            }

            if (isset($_POST['submit'])) {
                try {
                    // Use mysqli_real_escape_string to prevent SQL injection
                    $username = isset($_POST['username']) ? mysqli_real_escape_string($con, $_POST['username']) : '';
                    $email = isset($_POST['email']) ? mysqli_real_escape_string($con, $_POST['email']) : '';
                    $age = isset($_POST['age']) ? mysqli_real_escape_string($con, $_POST['age']) : '';
                    $parish = isset($_POST['parish']) ? mysqli_real_escape_string($con, $_POST['parish']) : '';
                    $password = isset($_POST['password']) ? mysqli_real_escape_string($con, $_POST['password']) : '';
                    
                    // Validate inputs
                    if (empty($username) || empty($email) || empty($age) || empty($parish) || empty($password)) {
                        throw new Exception("All fields are required");
                    }
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email format");
                    }
                    
                    // Password is stored as plain text now
                    // No hashing is applied to the password
                    
                    // Set initial account balance to 0
                    $initial_balance = 0.00;

                    // Verifying the unique email using prepared statement
                    $verify_stmt = $con->prepare("SELECT Email FROM users WHERE Email = ?");
                    if (!$verify_stmt) {
                        throw new Exception("Database error: " . $con->error);
                    }
                    
                    $verify_stmt->bind_param("s", $email);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    
                    if ($verify_result->num_rows != 0) {
                        echo "<div class='message'>
                        <p>This email is already in use. Please try another one!</p> </div> <br>";
                        echo "<a href='javascript:self.history.back()'><button class='btn'>Go Back</button></a>";
                    } else {
                        // Insert new user with prepared statement
                        
                        // Get the next available ID
                        $next_id = get_next_available_id($con, 'users');
                        
                        // If we found a valid next ID, use an INSERT with explicit ID
                        if ($next_id !== null) {
                            $insert_stmt = $con->prepare("INSERT INTO users(Id, Username, Email, Age, Parish, Password, AccountBalance) VALUES(?, ?, ?, ?, ?, ?, ?)");
                            if ($insert_stmt) {
                                $insert_stmt->bind_param("ississd", $next_id, $username, $email, $age, $parish, $password, $initial_balance);
                            } else {
                                // Fall back to standard INSERT if prepare fails
                                $insert_stmt = $con->prepare("INSERT INTO users(Username, Email, Age, Parish, Password, AccountBalance) VALUES(?, ?, ?, ?, ?, ?)");
                                if (!$insert_stmt) {
                                    throw new Exception("Database error: " . $con->error);
                                }
                                $insert_stmt->bind_param("ssissd", $username, $email, $age, $parish, $password, $initial_balance);
                            }
                        } else {
                            // Use standard INSERT without explicit ID if we couldn't determine the next ID
                            $insert_stmt = $con->prepare("INSERT INTO users(Username, Email, Age, Parish, Password, AccountBalance) VALUES(?, ?, ?, ?, ?, ?)");
                            if (!$insert_stmt) {
                                throw new Exception("Database error: " . $con->error);
                            }
                            $insert_stmt->bind_param("ssissd", $username, $email, $age, $parish, $password, $initial_balance);
                        }
                        
                        if ($insert_stmt->execute()) {
                            // Get the newly created user ID
                            $new_user_id = $con->insert_id;
                            
                            // If insert_id is not reliable or we explicitly set the ID
                            if (!$new_user_id && $next_id !== null) {
                                $new_user_id = $next_id;
                            }
                            
                            // If we still don't have a valid ID, try to find it by email
                            if (!$new_user_id) {
                                $find_id_stmt = $con->prepare("SELECT Id FROM users WHERE Email = ? LIMIT 1");
                                if ($find_id_stmt) {
                                    $find_id_stmt->bind_param("s", $email);
                                    $find_id_stmt->execute();
                                    $id_result = $find_id_stmt->get_result();
                                    if ($id_result && $id_result->num_rows > 0) {
                                        $id_row = $id_result->fetch_assoc();
                                        $new_user_id = $id_row['Id'];
                                    }
                                    $find_id_stmt->close();
                                }
                            }
                            
                            // Log successful registration for debugging in hosted environments
                            error_log("User registration successful - Email: $email, ID: $new_user_id");
                            
                            // Add JavaScript popup for successful registration with ID
                            echo "<script>
                                alert('Registration successful! Your account has been created with ID: " . $new_user_id . "');
                                window.location.href = 'index.php';
                            </script>";
                            
                            echo "<div class='message success'>
                            <p>Registration successful! Your account ID is: " . $new_user_id . "</p> </div> <br>";
                            echo "<a href='index.php'><button class='btn'>Login Now</button></a>";
                        } else {
                            throw new Exception("Registration failed: " . $insert_stmt->error);
                        }
                        
                        $insert_stmt->close();
                    }
                    
                    $verify_stmt->close();
                } catch (Exception $e) {
                    error_log("Registration error: " . $e->getMessage());
                    echo "<div class='message'>
                    <p>Registration failed: " . htmlspecialchars($e->getMessage()) . "</p> </div> <br>";
                    echo "<a href='javascript:self.history.back()'><button class='btn'>Go Back</button></a>";
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