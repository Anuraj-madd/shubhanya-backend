<?php
$host = "mysql-3aa1aa0e-shubhanya.g.aivencloud.com"; // Replace with your Aiven host
$port = 15547; // Replace with your Aiven port
$username = "avnadmin"; // Replace with your Aiven username
$password = "AVNS_oXhussMQxPyj9ghiVUq"; // Replace with your Aiven password
$database = "defaultdb"; // Replace with your database name

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

echo "Connected successfully (via SSL)!";
?>
