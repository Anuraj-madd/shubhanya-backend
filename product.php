<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$upload_dir = "uploads/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$method = $_SERVER['REQUEST_METHOD'];

// Debug logging
error_log("Request Method: " . $method);
if ($method === 'DELETE' && isset($_GET['id'])) {
    error_log("Delete request for ID: " . $_GET['id']);
}

if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM products");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode($products);
} elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $mrp = $_POST['mrp'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $offer = $_POST['offer'];
    $description = $_POST['description'];
    
    $image_name = $_POST['existingImage'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $image_name = time() . '_' . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
    }
    
    $stmt = $conn->prepare("UPDATE products SET name=?, mrp=?, price=?, stock=?, offer=?, description=?, image=? WHERE id=?");
    $stmt->bind_param("sssssssi", $name, $mrp, $price, $stock, $offer, $description, $image_name, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Update failed: " . $stmt->error);
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
} elseif ($method === 'POST' && !isset($_GET['action'])) {
    $name = $_POST['name'];
    $mrp = $_POST['mrp'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $offer = $_POST['offer'];
    $description = $_POST['description'];

    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $image_name = time() . '_' . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
    }

    $stmt = $conn->prepare("INSERT INTO products (name, mrp, price, stock, offer, description, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $name, $mrp, $price, $stock, $offer, $description, $image_name);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Insert failed: " . $stmt->error);
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
} elseif ($method === 'DELETE') {
    // Get the ID from the URL parameter
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($id) {
        // Prepare statement for deleting the product
        $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            error_log("Product deleted successfully: ID " . $id);
            echo json_encode(['success' => true]);
        } else {
            error_log("Delete failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } else {
        error_log("Delete failed - ID not provided or invalid");
        echo json_encode(['success' => false, 'error' => 'ID not provided']);
    }
}

$conn->close();
?>