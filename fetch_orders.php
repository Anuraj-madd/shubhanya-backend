<?php
header("Access-Control-Allow-Origin: *");
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

require_once 'config.php';

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Get JSON data from request
$raw_data = file_get_contents("php://input");
$data = json_decode($raw_data, true);

// Check if user_id is provided
if (!isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit();
}

$user_id = $data['user_id'];

try {
    // Get all orders for the user
    $orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($orders_query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $orders = [];
    
    while ($order = $result->fetch_assoc()) {
        // For each order, get its items
        $items_query = "SELECT oi.*, p.image FROM order_items oi 
                        LEFT JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?";
        
        $items_stmt = $conn->prepare($items_query);
        
        if (!$items_stmt) {
            throw new Exception("Prepare items query failed: " . $conn->error);
        }
        
        $items_stmt->bind_param("s", $order['order_id']);
        
        if (!$items_stmt->execute()) {
            throw new Exception("Execute items query failed: " . $items_stmt->error);
        }
        
        $items_result = $items_stmt->get_result();
        $items = [];
        
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
        
        $items_stmt->close();
        
        // Add items to the order
        $order['items'] = $items;
        $orders[] = $order;
    }
    
    $stmt->close();
    
    // Return the orders with their items
    echo json_encode($orders);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to fetch orders: " . $e->getMessage()
    ]);
}

$conn->close();
?>