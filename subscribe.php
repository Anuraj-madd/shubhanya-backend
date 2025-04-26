<?php
// subscribe.php - Handle newsletter subscription
// Enable CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get raw input for debugging
$raw_input = file_get_contents('php://input');

// Initialize response array
$response = array(
    'success' => false,
    'message' => '',
    'debug' => array(
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
        'raw_input' => $raw_input,
        'post_data' => $_POST,
    )
);

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check for raw input and parse if needed
    if (empty($_POST) && !empty($raw_input)) {
        parse_str($raw_input, $parsed_data);
        if (!empty($parsed_data['email'])) {
            $_POST['email'] = $parsed_data['email'];
        }
    }
    
    // Check if email is provided
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        
        // Sanitize the email input
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            
            try {
                // For testing purposes, just return success without DB interaction
                $response['success'] = true;
                $response['message'] = "Thank you for subscribing to our newsletter!";
                
                /* 
                // Uncomment this block when your database is properly set up
                // Database connection settings
                require_once 'config.php';
                
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
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
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
                        throw new Exception("Execute error: " . $stmt->error . " (SQL errno: " . $conn->errno . ")");
                    }
                }
                
                // Close statement and connection
                $stmt->close();
                $conn->close();
                */
                
            } catch (Exception $e) {
                $response['message'] = "Database error";
                $response['debug']['error'] = $e->getMessage();
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
echo json_encode($response);
exit;
?>
