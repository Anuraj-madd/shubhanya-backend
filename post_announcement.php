<?php
// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Set headers for JSON response
header('Content-Type: application/json');

// Database configuration
require_once 'config.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'error' => ''
];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['title']) || empty($data['content'])) {
    $response['error'] = 'Title and content are required';
    echo json_encode($response);
    exit;
}

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Save announcement to database (assuming you have an announcements table)
    $stmt = $pdo->prepare('INSERT INTO announcements (title, content, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$data['title'], $data['content']]);
    $announcementId = $pdo->lastInsertId();
    
    // Get active subscribers
    $stmt = $pdo->prepare('SELECT email FROM subscribers WHERE active = 1');
    $stmt->execute();
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pdo->commit();
    
    // If there are active subscribers, send emails
    if (count($subscribers) > 0) {
        // Send email to all subscribers
        $emailsSent = sendEmailToSubscribers($subscribers, $data['title'], $data['content']);
        $response['success'] = true;
        $response['message'] = "Announcement posted successfully. Emails sent to $emailsSent subscribers.";
    } else {
        $response['success'] = true;
        $response['message'] = "Announcement posted successfully. No active subscribers to notify.";
    }
    
} catch (PDOException $e) {
    // Rollback transaction if error occurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['error'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $response['error'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
exit;

/**
 * Send emails to all subscribers using PHPMailer
 * 
 * @param array $subscribers List of subscriber emails
 * @param string $title Announcement title
 * @param string $content Announcement content
 * @return int Number of emails sent
 */
function sendEmailToSubscribers($subscribers, $title, $content) {
    // Include PHPMailer classes
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception; // Adjust path as needed
    
    $emailsSent = 0;
    
    foreach ($subscribers as $subscriber) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com'; // Replace with your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'your_email@example.com'; // Replace with your email
            $mail->Password = 'your_password'; // Replace with your password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('no-reply@example.com', 'Your Company Name');
            $mail->addAddress($subscriber['email']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "New Announcement: $title";
            $mail->Body = createEmailTemplate($title, $content);
            $mail->AltBody = strip_tags($content);
            
            $mail->send();
            $emailsSent++;
        } catch (Exception $e) {
            // Log email sending error
            error_log("Could not send email to {$subscriber['email']}: {$mail->ErrorInfo}");
            continue;
        }
    }
    
    return $emailsSent;
}

/**
 * Create HTML email template
 * 
 * @param string $title Announcement title
 * @param string $content Announcement content
 * @return string HTML email content
 */
function createEmailTemplate($title, $content) {
    // Format the content (convert line breaks to <br>)
    $formattedContent = nl2br(htmlspecialchars($content));
    
    // Create email template
    $template = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>New Announcement</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #1CC5DC;
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 5px 5px 0 0;
            }
            .content {
                background-color: #f9f9f9;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 0 0 5px 5px;
            }
            .footer {
                text-align: center;
                font-size: 12px;
                color: #777;
                margin-top: 20px;
            }
            .unsubscribe {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
            }
            a {
                color: #1CC5DC;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>New Announcement</h1>
        </div>
        <div class="content">
            <h2>$title</h2>
            <div>$formattedContent</div>
        </div>
        <div class="unsubscribe">
            <p>If you wish to unsubscribe from these announcements, <a href="https://yourwebsite.com/unsubscribe?email=%%EMAIL%%">click here</a>.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Your Company Name. All rights reserved.</p>
        </div>
    </body>
    </html>
    HTML;
    
    return $template;
}
?>
