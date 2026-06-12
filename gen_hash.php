<?php
$password = $_GET['p'] ?? 'Student@123';
echo "<pre>";
echo "Password: $password\n\n";
echo "Hash: " . password_hash($password, PASSWORD_BCRYPT);
echo "</pre>";