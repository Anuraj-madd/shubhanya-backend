<?php
// Enable CORS for development
header("Access-Control-Allow-Origin: *");
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

// Use Composer autoloader for PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database connection
function connectDB() {
    require_once 'config.php';
    
    return $conn;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Log incoming request for debugging
error_log("Incoming request: " . json_encode($data));

// Check if mode is set
if (!isset($data['mode'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}

// Connect to database
$conn = connectDB();
if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Function to generate random OTP
function generateOTP() {
    return sprintf("%06d", mt_rand(0, 999999)); // Always returns 6 digits with leading zeros
}

// Function to send OTP email using PHPMailer
function sendOTPEmail($email, $otp, $firstName = '', $lastName = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings - MODIFY THESE WITH YOUR EMAIL PROVIDER DETAILS
        $mail->isSMTP();
        $mail->Host       = getenv("smtp_host");         // SMTP server
        $mail->SMTPAuth   = true;                       // Enable SMTP authentication
        $mail->Username   = getenv("smtp_username");   // SMTP username
        $mail->Password   = getenv("smtp_password");      // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                        // TCP port to connect to
        
        // Optional for debugging
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Recipients
        $mail->setFrom('no-reply@cybernetic.co.in', 'Shubhanya Enterprises');
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Password Reset OTP for Your Account";
        $mail->Body    = getEmailTemplate($email, $otp, $firstName, $lastName);
        
        $mail->send();
        error_log("Email sent successfully to {$email} with OTP: {$otp}");
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to get email template
function getEmailTemplate($email, $otp, $firstName = '', $lastName = '') {
    // Determine user name for greeting
    $userName = trim($firstName) != '' ? $firstName : explode('@', $email)[0];
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset OTP</title>
        <style type="text/css">
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                background-color: #f5f7fa;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
            }
            .header {
                background-color: #1CC5DC;
                padding: 20px;
                text-align: center;
                color: white;
            }
            .content {
                padding: 30px 20px;
                color: #333333;
            }
            .footer {
                background-color: #f5f7fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #778DA9;
            }
            .otp-box {
                background-color: #f5f7fa;
                padding: 20px;
                text-align: center;
                margin: 20px 0;
                border-radius: 5px;
                font-size: 24px;
                font-weight: bold;
                letter-spacing: 5px;
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background-color: #1CC5DC;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Shubhanya Enterprises</h1>
            </div>
            <div class="content">
                <p>Hello ' . ucfirst($userName) . ',</p>
                <p>We received a request to reset your password. Please use the following One-Time Password (OTP) to verify your identity:</p>
                
                <div class="otp-box">
                    ' . $otp . '
                </div>
                
                <p>This OTP will expire in 15 minutes for security reasons.</p>
                <p>If you didn\'t request a password reset, please ignore this email or contact our support team if you have concerns.</p>
                
                <p>Thank you for shopping with us!</p>
                <p>Best regards,<br>Shubhanya Enterprises Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date("Y") . ' Anuraj Maddhesiya. All rights reserved.</p>
                <p>This is an automated message, please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

// Process based on mode
switch ($data['mode']) {
    case 'request_otp':
    case 'resend_otp':
        // Validate email
        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email address'
            ]);
            exit;
        }
        
        $email = $conn->real_escape_string(trim($data['email']));
        
        // Check if email exists in the database and get user's first and last name
        $sql = "SELECT id, first_name, last_name FROM users WHERE email = '$email'";
        $result = $conn->query($sql);
        
        if ($result->num_rows == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Email address not found'
            ]);
            exit;
        }
        
        $user = $result->fetch_assoc();
        $firstName = $user['first_name'];
        $lastName = $user['last_name'];
        
        // Generate OTP - ensuring it's exactly 6 digits
        $otp = generateOTP();
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Check if password_resets table exists; if not, create it
        $checkTableSQL = "SHOW TABLES LIKE 'password_resets'";
        $tableResult = $conn->query($checkTableSQL);
        
        if ($tableResult->num_rows == 0) {
            // Table doesn't exist, create it
            $createTableSQL = "CREATE TABLE password_resets (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                otp VARCHAR(6) NOT NULL,
                expiry DATETIME NOT NULL,
                attempts INT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY email_unique (email)
            )";
            
            if (!$conn->query($createTableSQL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create required database table: ' . $conn->error
                ]);
                exit;
            }
        }
        
        // Store OTP in database - make sure we're storing clean digits only
        $otp = preg_replace('/[^0-9]/', '', $otp); // Ensure only digits
        
        // Store OTP in database
        $sql = "INSERT INTO password_resets (email, otp, expiry) VALUES ('$email', '$otp', '$expiry')
                ON DUPLICATE KEY UPDATE otp = '$otp', expiry = '$expiry', attempts = 0";
        
        if ($conn->query($sql) === TRUE) {
            // Send OTP email
            if (sendOTPEmail($email, $otp, $firstName, $lastName)) {
                // Log the generated OTP for debugging
                error_log("Generated OTP for $email: $otp");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'OTP sent successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to send OTP email. Please check your email configuration.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $conn->error
            ]);
        }
        break;
        
    case 'verify_otp':
        // Validate data
        if (!isset($data['email']) || !isset($data['otp'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
        
        $email = $conn->real_escape_string(trim($data['email']));
        
        // IMPORTANT FIX: Clean the OTP to ensure it only contains digits
        $otp = preg_replace('/[^0-9]/', '', $data['otp']);
        
        // DEBUG: Log the values we're checking
        error_log("OTP Verification - Email: $email, OTP entered: $otp");
        
        // Get the stored OTP for debugging
        $checkSql = "SELECT * FROM password_resets WHERE email = '$email'";
        $checkResult = $conn->query($checkSql);
        
        if ($checkResult->num_rows > 0) {
            $row = $checkResult->fetch_assoc();
            $storedOtp = trim($row['otp']);
            
            // IMPORTANT FIX: Clean the stored OTP also to ensure consistent comparison
            $storedOtp = preg_replace('/[^0-9]/', '', $storedOtp);
            
            error_log("Stored OTP: {$storedOtp} (Length: " . strlen($storedOtp) . ")");
            error_log("Entered OTP: {$otp} (Length: " . strlen($otp) . ")");
            error_log("Comparison: " . ($storedOtp === $otp ? "MATCH" : "NO MATCH"));
            error_log("Expiry: {$row['expiry']}, Current time: " . date('Y-m-d H:i:s'));
            
            // IMPORTANT FIX: Direct string comparison rather than relying on SQL comparison
            if ($storedOtp === $otp && strtotime($row['expiry']) > time() && $row['attempts'] < 5) {
                // OTP is valid
                error_log("OTP validation successful");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'OTP verified successfully'
                ]);
                exit;
            }
            
            // If we got here, the OTP didn't match or is expired or too many attempts
            // Increment attempts
            $updateSql = "UPDATE password_resets SET attempts = attempts + 1 WHERE email = '$email'";
            $conn->query($updateSql);
            
            // Check if OTP is expired
            if (strtotime($row['expiry']) < time()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ]);
            } else if ($row['attempts'] >= 4) { // 4 because we just incremented it
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new OTP.'
                ]);
            } else {
                $remainingAttempts = 5 - ($row['attempts'] + 1);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid OTP. ' . $remainingAttempts . ' attempts remaining.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No OTP request found for this email'
            ]);
        }
        break;
    
    case 'reset_password':
        // Validate data
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['otp'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
        
        $email = $conn->real_escape_string(trim($data['email']));
        $password = $conn->real_escape_string($data['password']);
        
        // IMPORTANT FIX: Clean the OTP to ensure it only contains digits
        $otp = preg_replace('/[^0-9]/', '', $data['otp']);
        
        // Get the stored OTP for comparison
        $checkSql = "SELECT * FROM password_resets WHERE email = '$email'";
        $checkResult = $conn->query($checkSql);
        
        if ($checkResult->num_rows > 0) {
            $row = $checkResult->fetch_assoc();
            $storedOtp = preg_replace('/[^0-9]/', '', $row['otp']);
            
            // IMPORTANT FIX: Direct string comparison
            if ($storedOtp === $otp && strtotime($row['expiry']) > time()) {
                // OTP verified, update password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateSql = "UPDATE users SET password = '$hashedPassword' WHERE email = '$email'";
                
                if ($conn->query($updateSql) === TRUE) {
                    // Delete the OTP record
                    $deleteSql = "DELETE FROM password_resets WHERE email = '$email'";
                    $conn->query($deleteSql);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Password reset successfully'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to update password: ' . $conn->error
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No OTP request found for this email'
            ]);
        }
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid mode'
        ]);
}

// Close database connection
$conn->close();
?>