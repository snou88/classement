<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// In a production environment, use environment variables or a secure configuration file
// This is just for demonstration purposes
$validPassword = 'm'; // Replace with a strong password

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if password was provided
if (!isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Verify the password
// In a real application, use password_hash() and password_verify() for secure password handling
$isValid = ($data['password'] === $validPassword);

if ($isValid) {
    // Password is correct
    echo json_encode([
        'success' => true,
        'message' => 'Password verified successfully'
    ]);
} else {
    // Password is incorrect
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect password'
    ]);
}
?>
