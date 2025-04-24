<?php
header("Access-Control-Allow-Origin: *"); // Allow from any origin or specify your frontend origin
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader (if using Composer)
// require 'vendor/autoload.php';

// Or include the PHPMailer files directly (if not using Composer)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require_once 'config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Log request data for debugging
$raw_data = file_get_contents("php://input");
// file_put_contents('order_debug.log', date('Y-m-d H:i:s') . " - Received data: " . $raw_data . PHP_EOL, FILE_APPEND);

// Get JSON data from request
$data = json_decode($raw_data, true);

// Validate input
if (!isset($data['user_id']) || !isset($data['cartItems']) || !isset($data['formData'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request data"]);
    exit();
}

$user_id = $data['user_id'];
$items = $data['cartItems'];
$form = $data['formData'];

// Check if items array is empty
if (empty($items)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Cart is empty"]);
    exit();
}

// Extract shipping details
$name = $form['name'];
$address1 = $form['address1'];
$address2 = $form['address2'] ?? ''; // Make address2 optional
$city = $form['city'];
$pincode = $form['pincode'];
$phone = $form['phone'];
$payment_mode = $form['paymentMode'];

// Input validation
if (empty($name) || empty($address1) || empty($city) || empty($pincode) || empty($phone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Required shipping details missing"]);
    exit();
}

// Function to generate unique order ID
function generateOrderId() {
    $prefix = "SHUB";
    $timestamp = date("YmdHis"); // YearMonthDayHourMinuteSecond
    $random = mt_rand(100, 999); // Random 3-digit number
    return $prefix . $timestamp . $random;
}

// Start transaction
$conn->begin_transaction();

try {
    // Generate a unique order ID
    $order_id = generateOrderId();
    $order_date = date("Y-m-d H:i:s");
    
    // Calculate order total
    $total_amount = 0;
    foreach ($items as $item) {
        $price = floatval($item['price']);
        $quantity = intval($item['quantity']);
        $total_amount += $price * $quantity;
    }
    
    // Insert main order record
    $order_sql = "INSERT INTO orders (
        order_id, user_id, name, address1, address2, city, pincode, phone, payment_mode, total_amount, order_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($order_sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed for main order: " . $conn->error);
    }
    
    $stmt->bind_param(
        "sisssssssds",
        $order_id,
        $user_id,
        $name,
        $address1,
        $address2,
        $city,
        $pincode,
        $phone,
        $payment_mode,
        $total_amount,
        $order_date
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create order: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Insert all items into the order_items table
    foreach ($items as $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : (isset($item['id']) ? $item['id'] : 0);
        
        if (!$product_id) {
            throw new Exception("Missing product ID for item: " . json_encode($item));
        }
        
        $product_name = $item['name'];
        $price = floatval($item['price']);
        $quantity = intval($item['quantity']);
        $total = $price * $quantity;
        
        $item_sql = "INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total_price) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        
        $item_stmt = $conn->prepare($item_sql);
        
        if (!$item_stmt) {
            throw new Exception("Prepare failed for item insert: " . $conn->error);
        }
        
        $item_stmt->bind_param(
            "sisids",
            $order_id,
            $product_id,
            $product_name,
            $quantity,
            $price,
            $total
        );
        
        if (!$item_stmt->execute()) {
            throw new Exception("Failed to insert order item: " . $item_stmt->error);
        }
        
        $item_stmt->close();
    }

    // Check if the table name is cart or user_cart
    $cart_table = "cart"; // Default to cart
    
    // Check if user_cart table exists
    $result = $conn->query("SHOW TABLES LIKE 'user_cart'");
    if ($result->num_rows > 0) {
        $cart_table = "user_cart"; // Use user_cart if it exists
    }
    
    // Clear user's cart after successful order
    $clearCartStmt = $conn->prepare("DELETE FROM $cart_table WHERE user_id = ?");
    
    if (!$clearCartStmt) {
        throw new Exception("Prepare cart deletion failed: " . $conn->error);
    }
    
    $clearCartStmt->bind_param("i", $user_id);
    
    if (!$clearCartStmt->execute()) {
        throw new Exception("Failed to clear cart: " . $clearCartStmt->error);
    }
    
    $clearCartStmt->close();
    
    // Get user email from database
    $email_query = "SELECT email FROM users WHERE id = ?";
    $email_stmt = $conn->prepare($email_query);
    
    if (!$email_stmt) {
        throw new Exception("Failed to prepare email query: " . $conn->error);
    }
    
    $email_stmt->bind_param("i", $user_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    
    if ($email_result->num_rows === 0) {
        throw new Exception("User not found");
    }
    
    $user_data = $email_result->fetch_assoc();
    $user_email = $user_data['email'];
    $email_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Send confirmation email to customer and notification to sales team
    sendOrderConfirmationEmail($user_email, $name, $order_id, $items, $order_date, $address1, $address2, $city, $pincode, $phone, $payment_mode, $total_amount);
    
    echo json_encode([
        "status" => "success", 
        "message" => "Order placed successfully", 
        "order_id" => $order_id,
        "order_date" => $order_date
    ]);
    
} catch (Exception $e) {
    // Roll back on error
    $conn->rollback();
    
    // Log the error
    file_put_contents('order_error.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to place order: " . $e->getMessage()
    ]);
}

$conn->close();

/**
 * Send order confirmation email to customer and notification to sales team
 */
function sendOrderConfirmationEmail($email, $name, $order_id, $items, $order_date, $address1, $address2, $city, $pincode, $phone, $payment_mode, $total_amount) {
    // SMTP Configuration
    $smtp_host = getenv("smtp_host");
    $smtp_username = getenv("smtp_username");
    $smtp_password = getenv("smtp_password");
    $smtp_port = 587;
    $smtp_secure = PHPMailer::ENCRYPTION_STARTTLS;
    
    // Sales email address
    $sales_email = 'sales@cybernetic.co.in';
    
    // Calculate values for email
    $subtotal_before_tax = $total_amount / 1.18;
    $tax = $subtotal_before_tax * 0.18;
    $shipping_fee = $subtotal_before_tax < 199 ? 40 : 0;
    $total = $subtotal_before_tax + $tax + $shipping_fee;

    // Generate order items HTML for both emails
    $itemsHtml = '';
    $itemsList = ''; // For plain text alternative
    $itemCount = 0;
    $totalQuantity = 0;
    
    foreach ($items as $item) {
        $itemCount++;
        $price = floatval($item['price']);
        $quantity = intval($item['quantity']);
        $totalQuantity += $quantity;
        $itemTotal = $price * $quantity;
        
        $itemsHtml .= "<tr>
            <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$item['name']}</td>
            <td style='padding: 12px; border-bottom: 1px solid #eee;'>₹" . number_format($price, 2) . "</td>
            <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$quantity}</td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>₹" . number_format($itemTotal, 2) . "</td>
        </tr>";
        
        $itemsList .= "{$item['name']} - ₹" . number_format($price, 2) . " x {$quantity} = ₹" . number_format($itemTotal, 2) . "\n";
    }

    // 1. SEND EMAIL TO CUSTOMER
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $smtp_secure;
        $mail->Port = $smtp_port;

        // Recipients
        $mail->setFrom('no-reply@cybernetic.co.in', 'Shubhanya Enterprises');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation - $order_id";

        // Build customer email body
        $customerMailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Order Confirmation</title>
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 650px;
                    margin: 0 auto;
                }
                .header {
                    background-color: #1CC5DC;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .content {
                    padding: 20px;
                    background-color: #f9f9f9;
                }
                .footer {
                    background-color: #f1f1f1;
                    padding: 10px 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .order-details {
                    background-color: white;
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }
                .order-summary {
                    background-color: white;
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th {
                    text-align: left;
                    padding: 12px;
                    background-color: #f5f5f5;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #f9f9f9;
                }
                .thank-you {
                    text-align: center;
                    font-size: 18px;
                    margin: 30px 0;
                }
                .btn {
                    display: inline-block;
                    background-color: #1CC5DC;
                    color: white;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Order Confirmation</h1>
            </div>
            
            <div class='content'>
                <p>Dear $name,</p>
                <p>Thank you for your order. We are pleased to confirm that your order has been received and is being processed.</p>
                
                <div class='order-details'>
                    <h2>Order Details</h2>
                    <p><strong>Order ID:</strong> $order_id</p>
                    <p><strong>Order Date:</strong> $order_date</p>
                    <p><strong>Payment Method:</strong> " . ($payment_mode == 'cod' ? 'Cash on Delivery' : 'Online Payment') . "</p>
                    
                    <h3>Shipping Address:</h3>
                    <p>
                        $name<br>
                        $address1<br>
                        " . ($address2 ? "$address2<br>" : "") . "
                        $city - $pincode<br>
                        Phone: $phone
                    </p>
                </div>
                
                <div class='order-summary'>
                    <h2>Order Summary</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            $itemsHtml
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan='3' style='padding: 12px; text-align: right;'>Subtotal (Before Tax):</td>
                                <td style='padding: 12px; text-align: right;'>₹" . number_format($subtotal_before_tax, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='3' style='padding: 12px; text-align: right;'>Tax (18% GST):</td>
                                <td style='padding: 12px; text-align: right;'>₹" . number_format($tax, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='3' style='padding: 12px; text-align: right;'>Shipping Fee:</td>
                                <td style='padding: 12px; text-align: right;'>₹" . number_format($shipping_fee, 2) . "</td>
                            </tr>
                            <tr class='total-row'>
                                <td colspan='3' style='padding: 12px; border-top: 2px solid #ddd; text-align: right;'>Total:</td>
                                <td style='padding: 12px; border-top: 2px solid #ddd; text-align: right;'>₹" . number_format($total, 2) . "</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class='thank-you'>
                    <p>Thank you for shopping with Shubhanya!</p>
                    <a href='http://192.168.29.101:3001/orders' class='btn'>View Your Orders</a>
                </div>
            </div>
            
            <div class='footer'>
                <p>If you have any questions, please contact our customer support at support@shubhanya.com</p>
                <p>&copy; " . date('Y') . " Anuraj Maddhesiya. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $customerMailBody;
        $mail->AltBody = "Order Confirmation - $order_id\n\n" .
                         "Dear $name,\n\n" .
                         "Thank you for your order. Your order ID is $order_id.\n" .
                         "Order Date: $order_date\n" .
                         "Payment Method: " . ($payment_mode == 'cod' ? 'Cash on Delivery' : 'Online Payment') . "\n\n" .
                         "Shipping Address:\n$name\n$address1\n" . ($address2 ? "$address2\n" : "") . "$city - $pincode\nPhone: $phone\n\n" .
                         "Total Amount: ₹" . number_format($total, 2) . "\n\n" .
                         "Thank you for shopping with Shubhanya!";

        $mail->send();
        // Log email sent
        file_put_contents('email_log.txt', date('Y-m-d H:i:s') . " - Customer email sent to $email for order $order_id\n", FILE_APPEND);
        
    } catch (Exception $e) {
        // Log email error
        file_put_contents('email_error.log', date('Y-m-d H:i:s') . " - Customer email error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 2. SEND NOTIFICATION EMAIL TO SALES TEAM
    try {
        $salesMail = new PHPMailer(true);
        
        // Server settings
        $salesMail->isSMTP();
        $salesMail->Host = $smtp_host;
        $salesMail->SMTPAuth = true;
        $salesMail->Username = $smtp_username;
        $salesMail->Password = $smtp_password;
        $salesMail->SMTPSecure = $smtp_secure;
        $salesMail->Port = $smtp_port;

        // Recipients
        $salesMail->setFrom('no-reply@cybernetic.co.in', 'Shubhanya Order System');
        $salesMail->addAddress($sales_email, 'Sales Team');

        // Content
        $salesMail->isHTML(true);
        $salesMail->Subject = "New Order Alert - $order_id";

        // Format date for better readability
        $formatted_date = date('d M Y, h:i A', strtotime($order_date));
        
        // Payment method display text
        $payment_display = ($payment_mode == 'cod') ? '💵 Cash on Delivery' : '💳 Online Payment';

        // Build sales team email body
        $salesMailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>New Order Alert</title>
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 650px;
                    margin: 0 auto;
                }
                .header {
                    background-color: #4A6FDC;
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 4px 4px 0 0;
                }
                .content {
                    padding: 20px;
                    background-color: #f9f9f9;
                }
                .footer {
                    background-color: #f1f1f1;
                    padding: 10px 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    border-radius: 0 0 4px 4px;
                }
                .panel {
                    background-color: white;
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }
                .highlight {
                    background-color: #f7f9fc;
                    border-left: 4px solid #4A6FDC;
                    padding: 10px 15px;
                    margin: 15px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th {
                    text-align: left;
                    padding: 12px;
                    background-color: #f5f5f5;
                }
                tr:nth-child(even) {
                    background-color: #fafafa;
                }
                .btn {
                    display: inline-block;
                    background-color: #4A6FDC;
                    color: white;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    margin-top: 20px;
                    text-align: center;
                }
                .alert {
                    background-color: #FF9800;
                    color: white;
                    padding: 10px;
                    border-radius: 4px;
                    margin: 10px 0;
                }
                .stats {
                    display: flex;
                    justify-content: space-between;
                    text-align: center;
                    margin: 20px 0;
                }
                .stat-box {
                    background-color: #f5f5f5;
                    border-radius: 4px;
                    padding: 15px;
                    width: 30%;
                }
                .stat-value {
                    font-size: 24px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .stat-label {
                    font-size: 14px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🔔 New Order Alert</h1>
            </div>
            
            <div class='content'>
                <div class='panel'>
                    <h2>Order Information</h2>
                    
                    <div class='highlight'>
                        <p><strong>Order ID:</strong> $order_id</p>
                        <p><strong>Date/Time:</strong> $formatted_date</p>
                        <p><strong>Payment Method:</strong> $payment_display</p>
                    </div>
                    
                    <div class='stats'>
                        <div class='stat-box'>
                            <div class='stat-label'>Total Amount</div>
                            <div class='stat-value'>₹" . number_format($total, 2) . "</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Items</div>
                            <div class='stat-value'>$itemCount</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Quantity</div>
                            <div class='stat-value'>$totalQuantity</div>
                        </div>
                    </div>
                </div>
                
                <div class='panel'>
                    <h2>Customer Details</h2>
                    <p><strong>Name:</strong> $name</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Phone:</strong> $phone</p>
                    
                    <h3>Shipping Address:</h3>
                    <p>
                        $address1<br>
                        " . ($address2 ? "$address2<br>" : "") . "
                        $city - $pincode
                    </p>
                </div>
                
                <div class='panel'>
                    <h2>Order Summary</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            $itemsHtml
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan='3' style='padding: 12px; text-align: right;'>Subtotal:</td>
                                <td style='padding: 12px; text-align: right;'>₹" . number_format($subtotal_before_tax, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='3' style='padding: 12px; text-align: right;'>Tax (18% GST):</td>
                                <td style='padding: 12px; text-align: right;'>₹" . number_format($tax, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='3' style='padding: 12px; text-align: right;'>Shipping Fee:</td>
                                <td style='padding: 12px; text-align: right;'>₹" . number_format($shipping_fee, 2) . "</td>
                            </tr>
                            <tr>
                                <td colspan='3' style='padding: 12px; border-top: 2px solid #ddd; text-align: right;'><strong>Total:</strong></td>
                                <td style='padding: 12px; border-top: 2px solid #ddd; text-align: right;'><strong>₹" . number_format($total, 2) . "</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                " . ($payment_mode == 'cod' ? "<div class='alert'>This order requires Cash on Delivery payment. Please ensure delivery staff is prepared.</div>" : "") . "
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='http://192.168.29.101:3001/admin/orders' class='btn'>View Order in Admin Panel</a>
                </div>
            </div>
            
            <div class='footer'>
                <p>This is an automated message from the Shubhanya Order System.</p>
                <p>&copy; " . date('Y') . " Shubhanya Enterprises. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";

        $salesMail->Body = $salesMailBody;
        $salesMail->AltBody = "NEW ORDER ALERT - $order_id\n\n" .
                         "Order Information:\n" .
                         "Order ID: $order_id\n" .
                         "Date/Time: $formatted_date\n" .
                         "Payment Method: " . ($payment_mode == 'cod' ? 'Cash on Delivery' : 'Online Payment') . "\n\n" .
                         "Customer Details:\n" .
                         "Name: $name\n" .
                         "Email: $email\n" .
                         "Phone: $phone\n\n" .
                         "Shipping Address:\n$address1\n" . ($address2 ? "$address2\n" : "") . "$city - $pincode\n\n" .
                         "Order Summary:\n" .
                         "$itemsList\n" .
                         "Subtotal: ₹" . number_format($subtotal_before_tax, 2) . "\n" .
                         "Tax (18% GST): ₹" . number_format($tax, 2) . "\n" .
                         "Shipping Fee: ₹" . number_format($shipping_fee, 2) . "\n" .
                         "Total: ₹" . number_format($total, 2) . "\n\n" .
                         ($payment_mode == 'cod' ? "Note: This order requires Cash on Delivery payment.\n\n" : "") .
                         "View this order in the admin panel: http://192.168.29.101:3001/admin/orders";

        $salesMail->send();
        // Log sales notification email sent
        file_put_contents('email_log.txt', date('Y-m-d H:i:s') . " - Sales notification sent to $sales_email for order $order_id\n", FILE_APPEND);
        
        return true;
    } catch (Exception $e) {
        // Log email error
        file_put_contents('email_error.log', date('Y-m-d H:i:s') . " - Sales notification error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
?>