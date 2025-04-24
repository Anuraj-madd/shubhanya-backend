<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'config.php';

if ($conn->connect_error) {
  die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $sql = "SELECT id, first_name, last_name, email, role, created_at FROM users";
  $result = $conn->query($sql);

  $users = [];
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }

  echo json_encode($users);
  exit;
}

if ($method === 'POST') {
  $data = json_decode(file_get_contents("php://input"), true);

  if ($data['mode'] === 'update_role') {
    $user_id = $conn->real_escape_string($data['user_id']);
    $role = $conn->real_escape_string($data['role']);
    $sql = "UPDATE users SET role = '$role' WHERE id = '$user_id'";
    $result = $conn->query($sql);
    echo json_encode(["success" => $result]);
    exit;
  }

  if ($data['mode'] === 'delete_user') {
    $user_id = $conn->real_escape_string($data['user_id']);
    $sql = "DELETE FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);
    echo json_encode(["success" => $result]);
    exit;
  }
}

$conn->close();
?>
