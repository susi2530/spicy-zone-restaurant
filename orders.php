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


$user_id =
    (int) $_SESSION["user_id"];


/* =========================================================
   GET USER ORDERS
========================================================= */

$order_stmt = $conn->prepare(
    "SELECT
        id,
        order_number,
        subtotal,
        discount,
        total,
        payment_method,
        payment_status,
        order_status,
        order_type,
        created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC"
);


if (!$order_stmt) {

    die(
        "Unable to load orders."
    );
}


$order_stmt->bind_param(
    "i",
    $user_id
);


$order_stmt->execute();


$order_result =
    $order_stmt->get_result();

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
        My Orders - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/orders.css"
    >


    <style>

        .nav-links a.active {
            color: #3a8f3a;
            font-weight: 700;
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


        <a
            href="orders.php"
            class="active"
        >
            My Orders
        </a>


        <a href="profile.php">
            Profile
        </a>


    </nav>


</header>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<section class="orders-header">


    <p class="small-heading">
        YOUR ACCOUNT
    </p>


    <h1>
        My Orders
    </h1>


    <p>
        View your recent and previous food orders.
    </p>


</section>


<!-- =========================================================
     ORDERS
========================================================= -->

<main class="orders-page">


<?php if (
    $order_result->num_rows > 0
): ?>


    <?php while (
        $order =
        $order_result->fetch_assoc()
    ): ?>


        <?php

        /* =====================================================
           ITEM COUNT
        ===================================================== */

        $item_stmt = $conn->prepare(
            "SELECT
                SUM(quantity) AS total_items,
                COUNT(DISTINCT food_id) AS different_foods
             FROM order_items
             WHERE order_id = ?"
        );


        $item_stmt->bind_param(
            "i",
            $order["id"]
        );


        $item_stmt->execute();


        $item_result =
            $item_stmt->get_result();


        $item_row =
            $item_result->fetch_assoc();


        $total_items =
            (int)
            (
                $item_row["total_items"]
                ?? 0
            );


        $different_foods =
            (int)
            (
                $item_row["different_foods"]
                ?? 0
            );


        $item_stmt->close();


        /* =====================================================
           ORDER TYPE
        ===================================================== */

        switch (
            strtoupper(
                (string)
                $order["order_type"]
            )
        ) {

            case "DELIVERY":

                $display_order_type =
                    "Delivery";

                break;


            case "TAKEAWAY":

                $display_order_type =
                    "Takeaway";

                break;


            case "DINE_IN":

                $display_order_type =
                    "Dine-in";

                break;


            default:

                $display_order_type =
                    "Order";
        }


        /* =====================================================
           PAYMENT
        ===================================================== */

        if (
            $order["payment_method"] ===
            "UPI"
        ) {

            $display_payment =
                "UPI";

        } else {

            $display_payment =
                "Cash on Delivery";
        }


        /* =====================================================
           STATUS
        ===================================================== */

        $raw_status =
            strtoupper(
                trim(
                    (string)
                    $order["order_status"]
                )
            );


        /* =====================================================
           ORDER-TYPE TRACKING
        ===================================================== */

        $order_type_upper =
            strtoupper(
                (string)
                $order["order_type"]
            );


        $show_tracking =
            $raw_status !==
            "CANCELLED";


        $tracking_step =
            1;


        $tracking_labels = [];


        /* ================= DELIVERY ================= */

        if (
            $order_type_upper ===
            "DELIVERY"
        ) {

            $tracking_labels = [

                "Confirmed",
                "Preparing",
                "Ready",
                "Out for Delivery",
                "Delivered"

            ];


            switch (
                $raw_status
            ) {

                case "CONFIRMED":

                    $display_status =
                        "Confirmed";

                    $tracking_step =
                        1;

                    break;


                case "PREPARING":

                    $display_status =
                        "Preparing";

                    $tracking_step =
                        2;

                    break;


                case "READY":

                    $display_status =
                        "Ready";

                    $tracking_step =
                        3;

                    break;


                case "OUT_FOR_DELIVERY":

                    $display_status =
                        "Out for Delivery";

                    $tracking_step =
                        4;

                    break;


                case "COMPLETED":

                    $display_status =
                        "Delivered";

                    $tracking_step =
                        5;

                    break;


                case "CANCELLED":

                    $display_status =
                        "Cancelled";

                    $tracking_step =
                        0;

                    break;


                default:

                    $display_status =
                        ucfirst(
                            strtolower(
                                $order[
                                    "order_status"
                                ]
                            )
                        );

                    $tracking_step =
                        1;
            }


        /* ================= TAKEAWAY ================= */

        } elseif (
            $order_type_upper ===
            "TAKEAWAY"
        ) {

            $tracking_labels = [

                "Confirmed",
                "Preparing",
                "Ready for Pickup",
                "Picked Up"

            ];


            switch (
                $raw_status
            ) {

                case "CONFIRMED":

                    $display_status =
                        "Confirmed";

                    $tracking_step =
                        1;

                    break;


                case "PREPARING":

                    $display_status =
                        "Preparing";

                    $tracking_step =
                        2;

                    break;


                case "READY":

                    $display_status =
                        "Ready for Pickup";

                    $tracking_step =
                        3;

                    break;


                case "COMPLETED":

                    $display_status =
                        "Picked Up";

                    $tracking_step =
                        4;

                    break;


                case "CANCELLED":

                    $display_status =
                        "Cancelled";

                    $tracking_step =
                        0;

                    break;


                default:

                    $display_status =
                        ucfirst(
                            strtolower(
                                $order[
                                    "order_status"
                                ]
                            )
                        );

                    $tracking_step =
                        1;
            }


        /* ================= DINE-IN ================= */

        } elseif (
            $order_type_upper ===
            "DINE_IN"
        ) {

            $tracking_labels = [

                "Confirmed",
                "Preparing",
                "Ready",
                "Served"

            ];


            switch (
                $raw_status
            ) {

                case "CONFIRMED":

                    $display_status =
                        "Confirmed";

                    $tracking_step =
                        1;

                    break;


                case "PREPARING":

                    $display_status =
                        "Preparing";

                    $tracking_step =
                        2;

                    break;


                case "READY":

                    $display_status =
                        "Ready";

                    $tracking_step =
                        3;

                    break;


                case "COMPLETED":

                    $display_status =
                        "Served";

                    $tracking_step =
                        4;

                    break;


                case "CANCELLED":

                    $display_status =
                        "Cancelled";

                    $tracking_step =
                        0;

                    break;


                default:

                    $display_status =
                        ucfirst(
                            strtolower(
                                $order[
                                    "order_status"
                                ]
                            )
                        );

                    $tracking_step =
                        1;
            }

        } else {

            $tracking_labels = [

                "Confirmed",
                "Preparing",
                "Ready",
                "Completed"

            ];


            switch (
                $raw_status
            ) {

                case "CONFIRMED":

                    $display_status =
                        "Confirmed";

                    $tracking_step =
                        1;

                    break;


                case "PREPARING":

                    $display_status =
                        "Preparing";

                    $tracking_step =
                        2;

                    break;


                case "READY":

                    $display_status =
                        "Ready";

                    $tracking_step =
                        3;

                    break;


                case "COMPLETED":

                    $display_status =
                        "Completed";

                    $tracking_step =
                        4;

                    break;


                case "CANCELLED":

                    $display_status =
                        "Cancelled";

                    $tracking_step =
                        0;

                    break;


                default:

                    $display_status =
                        ucfirst(
                            strtolower(
                                $order[
                                    "order_status"
                                ]
                            )
                        );

                    $tracking_step =
                        1;
            }
        }

        ?>


        <!-- =================================================
             ORDER CARD
        ================================================= -->

        <section class="order-card">


            <!-- ================= TOP ================= -->

            <div class="order-top">


                <div>


                    <h2>

                        Order #

                        <?php

                        echo htmlspecialchars(
                            $order[
                                "order_number"
                            ]
                        );

                        ?>

                    </h2>


                    <p>

                        <?php

                        echo date(
                            "d M Y, h:i A",
                            strtotime(
                                $order[
                                    "created_at"
                                ]
                            )
                        );

                        ?>

                    </p>


                </div>


                <span class="order-status">

                    <?php

                    echo htmlspecialchars(
                        $display_status
                    );

                    ?>

                </span>


            </div>


            <!-- ================= SUMMARY ================= -->

            <div class="order-summary-row">


                <div>

                    <span>
                        Items
                    </span>


                    <strong>

                        <?php

                        echo $total_items;

                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Type
                    </span>


                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $display_order_type
                        );

                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Payment
                    </span>


                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $display_payment
                        );

                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Total
                    </span>


                    <strong>

                        ₹<?php

                        echo number_format(
                            (float)
                            $order["total"],
                            2
                        );

                        ?>

                    </strong>

                </div>


            </div>


            <!-- =================================================
                 TRACKING
            ================================================== -->

            <?php if (
                $show_tracking
            ): ?>


                <div class="order-tracking">


                    <div class="tracking-title">
                        Order Tracking
                    </div>


                    <div class="tracking-wrapper">


                        <?php

                        $label_count =
                            count(
                                $tracking_labels
                            );


                        foreach (
                            $tracking_labels
                            as $index =>
                            $label
                        ):

                            $step_number =
                                $index + 1;


                            $is_completed =
                                $tracking_step >=
                                $step_number;

                        ?>


                            <!-- ================= STEP ================= -->

                            <div
                                class="
                                    tracking-step
                                    <?php
                                    echo $is_completed
                                        ? "completed"
                                        : "";
                                    ?>
                                "
                            >


                                <div
                                    class="tracking-dot"
                                >

                                    <?php

                                    if (
                                        $is_completed
                                    ) {

                                        echo "✓";
                                    }

                                    ?>


                                </div>


                                <span>

                                    <?php

                                    echo htmlspecialchars(
                                        $label
                                    );

                                    ?>

                                </span>


                            </div>


                            <?php if (
                                $step_number <
                                $label_count
                            ): ?>


                                <div
                                    class="
                                        tracking-line
                                        <?php

                                        echo
                                            $tracking_step >
                                            $step_number
                                            ? "completed"
                                            : "";

                                        ?>
                                    "
                                ></div>


                            <?php endif; ?>


                        <?php endforeach; ?>


                    </div>


                </div>


            <?php endif; ?>


            <!-- =================================================
                 ACTIONS
            ================================================== -->

            <div class="order-actions">


                <!-- ================= VIEW DETAILS ================= -->

                <a
                    href="order-details.php?order_id=<?php echo (int) $order["id"]; ?>"
                    class="view-details-btn"
                >
                    View Details
                </a>


                <!-- ================= RATE & REVIEW ================= -->

                <?php if (
                    $different_foods > 0
                ): ?>


                    <a
                        href="rate-order.php?order_id=<?php echo (int) $order["id"]; ?>"
                        class="rate-order-btn"
                    >
                        Rate & Review
                    </a>


                <?php endif; ?>


                <!-- ================= CANCEL ================= -->

                <?php if (
                    $raw_status ===
                    "CONFIRMED"
                ): ?>


                    <button
                        type="button"
                        class="cancel-order-btn"
                        onclick="showCancelConfirmation(<?php echo (int) $order["id"]; ?>)"
                    >
                        Cancel Order
                    </button>


                    <div
                        class="cancel-confirm-box"
                        id="cancel-confirm-<?php echo (int) $order["id"]; ?>"
                    >


                        <p>
                            Are you sure you want to cancel this order?
                        </p>


                        <div class="cancel-confirm-actions">


                            <a
                                href="cancel-order.php?order_id=<?php echo (int) $order["id"]; ?>"
                                class="confirm-cancel-btn"
                            >
                                Yes, Cancel Order
                            </a>


                            <button
                                type="button"
                                class="keep-order-btn"
                                onclick="hideCancelConfirmation(<?php echo (int) $order["id"]; ?>)"
                            >
                                Keep Order
                            </button>


                        </div>


                    </div>


                <?php endif; ?>


            </div>


        </section>


    <?php endwhile; ?>


<?php else: ?>


    <!-- =================================================
         EMPTY
    ================================================== -->

    <section class="empty-orders">


        <h2>
            No Orders Yet
        </h2>


        <p>
            You haven't placed any orders with
            Spicy Zone Restaurant yet.
        </p>


        <a
            href="menu.php"
            class="menu-btn"
        >
            Explore Menu
        </a>


    </section>


<?php endif; ?>


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
   SHOW CANCEL CONFIRMATION
========================================================= */

function showCancelConfirmation(
    orderId
) {

    const box =
        document.getElementById(
            "cancel-confirm-" +
            orderId
        );


    if (box) {

        box.style.display =
            "block";
    }

}


/* =========================================================
   HIDE CANCEL CONFIRMATION
========================================================= */

function hideCancelConfirmation(
    orderId
) {

    const box =
        document.getElementById(
            "cancel-confirm-" +
            orderId
        );


    if (box) {

        box.style.display =
            "none";
    }

}

</script>


</body>
</html>