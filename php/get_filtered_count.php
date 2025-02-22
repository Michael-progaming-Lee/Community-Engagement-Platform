<?php
header('Content-Type: application/json');
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
?>
