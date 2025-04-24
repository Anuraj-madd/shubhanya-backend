<?php
// Allow CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read input
$request_body = file_get_contents("php://input");
$data = json_decode($request_body, true);

// Debug log
error_log("Login request: " . $request_body);

// DB credentials
require_once 'config.php';
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

// Validate inputs
$email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
$pass = isset($data['password']) ? $data['password'] : '';

if (empty($email) || empty($pass)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and password are required"]);
    exit();
}

// Query user
$sql = "SELECT * FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($pass, $user['password'])) {
        // Determine redirect
        $role = $user['role'];
        $redirect = $role === "admin" ? "/admin/product-manager" : "/products";

        // Get cart items
        $cart = [];
        $user_id = $user['id'];

        $cartQuery = "SELECT uc.product_id, uc.quantity, p.name, p.price, p.image
                      FROM user_cart uc
                      JOIN products p ON uc.product_id = p.id
                      WHERE uc.user_id = $user_id";

        $cartResult = $conn->query($cartQuery);
        while ($cartResult && $row = $cartResult->fetch_assoc()) {
            $cart[] = [
                "id" => $row['product_id'],
                "name" => $row['name'],
                "price" => $row['price'],
                "image" => $row['image'],
                "quantity" => $row['quantity']
            ];
        }

        // Final success response
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user_id,
                "firstname" => $user['first_name'],
                "lastname" => $user['last_name'],
                "email" => $user['email'],
                "role" => $role
            ],
            "cart" => $cart,
            "redirect" => $redirect
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid password"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "User not found"]);
}

$conn->close();
?>
