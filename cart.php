<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Log raw input data to check what is being sent
$rawInput = file_get_contents("php://input");
// file_put_contents("log.txt", "Received data: " . $rawInput . PHP_EOL, FILE_APPEND);  // Log raw data

// Decode JSON input
$data = json_decode($rawInput);

// Check if the input is valid JSON
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    exit;
}

// Log the decoded data for debugging (optional)
// file_put_contents("log.txt", "Decoded data: " . json_encode($data) . PHP_EOL, FILE_APPEND);

$mode = $data->mode ?? ''; // Get the mode (add, update, delete, fetch)

// Include your database connection
include "config.php";

// Process based on the mode
if ($mode === 'add') {
    $user_id = (int) $data->user_id;  // Cast to integer
    $product_id = (int) $data->product_id;  // Cast to integer
    $quantity = (int) $data->quantity;  // Cast to integer

    $check = $conn->prepare("SELECT * FROM user_cart WHERE user_id = ? AND product_id = ?");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        // Update quantity if the product is already in the cart
        $update = $conn->prepare("UPDATE user_cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
        $update->bind_param("iii", $quantity, $user_id, $product_id);
        $update->execute();
        echo json_encode(["status" => "success", "message" => "Cart updated"]);
    } else {
        // Insert new product into cart
        $insert = $conn->prepare("INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $user_id, $product_id, $quantity);
        $insert->execute();
        echo json_encode(["status" => "success", "message" => "Product added to cart"]);
    }

} elseif ($mode === 'fetch') {
    $user_id = (int) $data->user_id;  // Cast to integer

    // Fetch cart items
    $stmt = $conn->prepare("SELECT uc.product_id as id, uc.quantity, p.name, p.price, p.image FROM user_cart uc JOIN products p ON uc.product_id = p.id WHERE uc.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cartItems = [];
    while ($row = $result->fetch_assoc()) {
        $cartItems[] = $row;
    }

    echo json_encode($cartItems);

} elseif ($mode === 'delete') {
    $user_id = (int) $data->user_id;  // Cast to integer
    $product_id = (int) $data->product_id;  // Cast to integer

    // Delete item from cart
    $stmt = $conn->prepare("DELETE FROM user_cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Item removed from cart"]);

} elseif ($mode === 'update') {
    $user_id = (int) $data->user_id;  // Cast to integer
    $product_id = (int) $data->product_id;  // Cast to integer
    $quantity = (int) $data->quantity;  // Cast to integer

    // Update product quantity in cart
    $stmt = $conn->prepare("UPDATE user_cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Quantity updated"]);

} else {
    echo json_encode(["status" => "error", "message" => "Invalid mode"]);
}
?>