<?php
// Function to check if delivery time exceeds 20 minutes
function isDeliveryDelayed($order_datetime) {
    $order_time = strtotime($order_datetime);
    $current_time = time();
    $diff_minutes = ($current_time - $order_time) / 60;
    
    return $diff_minutes > 20;
}

// Function to format currency
function formatPrice($amount) {
    return '?' . number_format($amount, 2);
}

// Function to sanitize input
function sanitize($conn, $str) {
    return mysqli_real_escape_string($conn, trim($str));
}

// Function to check admin login
function checkAdminLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: ./index.php");
        exit();
    }
}

// Function to check customer login
function checkCustomerLogin() {
    if (!isset($_SESSION['customer_id'])) {
        header("Location: ./index.php");
        exit();
    }
}
?>