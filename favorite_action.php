<?php

session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Please login to use Favorites."
    ]);
    exit();
}

$user_id = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);
    exit();
}

$food_id = isset($_POST["food_id"]) ? (int)$_POST["food_id"] : 0;

if ($food_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid food."
    ]);
    exit();
}


/* Check whether food is already in favorites */

$check = $conn->prepare(
    "SELECT id
     FROM favorites
     WHERE user_id = ? AND food_id = ?"
);

$check->bind_param("ii", $user_id, $food_id);
$check->execute();

$result = $check->get_result();


/* Remove from favorites */

if ($result->num_rows > 0) {

    $row = $result->fetch_assoc();

    $delete = $conn->prepare(
        "DELETE FROM favorites
         WHERE id = ?"
    );

    $delete->bind_param("i", $row["id"]);
    $delete->execute();

    $delete->close();
    $check->close();

    echo json_encode([
        "success" => true,
        "status" => "removed"
    ]);

    exit();
}


/* Add to favorites */

$insert = $conn->prepare(
    "INSERT INTO favorites (user_id, food_id)
     VALUES (?, ?)"
);

$insert->bind_param("ii", $user_id, $food_id);

if ($insert->execute()) {

    echo json_encode([
        "success" => true,
        "status" => "added"
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Unable to save favorite."
    ]);
}

$insert->close();
$check->close();

?>