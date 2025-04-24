<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST");
header("Content-Type: application/json");

// For preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
require_once 'config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Function to send order status notification emails
function sendOrderStatusEmail($conn, $orderId, $newStatus) {
    // Get order details and customer email
    $sql = "SELECT o.*, u.email FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.order_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $customerEmail = $order['email'];
        $customerName = $order['name'];
        
        // Get order items
        $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->bind_param("s", $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        $items = [];
        $itemsHtml = "";
        
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
            $itemsHtml .= "<tr>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['product_name']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['quantity']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>₹{$item['price']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>₹{$item['total_price']}</td>
            </tr>";
        }
        
        // Create mail object
        $mail = new PHPMailer(true);
        
        try {
            // SMTP configuration (update these with your SMTP details)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Update with your SMTP host
            $mail->SMTPAuth = true;
            $mail->Username = 'fun.storage26@gmail.com'; // Update with your email
            $mail->Password = 'rdkriwciwwxztizj'; // Update with your password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Email content
            $mail->setFrom('no-reply@cybernetic.co.in', 'Shubhanya Enterprises');
            $mail->addAddress($customerEmail, $customerName);
            $mail->isHTML(true);
            
            // Email subject based on status
            $statusMap = [
                'pending' => 'Order Received',
                'processing' => 'Order Processing',
                'shipped' => 'Order Shipped',
                'delivered' => 'Order Delivered',
                'cancelled' => 'Order Cancelled'
            ];
            
            $statusTitle = isset($statusMap[$newStatus]) ? $statusMap[$newStatus] : 'Order Update';
            
            $mail->Subject = "Shubhanya: Order #{$orderId} - {$statusTitle}";
            
            // Email body based on status
            $statusMessages = [
                'pending' => "We've received your order and are reviewing it. We'll update you soon.",
                'processing' => "We're processing your order and preparing your items for shipment.",
                'shipped' => "Your order is on its way! It should reach you soon.",
                'delivered' => "Your order has been delivered. We hope you enjoy your purchase!",
                'cancelled' => "Your order has been cancelled as requested."
            ];
            
            $statusMessage = isset($statusMessages[$newStatus]) ? $statusMessages[$newStatus] : "Your order status has been updated to {$newStatus}.";
            $statusColor = [
                'pending' => '#f59e0b',
                'processing' => '#3b82f6',
                'shipped' => '#10b981',
                'delivered' => '#059669',
                'cancelled' => '#ef4444'
            ];
            
            $currentStatusColor = isset($statusColor[$newStatus]) ? $statusColor[$newStatus] : '#6b7280';
            
            // Create email template
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background-color: #1B263B; padding: 20px; text-align: center; color: white; }
                    .content { padding: 20px; }
                    .footer { background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                    .status { padding: 10px; background-color: {$currentStatusColor}; color: white; text-align: center; font-weight: bold; border-radius: 4px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th { text-align: left; padding: 10px; background-color: #f5f5f5; border-bottom: 2px solid #ddd; }
                    .total { font-weight: bold; text-align: right; padding: 10px; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #1CC5DC; color: white; text-decoration: none; border-radius: 4px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Shubhanya Enterprises</h1>
                    </div>
                    <div class='content'>
                        <p>Dear {$customerName},</p>
                        <p>Your order #{$orderId} status has been updated.</p>
                        
                        <div class='status'>
                            {$statusTitle}
                        </div>
                        
                        <p>{$statusMessage}</p>
                        
                        <h2>Order Details</h2>
                        <p><strong>Order ID:</strong> {$orderId}</p>
                        <p><strong>Order Date:</strong> " . date('F j, Y, g:i a', strtotime($order['order_date'])) . "</p>
                        <p><strong>Payment Method:</strong> " . ucfirst($order['payment_mode']) . "</p>
                        
                        <h3>Shipping Address</h3>
                        <p>
                            {$order['address1']}<br>
                            " . ($order['address2'] ? $order['address2'] . "<br>" : "") . "
                            {$order['city']} - {$order['pincode']}<br>
                            Phone: {$order['phone']}
                        </p>
                        
                        <h3>Order Summary</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$itemsHtml}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan='3' class='total'>Total:</td>
                                    <td>₹{$order['total_amount']}</td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <p>Thank you for shopping with Shubhanya!</p>
                        
                        <p>
                            <a href='https://shubhanya.com/track-order?id={$orderId}' class='button'>Track Your Order</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Anuraj Maddhesiya. All rights reserved.</p>
                        <p>If you have any questions, please contact our customer support at support@shubhanya.com</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $statusMessage)) . "\n\n" .
                            "Order ID: {$orderId}\n" .
                            "Order Status: {$statusTitle}\n" .
                            "Order Total: ₹{$order['total_amount']}\n\n" .
                            "Thank you for shopping with Shubhanya!";
            
            $mail->send();
            return [
                "success" => true,
                "message" => "Email notification sent to {$customerEmail}"
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"
            ];
        }
    }
    
    return [
        "success" => false,
        "message" => "Could not find order details or customer email"
    ];
}

// Function to update product stock when order is delivered
function updateProductStock($conn, $orderId) {
    // Get all items for this order
    $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("s", $orderId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $stockUpdateErrors = [];
    $stockUpdateSuccess = [];
    
    while ($item = $itemsResult->fetch_assoc()) {
        $product_id = $item['product_id'];
        $quantity = (int)$item['quantity'];
        $product_name = $item['product_name'];
        
        // Check if product exists and has enough stock
        $checkStockSql = "SELECT stock FROM products WHERE id = ?";
        $checkStockStmt = $conn->prepare($checkStockSql);
        $checkStockStmt->bind_param("i", $product_id);
        $checkStockStmt->execute();
        $stockResult = $checkStockStmt->get_result();
        
        if ($stockResult && $stockResult->num_rows > 0) {
            $currentStock = (int)$stockResult->fetch_assoc()['stock'];
            
            // Update the stock
            if ($currentStock < $quantity) {
                // Option 1: Log a warning and set stock to 0
                $reduceStockSql = "UPDATE products SET stock = 0 WHERE id = ?";
                $reduceStockStmt = $conn->prepare($reduceStockSql);
                $reduceStockStmt->bind_param("i", $product_id);
                
                if ($reduceStockStmt->execute()) {
                    $stockUpdateSuccess[] = "Set stock to 0 for product '{$product_name}' (ID: {$product_id}) as requested quantity ({$quantity}) exceeds available stock ({$currentStock})";
                } else {
                    $stockUpdateErrors[] = "Failed to update stock for product '{$product_name}' (ID: {$product_id}): " . $conn->error;
                }
            } else {
                // Normal case: reduce stock by the ordered quantity
                $reduceStockSql = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $reduceStockStmt = $conn->prepare($reduceStockSql);
                $reduceStockStmt->bind_param("ii", $quantity, $product_id);
                
                if ($reduceStockStmt->execute()) {
                    $stockUpdateSuccess[] = "Reduced stock for product '{$product_name}' (ID: {$product_id}) by {$quantity}";
                } else {
                    $stockUpdateErrors[] = "Failed to update stock for product '{$product_name}' (ID: {$product_id}): " . $conn->error;
                }
            }
        } else {
            $stockUpdateErrors[] = "Product '{$product_name}' (ID: {$product_id}) not found";
        }
    }
    
    return [
        "success" => empty($stockUpdateErrors),
        "updated" => $stockUpdateSuccess,
        "errors" => $stockUpdateErrors
    ];
}

// Handle GET requests - Fetch all orders with their items
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // First get all orders
        $ordersSql = "SELECT * FROM orders ORDER BY order_date DESC";
        $ordersResult = $conn->query($ordersSql);
        
        if (!$ordersResult) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        
        $orders = [];
        while ($order = $ordersResult->fetch_assoc()) {
            // For each order, get its items
            $itemsSql = "SELECT * FROM order_items WHERE order_id = '{$order['order_id']}'";
            $itemsResult = $conn->query($itemsSql);
            
            if (!$itemsResult) {
                throw new Exception("Failed to fetch order items: " . $conn->error);
            }
            
            $items = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $items[] = $item;
            }
            
            // Add items to the order
            $order['items'] = $items;
            $orders[] = $order;
        }
        
        echo json_encode($orders);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}

// Handle POST requests - Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data['mode']) && $data['mode'] === 'update_status') {
        try {
            $conn->begin_transaction();
            
            $order_id = $conn->real_escape_string($data['order_id']);
            $new_status = $conn->real_escape_string($data['order_status']);
            
            // First, fetch the order's current status (simplified query)
            $checkOrderSql = "SELECT order_status FROM orders WHERE order_id = ?";
            $checkOrderStmt = $conn->prepare($checkOrderSql);
            $checkOrderStmt->bind_param("s", $order_id);
            $checkOrderStmt->execute();
            $orderResult = $checkOrderStmt->get_result();
            
            if ($orderResult && $orderResult->num_rows > 0) {
                $order = $orderResult->fetch_assoc();
                $current_status = $order['order_status'];
                
                // Update order status
                $updateOrderSql = "UPDATE orders SET order_status = ? WHERE order_id = ?";
                $updateOrderStmt = $conn->prepare($updateOrderSql);
                $updateOrderStmt->bind_param("ss", $new_status, $order_id);
                
                if ($updateOrderStmt->execute()) {
                    $stockUpdateResult = null;
                    
                    // If changing to delivered and not already delivered before
                    if ($new_status === 'delivered' && $current_status !== 'delivered') {
                        // Update product stock
                        $stockUpdateResult = updateProductStock($conn, $order_id);
                        
                        // If there were errors updating stock, we could handle them here
                        // For now, we'll continue even if there were errors
                    }
                    
                    // Send email notification
                    $emailResult = sendOrderStatusEmail($conn, $order_id, $new_status);
                    
                    $conn->commit();
                    
                    echo json_encode([
                        "success" => true,
                        "message" => "Order $order_id updated to $new_status",
                        "email_status" => $emailResult,
                        "stock_update" => $stockUpdateResult
                    ]);
                } else {
                    throw new Exception("Failed to update order status: " . $conn->error);
                }
            } else {
                throw new Exception("Order not found");
            }
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);
        }
        exit;
    }
    
    echo json_encode(["error" => "Invalid mode"]);
    exit;
}

echo json_encode(["error" => "Invalid request method"]);
$conn->close();
?>