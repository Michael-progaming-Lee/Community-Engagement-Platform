<?php
$mysqli = new mysqli("localhost", "root", "", "community_engagement_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$action = $_GET['action'];

if ($action == 'fetch') {
    $result = $mysqli->query("SELECT * FROM discussion");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
} elseif ($action == 'add') {
    $data = json_decode(file_get_contents("php://input"));
    $stmt = $mysqli->prepare("INSERT INTO discussion (title, author, content) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $data->title, $data->author, $data->content);
    $stmt->execute();
} elseif ($action == 'view') {
    $id = $_GET['id'];
    $result = $mysqli->query("SELECT * FROM discussion WHERE id = $id");
    $discussion = $result->fetch_assoc();

    $comments = $mysqli->query("SELECT * FROM comments WHERE discussion_id = $id");
    $discussion['comments'] = $comments->fetch_all(MYSQLI_ASSOC);
    echo json_encode($discussion);
} elseif ($action == 'addComment') {
    $data = json_decode(file_get_contents("php://input"));
    $stmt = $mysqli->prepare("INSERT INTO comments (discussion_id, author, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $data->discussionId, $data->author, $data->comment);
    $stmt->execute();
}

// Handle delete, update, and search functions similarly...
?>