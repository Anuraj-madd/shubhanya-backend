<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    exit(0);
}

// Database connection
require_once 'config.php';

// Get JSON data from request
$data = json_decode(file_get_contents("php://input"), true);
$user_id = isset($data['user_id']) ? $conn->real_escape_string($data['user_id']) : null;
$limit = isset($data['limit']) ? (int)$data['limit'] : 2; // Default to 2 recent orders

// Validate user_id
if (!$user_id) {
    echo json_encode(["error" => "User ID is required"]);
    exit;
}

// Create response array
$response = [
    "user" => null,
    "recent_orders" => []
];

// Query to get user profile
// Note: The phone field is not in the users table according to schema
$user_sql = "SELECT id, first_name, last_name, email, created_at, role FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);

if ($user_result && $user_result->num_rows > 0) {
    $response["user"] = $user_result->fetch_assoc();
    
    // Add a phone field with null value since it's expected by the frontend but not in your schema
    $response["user"]["phone"] = null;
} else {
    echo json_encode(["error" => "User not found"]);
    exit;
}

// Query to get recent orders
// Using two tables: orders and order_items
$orders_sql = "SELECT 
                o.order_id,
                o.order_date, 
                o.total_amount,
                o.payment_mode,
                o.order_status
            FROM orders o
            WHERE o.user_id = $user_id
            ORDER BY o.order_date DESC
            LIMIT $limit";

$orders_result = $conn->query($orders_sql);

if ($orders_result && $orders_result->num_rows > 0) {
    while ($order = $orders_result->fetch_assoc()) {
        // Get the items for this order
        $items_sql = "SELECT product_name, quantity
                    FROM order_items
                    WHERE order_id = '{$order['order_id']}'";
        
        $items_result = $conn->query($items_sql);
        $items_list = [];
        
        if ($items_result && $items_result->num_rows > 0) {
            while ($item = $items_result->fetch_assoc()) {
                $items_list[] = $item['product_name'] . ' (' . $item['quantity'] . ')';
            }
        }
        
        // Add items as comma-separated string
        $order['items'] = implode(', ', $items_list);
        
        // Add to response
        $response["recent_orders"][] = $order;
    }
}

// Return the combined data
echo json_encode($response);

$conn->close();
?>