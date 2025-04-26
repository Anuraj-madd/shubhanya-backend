<?php
// subscribe.php - Handle newsletter subscription
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    exit(0);
}



// Database connection settings
 // Your database name
require_once 'config.php';

// Initialize response array
$response = array(
    'success' => false,
    'message' => ''
);

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if email is provided
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        
        // Sanitize the email input
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            
            try {
                // Create database connection
                $conn = new mysqli($host, $username, $password, $database);
                
                // Check connection
                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }
                
                // Get IP address (optional)
                $ip_address = $_SERVER['REMOTE_ADDR'];
                
                // Prepare SQL statement to prevent SQL injection
                $stmt = $conn->prepare("INSERT INTO subscribers (email, ip_address) VALUES (?, ?)");
                $stmt->bind_param("ss", $email, $ip_address);
                
                // Execute the statement
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Thank you for subscribing to our newsletter!";
                } else {
                    // If email already exists (due to unique constraint)
                    if ($conn->errno == 1062) {
                        $response['message'] = "You are already subscribed to our newsletter.";
                    } else {
                        throw new Exception("Error: " . $stmt->error);
                    }
                }
                
                // Close statement and connection
                $stmt->close();
                $conn->close();
                
            } catch (Exception $e) {
                $response['message'] = "An error occurred: " . $e->getMessage();
            }
            
        } else {
            $response['message'] = "Please enter a valid email address.";
        }
        
    } else {
        $response['message'] = "Email address is required.";
    }
    
} else {
    $response['message'] = "Invalid request method.";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
