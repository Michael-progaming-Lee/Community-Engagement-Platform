<?php
session_start();

// Include database connection
include("php/config.php");

// Handle cart count API request
if (isset($_GET['action']) && $_GET['action'] === 'get_cart_count') {
    if (!isset($_SESSION['id'])) {
        echo json_encode(['count' => 0]);
        exit;
    }

    $user_id = $_SESSION['id'];

    // Get total number of items in cart
    $query = "SELECT COUNT(*) as item_count FROM users_cart WHERE UserID = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    echo json_encode(['count' => (int)$data['item_count']]);
    exit;
}

// Handle filtered count API request
if (isset($_GET['action']) && $_GET['action'] === 'get_filtered_count') {
    header('Content-Type: application/json');
    
    // Initialize search condition
    $searchCondition = "";
    if (isset($_GET['search']) && isset($_GET['criteria'])) {
        $search = mysqli_real_escape_string($con, $_GET['search']);
        $criteria = mysqli_real_escape_string($con, $_GET['criteria']);
        
        switch ($criteria) {
            case 'id':
                $searchCondition = "WHERE Id = '$search'";
                break;
            case 'username':
                $searchCondition = "WHERE Username LIKE '%$search%'";
                break;
            case 'email':
                $searchCondition = "WHERE Email LIKE '%$search%'";
                break;
        }
    }
    
    // Get count of filtered results
    $query = "SELECT COUNT(*) as count FROM users $searchCondition";
    $result = mysqli_query($con, $query);
    
    if ($result) {
        $count = mysqli_fetch_assoc($result)['count'];
        echo json_encode(['count' => $count]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error counting users: ' . mysqli_error($con)]);
    }
    exit;
}

// Debug database connection and table
if ($con) {
    $test_query = "SHOW TABLES LIKE 'users'";
    $test_result = mysqli_query($con, $test_query);
    if (mysqli_num_rows($test_result) == 0) {
        // Users table doesn't exist, create it
        $create_table_query = "CREATE TABLE IF NOT EXISTS users(
            Id int PRIMARY KEY AUTO_INCREMENT,
            Username varchar(200),
            Email varchar(200),
            Age int,
            Parish varchar(50),
            Password varchar(200)
        )";
        mysqli_query($con, $create_table_query);
        
        // Insert a test user if table is empty
        $insert_test_user = "INSERT INTO users (Username, Email, Age, Parish, Password) 
                            VALUES ('Test User', 'test@example.com', 25, 'Test Parish', 'password123')";
        mysqli_query($con, $insert_test_user);
    }
}

// Check if admin is logged in
if (!isset($_SESSION['admin_valid'])) {
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Admin Dashboard</title>
    <style>
        .sidebar {
            width: 250px;
            min-height: fit-content;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 90px;
            color: white;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 10px 0;
        }
        
        .nav-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
            border-left: 4px solid transparent;
            user-select: none;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
            transform: translateX(5px);
        }
        
        .nav-item:active {
            transform: translateX(3px);
            background: rgba(102, 153, 204, 0.4);
        }
        
        .nav-item.active {
            background: rgba(102, 153, 204, 0.3);
            border-left: 4px solid #6699CC;
            font-weight: bold;
        }

        .nav-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 4px;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 1.2em;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .content-section {
            display: none;
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .content-section.active {
            display: block;
        }
        
        .welcome-bar {
            background: linear-gradient(to right, #6699CC, #7aa7d3);
            color: white;
            padding: 15px 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body style="background-image: url('Background Images/Background Image.png'); background-size: cover; background-position: top center; background-repeat: no-repeat; background-attachment: fixed; min-height: 100vh; margin: 0; padding: 0; width: 100%; height: 100%;">
    <header style="background: transparent; padding: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <img src="Background Images/CommUnity Logo.png" alt="Company Logo" style="height: 70px;">
            <h1 style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%); color: #6699CCFFFF; background: rgba(147, 163, 178, 0.8)">Admin Dashboard</h1>
            <a href="php/logout.php" style="text-decoration: none; padding: 10px 20px; background-color: #ff4444; color: white; border-radius: 5px;">Logout</a>
        </div>
    </header>

    <!-- Navigation Sidebar -->
    <nav class="sidebar">
        <div class="nav-item active" data-section="dashboard">
            <span class="nav-icon">📊</span>
            Dashboard
        </div>
        <div class="nav-item" data-section="users">
            <span class="nav-icon">👥</span>
            Manage Users
        </div>
        <div class="nav-item" data-section="properties">
            <span class="nav-icon">📦</span>
            View Existing Products
        </div>
        <div class="nav-item" data-section="reports">
            <span class="nav-icon">📈</span>
            Reports
        </div>
        <div class="nav-item" data-section="settings">
            <span class="nav-icon">⚙️</span>
            Settings
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Dashboard Section -->
        <section id="dashboard" class="content-section active">
            <h2>Dashboard Overview</h2>
            <div class="dashboard-content">
                <h2>Welcome, <?php echo $_SESSION['admin_username']; ?>!</h2>
                
                <!-- Welcome Message Bar -->
                <div id="welcome-bar" class="welcome-bar" style="
                    background: linear-gradient(to right, #6699CC, #7aa7d3);
                    color: white;
                    padding: 15px 25px;
                    margin: 20px 0;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <p style="margin: 0; font-size: 15px; line-height: 1.6;">
                        We're glad to have you on board! Manage Users, track rental transactions, and oversee operations efficiently all in one place. 
                        Stay organized, streamline tasks, and keep CommUnity Rentals running smoothly.
                    </p>
                </div>

                <div class="dashboard-stats">
                    <!-- Add dashboard statistics here -->
                </div>
            </div>
        </section>

        <!-- Users Section -->
        <section id="users" class="content-section">
            <h2>Manage Users</h2>
            
            <!-- Top Bar with Stats and Search -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <!-- Compact Stats Card -->
                <div id="totalUsersCard" style="background: rgba(102, 153, 204, 0.2); padding: 10px 20px; border-radius: 8px; display: flex; align-items: center;">
                    <span style="font-weight: bold; color: #6699CC; margin-right: 10px;">Total Users:</span>
                    <span style="font-size: 18px; color: #333;" id="totalUsersCount">
                        <?php 
                        $countQuery = "SELECT COUNT(*) as total FROM users";
                        $countResult = mysqli_query($con, $countQuery);
                        echo mysqli_fetch_assoc($countResult)['total']; 
                        ?>
                    </span>
                </div>

                <!-- Search Form -->
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select id="searchCriteria" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        <option value="id">Search by ID</option>
                        <option value="username">Search by Name</option>
                        <option value="email">Search by Email</option>
                    </select>
                    <input type="text" id="searchInput" placeholder="Enter search term..." 
                           style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
                    <button onclick="searchUsers()" 
                            style="background: #6699CC; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                        Search
                    </button>
                    <button onclick="resetSearch()" 
                            style="background: #666; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                        Reset
                    </button>
                </div>
            </div>

            <div class="user-list" id="userTableContainer">
                <?php
                // Initialize search condition
                $searchCondition = "";
                if (isset($_GET['search']) && isset($_GET['criteria'])) {
                    $search = mysqli_real_escape_string($con, $_GET['search']);
                    $criteria = mysqli_real_escape_string($con, $_GET['criteria']);
                    
                    switch ($criteria) {
                        case 'id':
                            $searchCondition = "WHERE Id = '$search'";
                            break;
                        case 'username':
                            $searchCondition = "WHERE Username LIKE '%$search%'";
                            break;
                        case 'email':
                            $searchCondition = "WHERE Email LIKE '%$search%'";
                            break;
                    }
                }

                include('php/load_users.php');
                ?>
            </div>

            <!-- Add User Button -->
            <div style="margin-top: 20px;">
                <button onclick="addNewUser()" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px;">
                    Add New User
                </button>
            </div>
        </section>

        <!-- Properties Section -->
        <section id="properties" class="content-section">
            <h2>View Existing Products</h2>
            <div class="property-list">
                <!-- Add property management content here -->
            </div>
        </section>

        <!-- Reports Section -->
        <section id="reports" class="content-section">
            <h2>Reports</h2>
            <div class="reports-content">
                <!-- Add reports content here -->
            </div>
        </section>

        <!-- Settings Section -->
        <section id="settings" class="content-section">
            <h2>Settings</h2>
            <div class="settings-content">
                <!-- Add settings content here -->
            </div>
        </section>
    </div>

    <script>
        // Get all navigation items and content sections
        const navItems = document.querySelectorAll('.nav-item');
        const contentSections = document.querySelectorAll('.content-section');

        // Add click event listeners to navigation items
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                // Remove active class from all navigation items and content sections
                navItems.forEach(nav => nav.classList.remove('active'));
                contentSections.forEach(section => section.classList.remove('active'));

                // Add active class to clicked navigation item
                item.classList.add('active');

                // Show corresponding content section
                const sectionId = item.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');
                
                // Show/hide welcome bar based on dashboard selection
                const welcomeBar = document.getElementById('welcome-bar');
                if (welcomeBar) {
                    welcomeBar.style.display = sectionId === 'dashboard' ? 'block' : 'none';
                }
            });
        });

        // Function to add new user
        function addNewUser() {
            // Add new user functionality
            alert('Add new user functionality will be implemented here');
        }

        // Function to edit user
        function editUser(userId) {
            // Edit user functionality
            alert('Edit user functionality will be implemented for user ID: ' + userId);
        }

        // Function to delete user
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                // Delete user functionality
                alert('Delete user functionality will be implemented for user ID: ' + userId);
            }
        }

        async function searchUsers() {
            const searchInput = document.getElementById('searchInput').value;
            const searchCriteria = document.getElementById('searchCriteria').value;
            
            try {
                // Load filtered users
                const response = await fetch(`php/load_users.php?search=${encodeURIComponent(searchInput)}&criteria=${searchCriteria}`);
                if (!response.ok) throw new Error('Network response was not ok');
                const html = await response.text();
                document.getElementById('userTableContainer').innerHTML = html;

                // Update total count for filtered results
                const countResponse = await fetch(`admin_dashboard.php?action=get_filtered_count&search=${encodeURIComponent(searchInput)}&criteria=${searchCriteria}`);
                if (!countResponse.ok) throw new Error('Network response was not ok');
                const countData = await countResponse.json();
                document.getElementById('totalUsersCount').textContent = countData.count;
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading users. Please try again.');
            }
        }

        async function resetSearch() {
            document.getElementById('searchInput').value = '';
            try {
                // Reset user table
                const response = await fetch('php/load_users.php');
                if (!response.ok) throw new Error('Network response was not ok');
                const html = await response.text();
                document.getElementById('userTableContainer').innerHTML = html;

                // Reset total count
                const countResponse = await fetch('admin_dashboard.php?action=get_filtered_count');
                if (!countResponse.ok) throw new Error('Network response was not ok');
                const countData = await countResponse.json();
                document.getElementById('totalUsersCount').textContent = countData.count;
            } catch (error) {
                console.error('Error:', error);
                alert('Error resetting search. Please try again.');
            }
        }

        // Add enter key support for search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchUsers();
            }
        });
    </script>
</body>
</html>
