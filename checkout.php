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
   GET USER
========================================================= */
$user_stmt = $conn->prepare(
    "SELECT
        name,
        email,
        mobile,
        address
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$user_stmt) {
    die("Unable to load user information.");
}

$user_stmt->bind_param(
    "i",
    $user_id
);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();

if (!$user) {
    header("Location: login.php");
    exit();
}

/* =========================================================
   GET CART
========================================================= */
$cart_stmt = $conn->prepare(
    "SELECT
        cart.id AS cart_id,
        cart.food_id,
        cart.quantity,
        foods.name,
        foods.price,
        foods.image
     FROM cart
     INNER JOIN foods
        ON cart.food_id = foods.id
     WHERE cart.user_id = ?
       AND foods.is_available = 1
     ORDER BY cart.created_at ASC"
);

if (!$cart_stmt) {
    die("Unable to load cart.");
}

$cart_stmt->bind_param(
    "i",
    $user_id
);

$cart_stmt->execute();

$cart_result = $cart_stmt->get_result();

$cart_items = [];
$subtotal = 0;

while (
    $item =
    $cart_result->fetch_assoc()
) {
    $item_total =
        (float) $item["price"] *
        (int) $item["quantity"];

    $item["item_total"] =
        $item_total;

    $subtotal +=
        $item_total;

    $cart_items[] =
        $item;
}

$cart_stmt->close();

/* =========================================================
   EMPTY CART
========================================================= */
if (
    count($cart_items) === 0
) {
    header(
        "Location: cart.php"
    );
    exit();
}

/* =========================================================
   CHECK FIRST ORDER
========================================================= */
$order_count = 0;

$order_stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_orders
     FROM orders
     WHERE user_id = ?"
);

if (!$order_stmt) {
    die("Unable to check order history.");
}

$order_stmt->bind_param(
    "i",
    $user_id
);

$order_stmt->execute();

$order_result =
    $order_stmt->get_result();

if (
    $row =
    $order_result->fetch_assoc()
) {
    $order_count =
        (int) $row["total_orders"];
}

$order_stmt->close();

/* =========================================================
   AUTOMATIC DISCOUNT
========================================================= */
$discount = 0;
$discount_label =
    "No Discount";

if (
    $order_count === 0
) {
    $discount =
        $subtotal * 0.10;

    $discount_label =
        "10% OFF";

} elseif (
    $subtotal >= 500
) {
    $discount =
        $subtotal * 0.20;

    $discount_label =
        "20% OFF";
}

$final_total =
    $subtotal - $discount;

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
        Checkout - Spicy Zone Restaurant
    </title>

    <link
        rel="stylesheet"
        href="css/checkout.css"
    >

    <style>

        /* =================================================
           TABLE AVAILABILITY MESSAGE
        ================================================== */
        .checkout-table-message {

            width: 100%;

            padding:
                13px 15px;

            margin-top: 12px;

            border-radius: 6px;

            background:
                #fafafa;

            border:
                1px solid
                #e1e1e1;

            color:
                #777777;

            font-size:
                12px;

            line-height:
                1.4;
        }


        .checkout-table-message.error {

            background:
                #fff4f4;

            border-color:
                #efcaca;

            color:
                #b33b3b;
        }


        .checkout-table-message.success {

            background:
                #eef8ee;

            border-color:
                #cde3ce;

            color:
                #318235;
        }


        /* =================================================
           TABLE GRID
        ================================================== */
        .checkout-tables {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap:
                12px;

            margin-top:
                15px;
        }


        /* =================================================
           TABLE CARD
        ================================================== */
        .checkout-table-card {

            position:
                relative;

            display:
                block;

            padding:
                12px;

            border:
                1px solid
                #dfdfdf;

            border-radius:
                7px;

            background:
                #ffffff;

            cursor:
                pointer;

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }


        .checkout-table-card:hover {

            border-color:
                #3a8f3a;
        }


        .checkout-table-card.selected {

            border-color:
                #3a8f3a;

            background:
                #f4fbf4;

            box-shadow:
                0
                0
                0
                2px
                rgba(
                    58,
                    143,
                    58,
                    0.08
                );
        }


        .checkout-table-card.booked {

            cursor:
                not-allowed;

            background:
                #f7f7f7;

            border-color:
                #dddddd;

            opacity:
                0.72;
        }


        .checkout-table-card.disabled {

            cursor:
                not-allowed;

            background:
                #f7f7f7;

            border-color:
                #e1e1e1;

            opacity:
                0.65;
        }


        .checkout-table-card input {

            position:
                absolute;

            opacity:
                0;

            pointer-events:
                none;
        }


        /* =================================================
           TABLE IMAGE
        ================================================== */
        .checkout-table-image {

            width:
                100%;

            height:
                125px;

            margin-bottom:
                10px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            overflow:
                hidden;

            border-radius:
                6px;

            background:
                #ffffff;
        }


        .checkout-table-image img {

            width:
                100%;

            height:
                100%;

            display:
                block;

            object-fit:
                contain;
        }


        /* =================================================
           TABLE INFO
        ================================================== */
        .checkout-table-info {

            text-align:
                left;
        }


        .checkout-table-info h3 {

            margin-bottom:
                5px;

            color:
                #333333;

            font-size:
                14px;

            font-weight:
                700;
        }


        .checkout-table-info p {

            margin-bottom:
                6px;

            color:
                #888888;

            font-size:
                10px;
        }


        .checkout-table-status {

            display:
                inline-flex;

            padding:
                4px 7px;

            border-radius:
                4px;

            font-size:
                8px;

            font-weight:
                700;
        }


        .checkout-table-status.available {

            background:
                #eef8ee;

            color:
                #318235;
        }


        .checkout-table-status.booked {

            background:
                #fff1f1;

            color:
                #b33b3b;
        }


        .selected-table-summary {

            margin-top:
                10px;

            color:
                #666666;

            font-size:
                11px;

            line-height:
                1.5;
        }


        /* =================================================
           PAYMENT APP OPTIONS
        ================================================== */
        .payment-app-options {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap:
                7px;

            margin-top:
                14px;
        }


        /* =================================================
           PAYMENT APP CARD
        ================================================== */
        .payment-app-card {

            position:
                relative;

            min-height:
                64px;

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                9px 10px;

            border:
                1px solid
                #dddddd;

            border-radius:
                7px;

            background:
                #ffffff;

            cursor:
                pointer;

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }


        /* =================================================
           HIDE RADIO
        ================================================== */
        .payment-app-card input {

            position:
                absolute;

            opacity:
                0;

            pointer-events:
                none;
        }


        /* =================================================
           SELECTED APP
        ================================================== */
        .payment-app-card:has(
            input:checked
        ) {

            border-color:
                #3a8f3a;

            background:
                #f7fcf7;

            box-shadow:
                0
                0
                0
                1px
                rgba(
                    58,
                    143,
                    58,
                    0.12
                );
        }


        /* =================================================
           COMMON ICON
        ================================================== */
        .app-icon {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex:
                0 0 auto;
        }


        /* =================================================
           GOOGLE PAY
        ================================================== */
        .google-pay-icon {

            width:
                34px;

            height:
                34px;

            border-radius:
                7px;

            background:
                linear-gradient(
                    135deg,
                    #4285f4 0%,
                    #34a853 35%,
                    #fbbc05 68%,
                    #ea4335 100%
                );
        }


        .google-pay-letter {

            width:
                25px;

            height:
                25px;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #ffffff;

            color:
                #4285f4;

            font-size:
                16px;

            font-weight:
                700;
        }


        /* =================================================
           PHONEPE
        ================================================== */
        .phonepe-icon {

            width:
                34px;

            height:
                34px;

            border-radius:
                7px;

            background:
                #5f259f;

            color:
                #ffffff;

            font-size:
                17px;

            font-weight:
                700;
        }


        /* =================================================
           PAYTM
        ================================================== */
        .paytm-icon {

            width:
                58px;

            height:
                34px;

            border-radius:
                7px;

            background:
                #eaf8ff;

            color:
                #00a9e0;

            font-size:
                12px;

            font-weight:
                800;

            letter-spacing:
                -0.5px;
        }


        /* =================================================
           APP TEXT
        ================================================== */
        .payment-app-text {

            min-width:
                0;

            display:
                flex;

            flex-direction:
                column;

            gap:
                2px;
        }


        .payment-app-text strong {

            color:
                #222222;

            font-size:
                11px;

            font-weight:
                700;

            line-height:
                1.1;
        }


        .payment-app-text small {

            color:
                #777777;

            font-size:
                8px;

            line-height:
                1.1;

            white-space:
                nowrap;
        }


        .payment-app-card:hover {

            border-color:
                #aaaaaa;
        }


        /* =================================================
           RESPONSIVE
        ================================================== */
        .checkout-end-time-note {

            margin-top:
                5px;

            color:
                #777777;

            font-size:
                11px;

            line-height:
                1.4;
        }


        .checkout-note {

            margin:
                8px 0 0;

            color:
                #666666;

            font-size:
                12px;

            line-height:
                1.45;

            text-align:
                left;
        }


        @media (
            max-width: 650px
        ) {

            .checkout-tables {

                grid-template-columns:
                    1fr;
            }


            .checkout-table-image {

                height:
                    135px;
            }


            .payment-app-options {

                grid-template-columns:
                    1fr;
            }


            .payment-app-card {

                min-height:
                    58px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="navbar">

    <div class="brand">

        <a href="index.php">

            <img
                src="images/logo.png"
                alt="Spicy Zone Logo"
            >

        </a>

        <span>
            Spicy Zone Restaurant
        </span>

    </div>


    <nav class="nav-links">

        <a href="index.php">
            Home
        </a>

        <a href="menu.php">
            Menu
        </a>

        <a href="mood.php">
            Mood
        </a>

        <a href="favorites.php">
            Favorites
        </a>

        <a href="cart.php">
            Cart
        </a>

        <a href="my-bookings.php">
            My Bookings
        </a>

        <a href="profile.php">
            Profile
        </a>

    </nav>

</header>


<!-- =========================================================
     HEADER
========================================================= -->

<section class="checkout-header">

    <p class="small-heading">
        SPICY ZONE
    </p>

    <h1>
        Checkout
    </h1>

    <p>
        Complete your order details before placing your order.
    </p>

</section>


<!-- =========================================================
     CHECKOUT
========================================================= -->

<main class="checkout-page">

    <form
        method="POST"
        action="place_order.php"
        id="checkoutForm"
    >

        <div class="checkout-layout">


            <!-- =================================================
                 LEFT
            ================================================== -->

            <div class="checkout-left">


                <!-- ================= ADDRESS ================= -->

                <section class="checkout-card">

                    <div class="card-title-row">

                        <h2>
                            Delivery Address
                        </h2>

                        <a href="edit-profile.php">
                            Edit
                        </a>

                    </div>


                    <?php if (
                        !empty(
                            $user["address"]
                        )
                    ): ?>

                        <div class="address-box">

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $user["name"]
                                );
                                ?>

                            </strong>


                            <p>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $user["address"]
                                    )
                                );
                                ?>

                            </p>


                            <p>

                                <?php
                                echo htmlspecialchars(
                                    $user["mobile"]
                                );
                                ?>

                            </p>

                        </div>


                    <?php else: ?>


                        <div class="no-address">

                            <p>
                                No saved address found.
                            </p>

                            <a href="edit-profile.php">
                                Add Address
                            </a>

                        </div>


                    <?php endif; ?>

                </section>


                <!-- ================= ORDER TYPE ================= -->

                <section class="checkout-card">

                    <h2>
                        Order Type
                    </h2>


                    <div class="order-type-options">


                        <label class="option-card">

                            <input
                                type="radio"
                                name="order_type"
                                value="delivery"
                                checked
                            >

                            <div>

                                <strong>
                                    Delivery
                                </strong>

                                <p>
                                    Get your food delivered to your saved address.
                                </p>

                            </div>

                        </label>


                        <label class="option-card">

                            <input
                                type="radio"
                                name="order_type"
                                value="takeaway"
                            >

                            <div>

                                <strong>
                                    Takeaway
                                </strong>

                                <p>
                                    Order online and collect it from the restaurant.
                                </p>

                            </div>

                        </label>


                        <label class="option-card">

                            <input
                                type="radio"
                                name="order_type"
                                value="dine_in"
                                id="dineInOption"
                            >

                            <div>

                                <strong>
                                    Dine-in
                                </strong>

                                <p>
                                    Reserve a table and enjoy your meal at the restaurant.
                                </p>

                            </div>

                        </label>


                    </div>

                </section>


                <!-- ================= DINE IN ================= -->

                <section
                    class="checkout-card"
                    id="dineInSection"
                    style="display: none;"
                >

                    <h2>
                        Dine-in Details
                    </h2>


                    <div class="form-group">

                        <label for="reservation_date">
                            Date
                        </label>

                        <input
                            type="date"
                            name="reservation_date"
                            id="reservation_date"
                            min="<?php echo date('Y-m-d'); ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="reservation_time">
                            Start Time
                        </label>

                        <input
                            type="time"
                            name="reservation_time"
                            id="reservation_time"
                        >

                    </div>


                    <div class="form-group">

                        <label for="reservation_end_time">
                            End Time
                        </label>

                        <input
                            type="time"
                            id="reservation_end_time"
                            readonly
                        >

                        <p class="checkout-end-time-note">
                            Estimated end time for table availability
                        </p>

                    </div>


                    <div class="form-group">

                        <label for="guests">
                            Number of Guests
                        </label>

                        <input
                            type="number"
                            name="guests"
                            id="guests"
                            min="1"
                            max="100"
                            placeholder="Enter number of guests"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Select Tables
                        </label>


                        <div
                            id="checkoutTableMessage"
                            class="checkout-table-message"
                        >
                            Select date and time to see table availability.
                        </div>


                        <div
                            id="checkoutTables"
                            class="checkout-tables"
                        >
                        </div>


                        <p
                            id="selectedTableSummary"
                            class="selected-table-summary"
                        >
                            No tables selected.
                        </p>


                        <!-- ================= DINE-IN NOTE ================= -->

                        <p class="checkout-note">
                            💡 <strong>Note:</strong> The selected time is your booking start time.
                            The displayed end time is only an estimated time slot for table availability
                            and is not a strict dining time limit.
                        </p>

                    </div>


                </section>


                <!-- =================================================
                     PAYMENT METHOD
                ================================================== -->

                <section class="checkout-card">

                    <h2>
                        Payment Method
                    </h2>


                    <div class="payment-options">


                        <!-- ================= ONLINE ================= -->

                        <label class="payment-option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="online"
                                id="onlinePayment"
                                checked
                            >


                            <div class="payment-option-content">

                                <strong>
                                    Online Payment
                                </strong>

                                <p>
                                    Pay securely using a UPI app.
                                </p>


                                <div
                                    class="payment-app-options"
                                    id="paymentAppOptions"
                                >


                                    <!-- ================= GOOGLE PAY ================= -->

                                    <label class="payment-app-card">

                                        <input
                                            type="radio"
                                            name="payment_app"
                                            value="Google Pay"
                                            checked
                                        >


                                        <span
                                            class="app-icon google-pay-icon"
                                        >

                                            <span
                                                class="google-pay-letter"
                                            >
                                                G
                                            </span>

                                        </span>


                                        <span
                                            class="payment-app-text"
                                        >

                                            <strong>
                                                Google Pay
                                            </strong>

                                            <small>
                                                Pay using UPI
                                            </small>

                                        </span>

                                    </label>


                                    <!-- ================= PHONEPE ================= -->

                                    <label class="payment-app-card">

                                        <input
                                            type="radio"
                                            name="payment_app"
                                            value="PhonePe"
                                        >


                                        <span
                                            class="app-icon phonepe-icon"
                                        >
                                            P
                                        </span>


                                        <span
                                            class="payment-app-text"
                                        >

                                            <strong>
                                                PhonePe
                                            </strong>

                                            <small>
                                                Pay using UPI
                                            </small>

                                        </span>

                                    </label>


                                    <!-- ================= PAYTM ================= -->

                                    <label class="payment-app-card">

                                        <input
                                            type="radio"
                                            name="payment_app"
                                            value="Paytm"
                                        >


                                        <span
                                            class="app-icon paytm-icon"
                                        >
                                            paytm
                                        </span>


                                        <span
                                            class="payment-app-text"
                                        >

                                            <strong>
                                                Paytm
                                            </strong>

                                            <small>
                                                Pay using UPI
                                            </small>

                                        </span>

                                    </label>


                                </div>

                            </div>

                        </label>


                        <!-- ================= COD ================= -->

                        <label class="payment-option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="cod"
                                id="codPayment"
                            >


                            <div class="payment-option-content">

                                <strong>
                                    Cash on Delivery
                                </strong>

                                <p>
                                    Pay when your order is delivered.
                                </p>

                            </div>

                        </label>


                    </div>

                </section>


            </div>


            <!-- =================================================
                 RIGHT SUMMARY
            ================================================== -->

            <aside class="checkout-summary">

                <h2>
                    Order Summary
                </h2>


                <div class="summary-items">


                    <?php
                    foreach (
                        $cart_items as $item
                    ):
                    ?>

                        <div class="summary-item">

                            <div>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $item["name"]
                                    );
                                    ?>

                                </strong>


                                <p>

                                    Qty:

                                    <?php
                                    echo (int)
                                        $item["quantity"];
                                    ?>

                                </p>

                            </div>


                            <span>

                                ₹<?php
                                echo number_format(
                                    $item["item_total"],
                                    2
                                );
                                ?>

                            </span>

                        </div>


                    <?php endforeach; ?>


                </div>


                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <strong>

                        ₹<?php
                        echo number_format(
                            $subtotal,
                            2
                        );
                        ?>

                    </strong>

                </div>


                <div class="summary-row discount">

                    <span>

                        Discount

                        <?php if (
                            $discount > 0
                        ): ?>

                            (
                            <?php
                            echo htmlspecialchars(
                                $discount_label
                            );
                            ?>
                            )

                        <?php endif; ?>

                    </span>


                    <strong>

                        -₹<?php
                        echo number_format(
                            $discount,
                            2
                        );
                        ?>

                    </strong>

                </div>


                <div class="automatic-discount">

                    <?php if (
                        $order_count === 0
                    ): ?>

                        First order 10% discount applied automatically.

                    <?php elseif (
                        $subtotal >= 500
                    ): ?>

                        20% discount applied automatically.

                    <?php else: ?>

                        No discount applicable for this order.

                    <?php endif; ?>

                </div>


                <div class="final-total">

                    <span>
                        Total
                    </span>

                    <strong>

                        ₹<?php
                        echo number_format(
                            $final_total,
                            2
                        );
                        ?>

                    </strong>

                </div>


                <button
                    type="submit"
                    class="place-order-btn"
                >
                    Place Order
                </button>


                <a
                    href="cart.php"
                    class="back-cart-btn"
                >
                    Back to Cart
                </a>


            </aside>


        </div>

    </form>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>
        © 2026 Spicy Zone Restaurant.
        All Rights Reserved.
    </p>

</footer>


<script>

/* =========================================================
   ELEMENTS
========================================================= */

const dineInOption =
    document.getElementById(
        "dineInOption"
    );


const dineInSection =
    document.getElementById(
        "dineInSection"
    );


const bookingDate =
    document.getElementById(
        "reservation_date"
    );


const bookingTime =
    document.getElementById(
        "reservation_time"
    );


const bookingEndTime =
    document.getElementById(
        "reservation_end_time"
    );


function updateCheckoutEndTime() {

    if (!bookingTime.value) {

        bookingEndTime.value = "";

        return;
    }


    const parts =
        bookingTime.value.split(":");


    const date =
        new Date();


    date.setHours(
        parseInt(parts[0], 10),
        parseInt(parts[1], 10),
        0,
        0
    );


    date.setMinutes(
        date.getMinutes() + 30
    );


    bookingEndTime.value =
        String(
            date.getHours()
        ).padStart(2, "0") +
        ":" +
        String(
            date.getMinutes()
        ).padStart(2, "0");
}


const guestsInput =
    document.getElementById(
        "guests"
    );


const checkoutTables =
    document.getElementById(
        "checkoutTables"
    );


const checkoutTableMessage =
    document.getElementById(
        "checkoutTableMessage"
    );


const selectedTableSummary =
    document.getElementById(
        "selectedTableSummary"
    );


/* =========================================================
   GET TABLE IMAGE
========================================================= */

function getTableImage(seats) {

    switch (
        parseInt(seats, 10)
    ) {

        case 2:
            return "table-2.png";

        case 4:
            return "table-4.png";

        case 7:
            return "table-7.png";

        case 10:
            return "table-10.png";

        default:
            return "";
    }
}


/* =========================================================
   UPDATE SELECTED TABLE SUMMARY
========================================================= */

function updateSelectedTableSummary() {

    const selected =
        document.querySelectorAll(
            'input[name="table_ids[]"]:checked'
        );


    let totalSeats = 0;

    const selectedNames = [];


    selected.forEach(
        function(checkbox) {

            totalSeats +=
                parseInt(
                    checkbox.getAttribute(
                        "data-seats"
                    ),
                    10
                ) || 0;


            selectedNames.push(
                checkbox.getAttribute(
                    "data-table-name"
                )
            );

        }
    );


    if (
        selected.length === 0
    ) {

        selectedTableSummary.textContent =
            "No tables selected.";

        return;
    }


    selectedTableSummary.textContent =
        selected.length +
        " table(s) selected: " +
        selectedNames.join(", ") +
        " • Total seats: " +
        totalSeats;
}


/* =========================================================
   DINE-IN TOGGLE
========================================================= */

document
    .querySelectorAll(
        'input[name="order_type"]'
    )
    .forEach(
        function(option) {

            option.addEventListener(
                "change",
                function() {

                    if (
                        this.value ===
                        "dine_in"
                    ) {

                        dineInSection.style.display =
                            "block";

                        loadCheckoutTables();

                    } else {

                        dineInSection.style.display =
                            "none";

                        checkoutTables.innerHTML =
                            "";

                        checkoutTableMessage.textContent =
                            "Select Dine-in to choose tables.";

                        checkoutTableMessage.className =
                            "checkout-table-message";

                        selectedTableSummary.textContent =
                            "No tables selected.";
                    }

                }
            );

        }
    );


/* =========================================================
   LOAD CHECKOUT TABLES
========================================================= */

function loadCheckoutTables() {

    const date =
        bookingDate.value;


    const time =
        bookingTime.value;


    if (
        date === "" ||
        time === ""
    ) {

        checkoutTables.innerHTML =
            "";

        checkoutTableMessage.textContent =
            "Select date and time to see table availability.";

        checkoutTableMessage.className =
            "checkout-table-message";

        selectedTableSummary.textContent =
            "No tables selected.";

        return;
    }


    checkoutTables.innerHTML =
        "";


    checkoutTableMessage.textContent =
        "Checking table availability...";


    checkoutTableMessage.className =
        "checkout-table-message";


    fetch(
        "check-table-availability.php?date=" +
        encodeURIComponent(date) +
        "&time=" +
        encodeURIComponent(time)
    )

    .then(
        function(response) {

            if (!response.ok) {

                throw new Error(
                    "Table availability request failed."
                );
            }

            return response.json();
        }
    )

    .then(
        function(data) {

            if (
                !data.success
            ) {

                checkoutTableMessage.textContent =
                    "Unable to check table availability.";

                checkoutTableMessage.className =
                    "checkout-table-message error";

                return;
            }


            checkoutTables.innerHTML =
                "";


            let availableCount = 0;


            data.tables.forEach(
                function(table) {

                    const isBooked =
                        parseInt(
                            table.is_booked,
                            10
                        ) === 1;


                    const image =
                        getTableImage(
                            table.seats
                        );


                    const card =
                        document.createElement(
                            "label"
                        );


                    card.className =
                        "checkout-table-card";


                    if (
                        isBooked
                    ) {

                        card.classList.add(
                            "booked"
                        );
                    }


                    card.innerHTML = `

                        <input
                            type="checkbox"
                            name="table_ids[]"
                            value="${table.id}"
                            data-seats="${table.seats}"
                            data-table-name="${table.table_number}"
                            ${isBooked ? "disabled" : ""}
                        >

                        <div class="checkout-table-image">

                            ${
                                image !== ""
                                ? `

                                    <img
                                        src="images/${image}"
                                        alt="${table.table_number}"
                                    >

                                  `
                                : ""
                            }

                        </div>


                        <div class="checkout-table-info">

                            <h3>
                                ${table.table_number}
                            </h3>


                            <p>
                                ${table.seats} Seats
                            </p>


                            <span
                                class="
                                    checkout-table-status
                                    ${
                                        isBooked
                                            ? "booked"
                                            : "available"
                                    }
                                "
                            >

                                ${
                                    isBooked
                                        ? "Already Booked"
                                        : "Available"
                                }

                            </span>

                        </div>

                    `;


                    if (
                        !isBooked
                    ) {

                        availableCount++;
                    }


                    checkoutTables.appendChild(
                        card
                    );

                }
            );


            if (
                availableCount === 0
            ) {

                checkoutTableMessage.textContent =
                    "All tables are already booked for the selected date and time. Please choose another time.";

                checkoutTableMessage.className =
                    "checkout-table-message error";

            } else {

                checkoutTableMessage.textContent =
                    "Available tables are shown below.";

                checkoutTableMessage.className =
                    "checkout-table-message success";
            }


            updateSelectedTableSummary();

        }
    )

    .catch(
        function(error) {

            console.error(
                error
            );


            checkoutTableMessage.textContent =
                "Unable to check table availability. Please try again.";

            checkoutTableMessage.className =
                "checkout-table-message error";
        }
    );
}


/* =========================================================
   TABLE SELECTION
========================================================= */

checkoutTables.addEventListener(
    "change",
    function(event) {

        const checkbox =
            event.target;


        if (
            !checkbox.matches(
                'input[name="table_ids[]"]'
            )
        ) {

            return;
        }


        const card =
            checkbox.closest(
                ".checkout-table-card"
            );


        if (
            checkbox.checked
        ) {

            card.classList.add(
                "selected"
            );

        } else {

            card.classList.remove(
                "selected"
            );
        }


        updateSelectedTableSummary();

    }
);


/* =========================================================
   DATE
========================================================= */

bookingDate.addEventListener(
    "change",
    function() {

        if (
            dineInOption.checked
        ) {

            loadCheckoutTables();
        }

    }
);


/* =========================================================
   TIME
========================================================= */

bookingTime.addEventListener(
    "change",
    function() {

        updateCheckoutEndTime();


        if (
            dineInOption.checked
        ) {

            loadCheckoutTables();
        }

    }
);


/* =========================================================
   GUESTS
========================================================= */

guestsInput.addEventListener(
    "input",
    function() {

        updateSelectedTableSummary();

    }
);


/* =========================================================
   PAYMENT APP SHOW / HIDE
========================================================= */

const onlinePayment =
    document.getElementById(
        "onlinePayment"
    );


const codPayment =
    document.getElementById(
        "codPayment"
    );


const paymentAppOptions =
    document.getElementById(
        "paymentAppOptions"
    );


function updatePaymentApps() {

    if (
        onlinePayment.checked
    ) {

        paymentAppOptions.style.display =
            "grid";

    } else {

        paymentAppOptions.style.display =
            "none";
    }
}


onlinePayment.addEventListener(
    "change",
    updatePaymentApps
);


codPayment.addEventListener(
    "change",
    updatePaymentApps
);


updatePaymentApps();


/* =========================================================
   FORM VALIDATION
========================================================= */

document
    .getElementById(
        "checkoutForm"
    )
    .addEventListener(
        "submit",
        function(event) {


            /* ================= DINE-IN ================= */

            if (
                dineInOption.checked
            ) {

                const selectedTables =
                    document.querySelectorAll(
                        'input[name="table_ids[]"]:checked'
                    );


                if (
                    !bookingDate.value ||
                    !bookingTime.value ||
                    !guestsInput.value
                ) {

                    event.preventDefault();

                    alert(
                        "Please complete all dine-in details."
                    );

                    return;
                }


                if (
                    selectedTables.length === 0
                ) {

                    event.preventDefault();

                    alert(
                        "Please select at least one available table."
                    );

                    return;
                }

            }


            /* ================= ONLINE PAYMENT ================= */

            if (
                onlinePayment.checked
            ) {

                const selectedApp =
                    document.querySelector(
                        'input[name="payment_app"]:checked'
                    );


                if (
                    !selectedApp
                ) {

                    event.preventDefault();

                    alert(
                        "Please select a payment app."
                    );

                    return;
                }

            }

        }
    );


updateCheckoutEndTime();

</script>


</body>

</html>