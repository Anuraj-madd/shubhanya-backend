<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

header('Content-Type: application/json');

// Function to open DB connection
function openDBConnection() {
    require_once 'config.php';
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Check the request method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if an order ID is provided for fetching details
    if (isset($_GET['id'])) {
        fetchOrderDetails($_GET['id']);
    } else {
        fetchSalesData();
    }
}

// Function to fetch sales data for reports
function fetchSalesData() {
    try {
        // Connect to the database
        $conn = openDBConnection();

        // Updated query to work with the new database structure
        // Joining orders and order_items tables
        $query = "
            SELECT 
                DATE(o.order_date) AS date,
                SUM(oi.total_price) AS sales,
                COUNT(DISTINCT o.order_id) AS orders,
                o.order_id
            FROM 
                orders o
            JOIN 
                order_items oi ON o.order_id = oi.order_id
            WHERE 
                o.order_status != 'cancelled'
            GROUP BY 
                DATE(o.order_date), o.order_id
            ORDER BY 
                o.order_date DESC
        ";

        // Execute the query
        $result = $conn->query($query);

        // Check if there are results
        if ($result->num_rows > 0) {
            $salesData = [];
            while ($row = $result->fetch_assoc()) {
                $salesData[] = [
                    'id' => $row['order_id'], // Added this for the frontend to use as key
                    'date' => $row['date'],
                    'sales' => $row['sales'],
                    'orders' => $row['orders'],
                    'order_id' => $row['order_id']
                ];
            }

            // Send the response
            echo json_encode($salesData);
        } else {
            echo json_encode([]);
        }

        // Close the database connection
        $conn->close();
    } catch (Exception $e) {
        // Handle error
        echo json_encode(['error' => 'Failed to fetch sales data: ' . $e->getMessage()]);
    }
}

// Function to fetch order details by order_id
function fetchOrderDetails($orderId) {
    try {
        // Connect to the database
        $conn = openDBConnection(); 

        // Query to fetch order details
        $orderQuery = "
            SELECT
                o.order_id,
                o.user_id,
                o.name,
                o.address1,
                o.address2,
                o.city,
                o.pincode,
                o.phone,
                o.payment_mode,
                o.order_status,
                o.total_amount,
                o.order_date
            FROM 
                orders o
            WHERE 
                o.order_id = ?
        ";

        // Prepare the statement for order details
        $stmt = $conn->prepare($orderQuery);
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $orderResult = $stmt->get_result();

        if ($orderResult->num_rows > 0) {
            $orderDetails = $orderResult->fetch_assoc();
            
            // Now fetch the order items
            $itemsQuery = "
                SELECT
                    product_id,
                    product_name,
                    quantity,
                    price,
                    total_price
                FROM 
                    order_items
                WHERE 
                    order_id = ?
            ";
            
            $itemsStmt = $conn->prepare($itemsQuery);
            $itemsStmt->bind_param("s", $orderId);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $orderItems = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $orderItems[] = $item;
            }
            
            // Combine order details with items
            $orderDetails['items'] = $orderItems;
            
            echo json_encode($orderDetails);
        } else {
            echo json_encode(['error' => 'Order not found']);
        }

        // Close the database connection
        $conn->close();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch order details: ' . $e->getMessage()]);
    }
}
?>