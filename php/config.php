<?php

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "community_engagement_db";

// Create connection
try {
    $con = mysqli_connect($servername, $username, $password, $dbname);
    
    // Check connection
    if (!$con) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
} catch (Exception $e) {
    die("Connection error: " . $e->getMessage());
}
