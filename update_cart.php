<?php

session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cart.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$cart_id = isset($_POST["cart_id"]) ? (int)$_POST["cart_id"] : 0;
$quantity = isset($_POST["quantity"]) ? (int)$_POST["quantity"] : 1;

if ($cart_id <= 0) {
    header("Location: cart.php");
    exit();
}

if ($quantity < 1) {
    $quantity = 1;
}

$stmt = $conn->prepare(
    "UPDATE cart
     SET quantity = ?
     WHERE id = ? AND user_id = ?"
);

$stmt->bind_param(
    "iii",
    $quantity,
    $cart_id,
    $user_id
);

$stmt->execute();
$stmt->close();

header("Location: cart.php");
exit();

?>