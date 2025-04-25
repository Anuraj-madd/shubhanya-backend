<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Contact form processor using PHPMailer

// Include PHPMailer classes
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Define variables and set to empty values
$name = $email = $phone = $message = "";
$formError = false;

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the POST data
    $input = file_get_contents('php://input');
    
    // Check if data is URL-encoded (from form submission)
    if (!empty($_POST)) {
        $postData = $_POST;
    } 
    // Try to parse JSON data if it exists
    else if (!empty($input)) {
        parse_str($input, $postData);
        if (empty($postData)) {
            // If parse_str failed, try JSON
            $jsonData = json_decode($input, true);
            if ($jsonData) {
                $postData = $jsonData;
            }
        }
    } else {
        $postData = [];
    }
    
    // Debug - log what we received
    error_log("Received POST data: " . print_r($postData, true));
    
    // Validate and sanitize inputs
    if (empty($postData["name"])) {
        $formError = true;
        error_log("Name is empty");
    } else {
        $name = filter_var($postData["name"], FILTER_SANITIZE_STRING);
    }
    
    if (empty($postData["email"])) {
        $formError = true;
        error_log("Email is empty");
    } else {
        $email = filter_var($postData["email"], FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $formError = true;
            error_log("Email is invalid: $email");
        }
    }
    
    $phone = !empty($postData["phone"]) ? filter_var($postData["phone"], FILTER_SANITIZE_STRING) : "Not provided";
    
    if (empty($postData["message"])) {
        $formError = true;
        error_log("Message is empty");
    } else {
        $message = filter_var($postData["message"], FILTER_SANITIZE_STRING);
    }
    
    // If no errors, proceed with sending email
    if (!$formError) {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        try {
            // Server settings - uncomment and configure these for SMTP if required
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';         // SMTP server
            $mail->SMTPAuth   = true;                       // Enable SMTP authentication
            $mail->Username   = 'fun.storage26@gmail.com';   // SMTP username
            $mail->Password   = 'rdkriwciwwxztizj';      // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
            $mail->Port       = 587;                        // TCP port to connect to

            // Recipients
            $mail->setFrom('no-reply@cybernetic.co.in', 'Shubhanya Contact Form');
            $mail->addAddress('contact@cybernetic.co.in');  // Add a recipient
            $mail->addReplyTo($email, $name);
            
            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'New Contact Form Submission from ' . $name;
            
            // Format date outside of the heredoc to avoid parsing issues
            $formattedDate = date('F j, Y, g:i a');
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            
            // Create professional HTML email template
            $htmlBody = <<<EOD
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #dddddd;
            border-radius: 5px;
        }
        .header {
            background-color: #005F73;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
            background-color: #f9f9f9;
        }
        .footer {
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #777777;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #dddddd;
        }
        .label {
            font-weight: bold;
            width: 30%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Contact Form Submission</h2>
        </div>
        <div class="content">
            <p>A new message has been submitted through the Shubhanya contact form.</p>
            
            <table>
                <tr>
                    <td class="label">Name:</td>
                    <td>{$name}</td>
                </tr>
                <tr>
                    <td class="label">Email:</td>
                    <td>{$email}</td>
                </tr>
                <tr>
                    <td class="label">Phone:</td>
                    <td>{$phone}</td>
                </tr>
                <tr>
                    <td class="label">Message:</td>
                    <td>{$message}</td>
                </tr>
                <tr>
                    <td class="label">Date Submitted:</td>
                    <td>{$formattedDate}</td>
                </tr>
                <tr>
                    <td class="label">IP Address:</td>
                    <td>{$ipAddress}</td>
                </tr>
            </table>
        </div>
        <div class="footer">
            <p>This is an automated message from the Shubhanya website contact form.</p>
        </div>
    </div>
</body>
</html>
EOD;
            
            $mail->Body = $htmlBody;
            
            // Plain text alternative for email clients that don't support HTML
            $textBody = "NEW CONTACT FORM SUBMISSION\n\n" .
                        "Name: $name\n" .
                        "Email: $email\n" .
                        "Phone: $phone\n" .
                        "Message: $message\n" .
                        "Date: $formattedDate\n" .
                        "IP: $ipAddress\n";
            
            $mail->AltBody = $textBody;
            
            $mail->send();
            
            error_log("Email sent successfully to contact@cybernetic.co.in");
            
            // Return JSON success response
            echo json_encode(['success' => true, 'message' => 'Thank you for your message. We will get back to you soon!']);
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            // Return JSON error response
            echo json_encode(['success' => false, 'message' => "Message could not be sent. Error: {$mail->ErrorInfo}"]);
        }
    } else {
        error_log("Form validation failed");
        // Return JSON validation error response
        echo json_encode(['success' => false, 'message' => 'Please fill out all required fields correctly.']);
    }
    
    exit; // Prevent further execution
} else {
    // Not a POST request
    error_log("Not a POST request: " . $_SERVER["REQUEST_METHOD"]);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
