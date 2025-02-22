<?php
include("config.php");

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

// Fetch and display users with search condition
$query = "SELECT * FROM users $searchCondition ORDER BY Id DESC";
$result = mysqli_query($con, $query);

if (!$result) {
    echo "<div class='error'>Error loading users: " . mysqli_error($con) . "</div>";
    exit();
}

if (mysqli_num_rows($result) > 0) {
    echo "<div style='overflow-x: auto;'>";
    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
            <thead style='background: rgba(102, 153, 204, 0.2);'>
                <tr>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>ID</th>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Username</th>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Email</th>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Age</th>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Parish</th>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #6699CC;'>Actions</th>
                </tr>
            </thead>
            <tbody>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr style='border-bottom: 1px solid rgba(102, 153, 204, 0.2);'>
                <td style='padding: 12px;'>" . htmlspecialchars($row['Id']) . "</td>
                <td style='padding: 12px;'>" . htmlspecialchars($row['Username']) . "</td>
                <td style='padding: 12px;'>" . htmlspecialchars($row['Email']) . "</td>
                <td style='padding: 12px;'>" . htmlspecialchars($row['Age']) . "</td>
                <td style='padding: 12px;'>" . htmlspecialchars($row['Parish']) . "</td>
                <td style='padding: 12px;'>
                    <button onclick='editUser(" . htmlspecialchars($row['Id']) . ")' style='background: #6699CC; color: white; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer; border-radius: 3px;'>Edit</button>
                    <button onclick='deleteUser(" . htmlspecialchars($row['Id']) . ")' style='background: #ff4444; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;'>Delete</button>
                </td>
            </tr>";
    }
    echo "</tbody></table></div>";
} else {
    echo "<div style='text-align: center; padding: 20px; background: rgba(255, 255, 255, 0.1); border-radius: 8px;'>
            <p style='margin: 0; color: #666;'>No users found.</p>
          </div>";
}
?>
