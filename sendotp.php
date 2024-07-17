<?php
session_start();
$email = $_POST['email'];
$otp = $_POST['otp'];

// Store OTP in session
$_SESSION['otp'] = $otp;

// Send OTP via email
mail($email, "Your OTP Code", "Your OTP code is: $otp");

echo json_encode(['success' => true]);
?>
