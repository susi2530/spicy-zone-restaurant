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
        Payment Paid
    </title>


    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* =====================================================
           BODY
        ====================================================== */

        body {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f5f6f8;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

        }


        /* =====================================================
           PAYMENT SUCCESS PAGE
        ====================================================== */

        .payment-success {

            width: 100%;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        /* =====================================================
           CARD
        ====================================================== */

        .success-card {

            width: 100%;

            max-width: 360px;

            padding:
                38px
                25px;

            background: #ffffff;

            border-radius: 22px;

            text-align: center;

            box-shadow:
                0
                15px
                40px
                rgba(
                    0,
                    0,
                    0,
                    0.08
                );

        }


        /* =====================================================
           BLUE TICK
        ====================================================== */

        .blue-tick {

            width: 76px;

            height: 76px;

            margin:
                0
                auto
                18px;

            border-radius: 50%;

            background:
                #4285f4;

            display: flex;

            align-items: center;

            justify-content: center;

            box-shadow:
                0
                8px
                25px
                rgba(
                    66,
                    133,
                    244,
                    0.20
                );

            animation:
                tickAppear
                0.35s
                ease-out;

        }


        .blue-tick::after {

            content:
                "✓";

            color:
                #ffffff;

            font-size:
                45px;

            font-weight:
                700;

            line-height:
                1;

        }


        @keyframes tickAppear {

            from {

                transform:
                    scale(0.7);

                opacity:
                    0;

            }

            to {

                transform:
                    scale(1);

                opacity:
                    1;

            }

        }


        /* =====================================================
           PAID
        ====================================================== */

        .paid-text {

            color:
                #222222;

            font-size:
                25px;

            font-weight:
                700;

        }


        .paid-subtext {

            margin-top:
                7px;

            color:
                #777777;

            font-size:
                13px;

        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (
            max-width: 480px
        ) {

            .success-card {

                padding:
                    32px
                    20px;

                border-radius:
                    20px;

            }


            .blue-tick {

                width:
                    70px;

                height:
                    70px;

            }


            .blue-tick::after {

                font-size:
                    42px;

            }


            .paid-text {

                font-size:
                    22px;

            }

        }

    </style>

</head>


<body>


<div class="payment-success">


    <div class="success-card">


        <!-- =================================================
             BLUE PAYMENT TICK
        ================================================== -->

        <div
            class="blue-tick"
            aria-label="Payment successful"
        ></div>


        <!-- =================================================
             PAID TEXT
        ================================================== -->

        <div class="paid-text">

            Paid

        </div>


        <div class="paid-subtext">

            Payment successful

        </div>


    </div>


</div>


<!-- =========================================================
     PAYMENT SOUND
========================================================= -->

<audio
    id="paymentSound"
    preload="auto"
>
    <source
        src="sounds/upi-ting.mp3"
        type="audio/mpeg"
    >
</audio>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const paymentSound =
            document.getElementById(
                "paymentSound"
            );


        /* =================================================
           PLAY UPI SOUND
        ================================================= */

        if (paymentSound) {

            paymentSound.volume = 1.0;

            const playPromise =
                paymentSound.play();

            if (
                playPromise !== undefined
            ) {

                playPromise.catch(
                    function () {

                        console.log(
                            "UPI sound was blocked by the browser."
                        );

                    }
                );

            }

        }


        /* =================================================
           GO TO SUCCESS.PHP
           
           IMPORTANT:
           Pass order_id so success.php
           does not redirect directly
           to orders.php.
        ================================================= */

        const orderId =
            <?php
            echo (int)
                (
                    $_SESSION["upi_order_id"]
                    ??
                    0
                );
            ?>;


        setTimeout(
            function () {

                if (
                    orderId > 0
                ) {

                    window.location.href =
                        "success.php?order_id=" +
                        orderId;

                } else {

                    /*
                     * Fallback only if
                     * order id is missing.
                     */

                    window.location.href =
                        "orders.php";

                }

            },
            2500
        );

    }
);

</script>


</body>

</html>