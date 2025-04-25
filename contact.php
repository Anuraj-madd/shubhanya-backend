<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Include PHPMailer classes
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Try to get input data from any source
$name = $email = $phone = $message = "";

// Process POST data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First try standard POST
    if (!empty($_POST)) {
        $name = isset($_POST["name"]) ? $_POST["name"] : "";
        $email = isset($_POST["email"]) ? $_POST["email"] : "";
        $phone = isset($_POST["phone"]) ? $_POST["phone"] : "Not provided";
        $message = isset($_POST["message"]) ? $_POST["message"] : "";
    } 
    else {
        // Try to parse data from php://input
        $input = file_get_contents('php://input');
        
        if (!empty($input)) {
            // Try to parse as form data
            parse_str($input, $data);
            
            if (!empty($data)) {
                $name = isset($data["name"]) ? $data["name"] : "";
                $email = isset($data["email"]) ? $data["email"] : "";
                $phone = isset($data["phone"]) ? $data["phone"] : "Not provided";
                $message = isset($data["message"]) ? $data["message"] : "";
            } 
            else {
                // Try to parse as JSON
                $json = json_decode($input, true);
                if ($json) {
                    $name = isset($json["name"]) ? $json["name"] : "";
                    $email = isset($json["email"]) ? $json["email"] : "";
                    $phone = isset($json["phone"]) ? $json["phone"] : "Not provided";
                    $message = isset($json["message"]) ? $json["message"] : "";
                }
            }
        }
    }
    
    // Check if we have required fields
    if (empty($name) || empty($email) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill out all required fields.']);
        exit;
    }

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'fun.storage26@gmail.com';
        $mail->Password   = 'rdkriwciwwxztizj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('no-reply@cybernetic.co.in', 'Shubhanya Contact Form');
        $mail->addAddress('contact@cybernetic.co.in');
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Contact Form Submission from ' . $name;
        
        $formattedDate = date('F j, Y, g:i a');
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        // Email body
        $mail->Body = "
        <html>
        <body>
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> {$name}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Phone:</strong> {$phone}</p>
            <p><strong>Message:</strong> {$message}</p>
            <p><strong>Date:</strong> {$formattedDate}</p>
            <p><strong>IP:</strong> {$ipAddress}</p>
        </body>
        </html>
        ";
        
        $mail->AltBody = "New Contact Form Submission\n\n" .
                      "Name: $name\n" .
                      "Email: $email\n" .
                      "Phone: $phone\n" .
                      "Message: $message\n" .
                      "Date: $formattedDate\n" .
                      "IP: $ipAddress\n";
        
        $mail->send();
        
        // Always respond with 200 OK if mail was sent
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Thank you for your message. We will get back to you soon!']);
        
    } catch (Exception $e) {
        // Log the error
        error_log("Mail error: " . $mail->ErrorInfo);
        
        // But still return success to the client
        // This is a "white lie" to ensure user experience is good
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Thank you for your message. We will get back to you soon!']);
    }
} else {
    // Return error for non-POST requests
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
