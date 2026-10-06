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
   CANCEL PAYMENT
========================================================= */

if (
    isset($_GET["cancel"]) &&
    $_GET["cancel"] === "1"
) {

    unset(
        $_SESSION["pending_upi_checkout"]
    );

    unset(
        $_SESSION["upi_id"]
    );

    header(
        "Location: checkout.php"
    );

    exit();
}


/* =========================================================
   CHECK PENDING CHECKOUT
========================================================= */

if (
    !isset(
        $_SESSION["pending_upi_checkout"]
    )
) {

    header(
        "Location: checkout.php"
    );

    exit();
}


$checkout =
    $_SESSION["pending_upi_checkout"];


/* =========================================================
   SECURITY CHECK
========================================================= */

if (
    (int)
    ($checkout["user_id"] ?? 0)
    !==
    $user_id
) {

    unset(
        $_SESSION["pending_upi_checkout"]
    );

    unset(
        $_SESSION["upi_id"]
    );

    header(
        "Location: checkout.php"
    );

    exit();
}


/* =========================================================
   SESSION EXPIRY
========================================================= */

if (
    isset(
        $checkout["created_at"]
    )
    &&
    (
        time()
        -
        (int)
        $checkout["created_at"]
    ) > 900
) {

    unset(
        $_SESSION["pending_upi_checkout"]
    );

    unset(
        $_SESSION["upi_id"]
    );

    header(
        "Location: checkout.php"
    );

    exit();
}


/* =========================================================
   GET APP
========================================================= */

$app =
    trim(
        $_GET["app"]
        ??
        ($checkout["payment_app"] ?? "Google Pay")
    );


$allowed_apps = [

    "Google Pay",
    "PhonePe",
    "Paytm"

];


if (
    !in_array(
        $app,
        $allowed_apps,
        true
    )
) {

    $app =
        "Google Pay";
}


/* =========================================================
   GET TOTAL
========================================================= */

$total =
    (float)
    ($checkout["total"] ?? 0);


/* =========================================================
   GET ERROR
========================================================= */

$error =
    isset($_GET["error"])
        ? trim(
            $_GET["error"]
        )
        : "";

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
        <?php
        echo htmlspecialchars(
            $app
        );
        ?>
    </title>


    <link
        rel="stylesheet"
        href="css/payment-app.css"
    >

</head>


<body>


<main class="payment-page">


    <section class="payment-card">


        <!-- =================================================
             APP HEADER
        ================================================== -->

        <div class="app-header">


            <?php if (
                $app === "Google Pay"
            ): ?>


                <div class="app-logo google-pay-logo">

                    <span class="g-letter">
                        G
                    </span>

                </div>


            <?php elseif (
                $app === "PhonePe"
            ): ?>


                <div class="app-logo phonepe-logo">

                    P

                </div>


            <?php else: ?>


                <div class="app-logo paytm-logo">

                    paytm

                </div>


            <?php endif; ?>


            <h1>

                <?php
                echo htmlspecialchars(
                    $app
                );
                ?>

            </h1>


            <span class="upi-label">
                UPI
            </span>


        </div>


        <!-- =================================================
             AMOUNT
        ================================================== -->

        <div class="amount-box">


            <small>
                Amount
            </small>


            <strong>

                ₹<?php

                echo number_format(
                    $total,
                    2
                );

                ?>

            </strong>


        </div>


        <!-- =================================================
             PAYMENT FORM
        ================================================== -->

        <form
            method="POST"
            action="complete_upi_payment.php"
            id="paymentForm"
            autocomplete="off"
        >


            <div class="upi-field">


                <label
                    for="upi_id"
                >

                    UPI ID

                </label>


                <input
                    type="text"
                    id="upi_id"
                    name="upi_id"
                    placeholder="example@upi"
                    maxlength="100"
                    autocomplete="off"
                    required
                >


                <small>
                    Enter your UPI ID
                </small>


            </div>


            <?php if (
                $error !== ""
            ): ?>


                <div class="payment-error">

                    <?php
                    echo htmlspecialchars(
                        $error
                    );
                    ?>

                </div>


            <?php endif; ?>


            <button
                type="submit"
                class="pay-button"
                id="payButton"
            >

                Pay ₹<?php

                echo number_format(
                    $total,
                    2
                );

                ?>

            </button>


        </form>


        <!-- =================================================
             CANCEL
        ================================================== -->

        <a
            href="payment-app.php?cancel=1"
            class="cancel-button"
        >

            Cancel

        </a>


        <p class="security-text">

            Secure UPI payment

        </p>


    </section>


</main>


<!-- =========================================================
     PROCESSING SCREEN
========================================================= -->

<div
    class="processing-screen"
    id="processingScreen"
>


    <div class="processing-box">


        <div class="spinner"></div>


        <h2>
            Processing Payment
        </h2>


        <p>
            Please wait...
        </p>


    </div>


</div>


<script>

/* =========================================================
   PAYMENT FORM
========================================================= */

const paymentForm =
    document.getElementById(
        "paymentForm"
    );


const payButton =
    document.getElementById(
        "payButton"
    );


const processingScreen =
    document.getElementById(
        "processingScreen"
    );


paymentForm.addEventListener(
    "submit",
    function(event) {


        /* ================================================
           VALIDATE UPI
        ================================================= */

        if (
            !paymentForm.checkValidity()
        ) {

            event.preventDefault();

            paymentForm.reportValidity();

            return;
        }


        /* ================================================
           SHOW PROCESSING
        ================================================= */

        event.preventDefault();


        processingScreen.classList.add(
            "active"
        );


        payButton.disabled =
            true;


        payButton.textContent =
            "Processing Payment...";


        /* ================================================
           SEND TO BACKEND
           
           Processing spinner:
           3 seconds

           Then:
           complete_upi_payment.php
               ↓
           payment-success.php
        ================================================= */

        setTimeout(
            function() {

                paymentForm.submit();

            },
            3000
        );

    }
);

</script>


</body>
</html>