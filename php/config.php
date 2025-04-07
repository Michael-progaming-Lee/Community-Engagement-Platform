<?php

// Database configuration
// Check if we're on a hosted environment or local development
$is_hosted = !empty($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);

if ($is_hosted) {
    // Hosted environment settings
    // IMPORTANT: Update these values with your hosting provider's database credentials
    $servername = "localhost"; // Often remains localhost on shared hosting
    $username = "your_db_username"; // Change to your hosting database username
    $password = "your_db_password"; // Change to your hosting database password
    $dbname = "your_db_name"; // Change to your hosting database name
} else {
    // Local development settings
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "community_engagement_db";
}

// Create connection
try {
    $con = mysqli_connect($servername, $username, $password, $dbname);
    
    // Check connection
    if (!$con) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
} catch (Exception $e) {
    // Log error to file for debugging on hosted environments
    error_log("Database connection error: " . $e->getMessage(), 0);
    die("Connection error: " . $e->getMessage());
}
