<?php
session_start();
$enteredOtp = $_POST['otp'];

if ($enteredOtp == $_SESSION['otp']) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
