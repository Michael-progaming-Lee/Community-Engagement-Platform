<?php
if (isset($_POST['qr_image']) && isset($_POST['filename'])) {
    $qr_image = $_POST['qr_image'];
    $filename = $_POST['filename'];
    
    // Remove the data URI header
    $qr_image = str_replace('data:image/png;base64,', '', $qr_image);
    $qr_image = str_replace(' ', '+', $qr_image);
    
    // Decode base64 image
    $image_data = base64_decode($qr_image);
    
    // Save the image
    if (file_put_contents($filename, $image_data)) {
        echo "QR code saved successfully";
    } else {
        echo "Error saving QR code";
    }
} else {
    echo "Invalid request";
}
?>
