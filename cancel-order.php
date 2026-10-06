<?php

session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$order_id = isset($_GET['order_id'])
    ? (int)$_GET['order_id']
    : 0;

if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}


/* ================= CHECK ORDER ================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        order_number,
        order_status
     FROM orders
     WHERE id = ?
       AND user_id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: orders.php");
    exit();
}

$order = $result->fetch_assoc();

$stmt->close();


/* ================= CANCEL CHECK ================= */

$status = strtoupper($order['order_status']);

if ($status !== 'CONFIRMED') {
    header("Location: orders.php");
    exit();
}


/* ================= CANCEL ORDER ================= */

$cancel_stmt = $conn->prepare(
    "UPDATE orders
     SET order_status = 'CANCELLED'
     WHERE id = ?
       AND user_id = ?
       AND order_status = 'CONFIRMED'"
);

$cancel_stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);

if ($cancel_stmt->execute()) {

    header(
        "Location: orders.php?cancelled=1"
    );

    exit();

}

$cancel_stmt->close();

header("Location: orders.php");

exit();

?>