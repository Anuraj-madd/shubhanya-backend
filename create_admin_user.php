<?php
require_once 'config.php';


// Admin user details
$firstName = "Admin";
$lastName = "User";
$email = "admin@shubhanya.com";
$plainPassword = "admin123";  // change this to something secure
$role = "admin";

// Hash the password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Check if user already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo "Admin user already exists.\n";
} else {
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        echo "Admin user created successfully.\n";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
