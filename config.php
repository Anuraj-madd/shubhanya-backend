<?php
$host = getenv("host");
$port = getenv("port");
$username = getenv("username");
$password = getenv("password");
$database = getenv("db_name"); // Replace with your database name

// Path to CA certificate
$ca_cert = __DIR__ . "/ca.pem";

// Create MySQLi connection
$conn = mysqli_init();

// Set SSL options
mysqli_ssl_set($conn, NULL, NULL, $ca_cert, NULL, NULL);

// Connect with SSL
if (!mysqli_real_connect($conn, $host, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("Connection failed: " . mysqli_connect_error());
}

//echo "Connected successfully (via SSL)!";
?>
