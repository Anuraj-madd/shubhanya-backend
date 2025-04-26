<?php
// subscribe.php - Handle newsletter subscription

// Enable comprehensive CORS for debugging
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
        'content_type' => isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'Not set',
        'raw_input' => $raw_input
    )
);

// Process the request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Parse raw input if $_POST is empty
    if (empty($_POST) && !empty($raw_input)) {
        parse_str($raw_input, $parsed_data);
        if (!empty($parsed_data)) {
            $_POST = $parsed_data;
        }
    }
    
    // Add POST data to debug info
    $response['debug']['post_data'] = $_POST;
    
    // Check if email is provided
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        // Sanitize the email input
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                // For testing: Just return success
                $response['success'] = true;
                $response['message'] = "Thank you for subscribing to our newsletter!";
                
                /*
                // Database connection and insertion
                // Make sure your config.php exists and has the correct credentials
                require_once 'config.php';
                
                if (!isset($host) || !isset($username) || !isset($password) || !isset($database)) {
                    throw new Exception("Database configuration is incomplete");
                }
                
                // Create database connection
                $conn = new mysqli($host, $username, $password, $database);
                
                // Check connection
                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }
                
                // Get IP address
                $ip_address = $_SERVER['REMOTE_ADDR'];
                
                // Prepare SQL statement
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
                        $response['success'] = true; // Still consider this a success from the user perspective
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
    $response['debug']['expected'] = "POST";
}

// Return JSON response
echo json_encode($response);
exit;
?>
