<?php
require_once 'config/database.php';

// Check if admin already exists
$result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($result->num_rows > 0) {
    die("Admin account already exists!");
}

// Create admin user
$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';

$sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $username, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Admin account created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
} else {
    echo "Error creating admin account: " . $conn->error;
}

$stmt->close();
$conn->close();
?> 