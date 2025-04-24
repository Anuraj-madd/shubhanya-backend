<?php
$host = getenv("host");
$db_name = getenv("db_name");
$username = getenv("username"); // Default for XAMPP or Laragon
$password = getenv("password");     // Default password is empty

$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>