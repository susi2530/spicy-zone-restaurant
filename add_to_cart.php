<?php

session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: menu.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$food_id = isset($_POST["food_id"]) ? (int)$_POST["food_id"] : 0;

if ($food_id <= 0) {
    header("Location: menu.php");
    exit();
}


/* Check whether food already exists in cart */

$check = $conn->prepare(
    "SELECT id, quantity
     FROM cart
     WHERE user_id = ? AND food_id = ?"
);

$check->bind_param("ii", $user_id, $food_id);
$check->execute();

$result = $check->get_result();


if ($result->num_rows > 0) {

    $cart_item = $result->fetch_assoc();

    $new_quantity = $cart_item["quantity"] + 1;

    $update = $conn->prepare(
        "UPDATE cart
         SET quantity = ?
         WHERE id = ?"
    );

    $update->bind_param(
        "ii",
        $new_quantity,
        $cart_item["id"]
    );

    $update->execute();

    $update->close();

} else {

    $insert = $conn->prepare(
        "INSERT INTO cart (user_id, food_id, quantity)
         VALUES (?, ?, 1)"
    );

    $insert->bind_param(
        "ii",
        $user_id,
        $food_id
    );

    $insert->execute();

    $insert->close();
}

$check->close();

header("Location: cart.php");
exit();

?>