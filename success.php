<?php

session_start();
require_once "db.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit();
}


$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   GET ORDER ID
========================================================= */

$order_id = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;


if ($order_id <= 0) {

    header("Location: orders.php");
    exit();
}


/* =========================================================
   CHECK ORDER BELONGS TO USER
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        order_number
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

    $stmt->close();

    header("Location: orders.php");
    exit();
}


$order = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Order Confirmed
    </title>

    <link
        rel="stylesheet"
        href="css/success.css"
    >

</head>

<body>


<!-- =========================================================
     SUCCESS PAGE
========================================================= -->

<main class="success-page">

    <section class="success-card">


        <!-- =================================================
             ONE GREEN TICK ONLY
        ================================================== -->

        <div class="success-check">
            ✓
        </div>


        <!-- =================================================
             ONE ORDER CONFIRMED ONLY
        ================================================== -->

        <h1>
            Order Confirmed
        </h1>


        <!-- =================================================
             MESSAGE
        ================================================== -->

        <p class="success-message">
            Your order has been successfully placed.
        </p>


    </section>

</main>


<!-- =========================================================
     SUCCESS SOUND
========================================================= -->

<audio
    id="orderSuccessSound"
    src="sounds/success-chime.mp3"
    preload="auto"
></audio>


<script>

window.addEventListener(
    "load",
    function () {

        const successSound =
            document.getElementById(
                "orderSuccessSound"
            );


        /* =================================================
           PLAY SUCCESS SOUND
        ================================================= */

        if (successSound) {

            successSound.volume = 1.0;

            const playPromise =
                successSound.play();


            if (
                playPromise !== undefined
            ) {

                playPromise.catch(
                    function () {

                        /*
                         Browser autoplay
                         may block audio.
                        */

                    }
                );

            }

        }


        /* =================================================
           REDIRECT AFTER 3 SECONDS
        ================================================= */

        setTimeout(
            function () {

                window.location.href =
                    "orders.php";

            },
            3000
        );

    }
);

</script>


</body>
</html>