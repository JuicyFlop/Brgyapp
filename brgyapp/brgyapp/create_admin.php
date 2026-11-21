<?php

require_once __DIR__ . "/config/database.php";

$fullName = "System Administrator";
$username = "admin";
$password = "admin123";
$role = "admin";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (full_name, username, password, role)
    VALUES (:full_name, :username, :password, :role)
");

$stmt->execute([
    ":full_name" => $fullName,
    ":username" => $username,
    ":password" => $hashedPassword,
    ":role" => $role
]);

echo "Admin account created successfully.";
