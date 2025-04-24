<?php
$host = "sql12.freesqldatabase.com";
$db_name = "sql12775156";
$username = "sql12775156"; // Default for XAMPP or Laragon
$password = "P9tZZLAGui";     // Default password is empty

$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>