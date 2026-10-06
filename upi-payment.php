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
   GET ORDER
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        order_number,
        total,
        payment_method,
        payment_status,
        order_status,
        order_type
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


/* =========================================================
   ALREADY PAID
========================================================= */

if (
    strtolower((string)$order["payment_status"]) === "paid"
) {

    header(
        "Location: success.php?order_id=" .
        $order_id
    );

    exit();
}


/* =========================================================
   ERROR
========================================================= */

$error = "";


/* =========================================================
   PROCESS UPI PAYMENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $upi_id = trim(
        $_POST["upi_id"] ?? ""
    );


    /* ================= EMPTY ================= */

    if ($upi_id === "") {

        $error = "Please enter your UPI ID.";

    }


    /* ================= FORMAT ================= */

    elseif (
        !preg_match(
            '/^[a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+$/',
            $upi_id
        )
    ) {

        $error = "Please enter a valid UPI ID.";

    }


    /* ================= SUCCESS ================= */

    else {

        $update_stmt = $conn->prepare(
            "UPDATE orders
             SET
                payment_method = 'UPI',
                payment_status = 'Paid',
                order_status = 'CONFIRMED'
             WHERE id = ?
               AND user_id = ?"
        );


        $update_stmt->bind_param(
            "ii",
            $order_id,
            $user_id
        );


        if ($update_stmt->execute()) {

            $update_stmt->close();


            header(
                "Location: success.php?order_id=" .
                $order_id
            );

            exit();


        } else {

            $error =
                "Unable to process payment. Please try again.";

            $update_stmt->close();

        }

    }

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

    

    <link
        rel="stylesheet"
        href="css/upi-payment.css"
    >

</head>

<body>





<!-- =========================================================
     PAYMENT PAGE
========================================================= -->

<main class="payment-page">

    <section class="payment-card">


        <p class="payment-label">
            SECURE UPI PAYMENT
        </p>


        <h1>
            Complete Payment
        </h1>


        <p class="payment-subtitle">
            Pay securely to confirm your order.
        </p>


        <!-- ================= ORDER ================= -->

        <div class="order-info">


            <div>

                <span>
                    Order
                </span>

                <strong>

                    <?php
                    echo htmlspecialchars(
                        $order["order_number"]
                    );
                    ?>

                </strong>

            </div>


            <div>

                <span>
                    Amount
                </span>

                <strong>

                    ₹<?php
                    echo number_format(
                        (float)$order["total"],
                        2
                    );
                    ?>

                </strong>

            </div>


        </div>


        <!-- ================= ERROR ================= -->

        <?php if ($error !== ""): ?>

            <div class="payment-error">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- ================= FORM ================= -->

        <form
            method="POST"
            id="upiPaymentForm"
        >


            <div class="upi-box">


                <div class="upi-heading">

                    <div class="upi-icon">
                        UPI
                    </div>

                    <div>

                        <h2>
                            Pay using UPI
                        </h2>

                        <p>
                            Enter your UPI ID
                        </p>

                    </div>

                </div>


                <label for="upi_id">
                    UPI ID
                </label>


                <input
                    type="text"
                    id="upi_id"
                    name="upi_id"
                    placeholder="example@upi"
                    autocomplete="off"
                    required
                >


                <p class="hint">
                    Example: yourname@upi
                </p>


            </div>


            <!-- ================= PROCESSING ================= -->

            <div
                class="processing-box"
                id="processingBox"
            >

                <div class="spinner"></div>

                <h3>
                    Processing Payment...
                </h3>

                <p>
                    Please wait a moment.
                </p>

            </div>


            <!-- ================= PAY ================= -->

            <button
                type="submit"
                class="pay-btn"
                id="payBtn"
            >

                Pay ₹<?php
                echo number_format(
                    (float)$order["total"],
                    2
                );
                ?>

            </button>


        </form>


        <a
            href="orders.php"
            class="back-link"
        >
            Back to My Orders
        </a>


    </section>

</main>


<script>

const form =
    document.getElementById("upiPaymentForm");

const processing =
    document.getElementById("processingBox");

const payButton =
    document.getElementById("payBtn");


form.addEventListener(
    "submit",
    function(event) {

        event.preventDefault();


        /* Show processing */

        processing.classList.add("show");


        /* Disable button */

        payButton.disabled = true;

        payButton.textContent =
            "Processing...";


        /*
            Short payment simulation.
            After 3 seconds the actual POST
            happens and PHP updates the order.
        */

        setTimeout(
            function() {

                form.submit();

            },
            3000
        );

    }
);

</script>


</body>
</html>