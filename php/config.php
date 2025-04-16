<?php

// Database configuration
// Check if we're on a hosted environment or local development
$is_hosted = !empty($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);

// Database configuration
if ($is_hosted) {
    $servername = "sql113.infinityfree.com";
$username = "if0_38405892";
$password = "v5Yq2SUeXZ1bHJ";
$dbname = "if0_38405892_community_engagement_db";
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
