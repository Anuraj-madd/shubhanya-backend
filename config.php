<?php
// config.php

// Render usually sets DATABASE_URL or you may have "uri" in env
$uri = getenv("DATABASE_URL") ?: getenv("uri");

if (!$uri) {
    die("Database URI not found in environment.");
}

$fields = parse_url($uri);

// Extract DB connection details
$host = $fields["host"];
$port = $fields["port"] ?? 3306;
$user = $fields["user"];
$pass = $fields["pass"];
$dbname = ltrim($fields["path"], "/");

// Build DSN with SSL (for Aiven/Render MySQL)
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};sslmode=verify-ca;sslrootcert=" . __DIR__ . "/ca.pem";

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Optional: test query
    // $stmt = $db->query("SELECT VERSION()");
    // echo "Connected. MySQL version: " . $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
