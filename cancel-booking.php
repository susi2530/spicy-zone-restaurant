<?php

session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$booking_id = isset($_GET['booking_id'])
    ? (int)$_GET['booking_id']
    : 0;

if ($booking_id <= 0) {
    header("Location: my-bookings.php");
    exit();
}


/* ================= CHECK BOOKING ================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        status
     FROM reservations
     WHERE id = ?
       AND user_id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: my-bookings.php");
    exit();
}

$booking = $result->fetch_assoc();

$stmt->close();


/* ================= CANCEL ONLY CONFIRMED ================= */

if (strtolower($booking['status']) !== 'confirmed') {
    header("Location: my-bookings.php");
    exit();
}


/* ================= UPDATE STATUS ================= */

$cancel_stmt = $conn->prepare(
    "UPDATE reservations
     SET status = 'cancelled'
     WHERE id = ?
       AND user_id = ?
       AND status = 'confirmed'"
);

$cancel_stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);


if ($cancel_stmt->execute()) {

    header(
        "Location: my-bookings.php?cancelled=1"
    );

    exit();
}


$cancel_stmt->close();

header("Location: my-bookings.php");

exit();

?>