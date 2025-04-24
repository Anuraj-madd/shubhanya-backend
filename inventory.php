<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'config.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch inventory
    $sql = "SELECT id, name, description, stock FROM products";
    $result = $conn->query($sql);

    $inventory = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
    }

    echo json_encode($inventory);
}

elseif ($method === 'POST') {
    // Update stock
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['productId']) || !isset($data['stock'])) {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
        exit();
    }

    $productId = intval($data['productId']);
    $stock = intval($data['stock']);

    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param("ii", $stock, $productId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update stock"]);
    }

    $stmt->close();
}

$conn->close();
?>
