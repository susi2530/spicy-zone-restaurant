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


$order_id = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;


if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}


/* =========================================================
   REORDER
========================================================= */

$reorder_message = "";
$reorder_type = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["reorder"])
) {

    $reorder_stmt = $conn->prepare(
        "SELECT
            oi.food_id,
            oi.quantity
         FROM order_items oi
         INNER JOIN orders o
            ON oi.order_id = o.id
         WHERE oi.order_id = ?
           AND o.user_id = ?"
    );

    $reorder_stmt->bind_param(
        "ii",
        $order_id,
        $user_id
    );

    $reorder_stmt->execute();

    $reorder_result =
        $reorder_stmt->get_result();

    $reorder_items = [];


    while (
        $reorder_item =
        $reorder_result->fetch_assoc()
    ) {

        $reorder_items[] =
            $reorder_item;
    }


    $reorder_stmt->close();


    if (count($reorder_items) === 0) {

        $reorder_message =
            "No food items found in this order.";

        $reorder_type =
            "error";

    } else {

        $conn->begin_transaction();

        try {

            $added_count = 0;


            foreach (
                $reorder_items as $item
            ) {

                $food_id =
                    (int) $item["food_id"];

                $quantity =
                    (int) $item["quantity"];


                /* =========================================
                   CHECK FOOD AVAILABILITY
                ========================================== */

                $food_stmt = $conn->prepare(
                    "SELECT
                        id,
                        is_available
                     FROM foods
                     WHERE id = ?
                     LIMIT 1"
                );

                $food_stmt->bind_param(
                    "i",
                    $food_id
                );

                $food_stmt->execute();

                $food_result =
                    $food_stmt->get_result();


                if (
                    $food_result->num_rows !== 1
                ) {

                    $food_stmt->close();

                    continue;
                }


                $food =
                    $food_result->fetch_assoc();

                $food_stmt->close();


                if (
                    (int) $food["is_available"] !== 1
                ) {

                    continue;
                }


                /* =========================================
                   CHECK EXISTING CART ITEM
                ========================================== */

                $cart_check = $conn->prepare(
                    "SELECT
                        id,
                        quantity
                     FROM cart
                     WHERE user_id = ?
                       AND food_id = ?
                     LIMIT 1"
                );

                $cart_check->bind_param(
                    "ii",
                    $user_id,
                    $food_id
                );

                $cart_check->execute();

                $cart_result =
                    $cart_check->get_result();


                if (
                    $cart_result->num_rows === 1
                ) {

                    $cart_row =
                        $cart_result->fetch_assoc();


                    $new_quantity =
                        (int) $cart_row["quantity"]
                        + $quantity;


                    $cart_update = $conn->prepare(
                        "UPDATE cart
                         SET quantity = ?
                         WHERE id = ?"
                    );

                    $cart_update->bind_param(
                        "ii",
                        $new_quantity,
                        $cart_row["id"]
                    );

                    $cart_update->execute();

                    $cart_update->close();

                } else {

                    $cart_insert = $conn->prepare(
                        "INSERT INTO cart
                        (
                            user_id,
                            food_id,
                            quantity
                        )
                        VALUES (?, ?, ?)"
                    );

                    $cart_insert->bind_param(
                        "iii",
                        $user_id,
                        $food_id,
                        $quantity
                    );

                    $cart_insert->execute();

                    $cart_insert->close();
                }


                $cart_check->close();

                $added_count++;
            }


            if ($added_count === 0) {

                $conn->rollback();

                $reorder_message =
                    "The food items from this order are currently unavailable.";

                $reorder_type =
                    "error";

            } else {

                $conn->commit();

                header(
                    "Location: cart.php?reordered=1"
                );

                exit();
            }


        } catch (Exception $e) {

            $conn->rollback();

            $reorder_message =
                "Unable to reorder this order.";

            $reorder_type =
                "error";
        }
    }
}


/* =========================================================
   GET ORDER
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
     WHERE id = ?
       AND user_id = ?
     LIMIT 1"
);

$order_stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);

$order_stmt->execute();

$order_result =
    $order_stmt->get_result();


if (
    $order_result->num_rows !== 1
) {

    header("Location: orders.php");
    exit();
}


$order =
    $order_result->fetch_assoc();

$order_stmt->close();


/* =========================================================
   ORDER ITEMS
========================================================= */

$item_stmt = $conn->prepare(
    "SELECT
        food_id,
        food_name,
        price,
        quantity
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC"
);

$item_stmt->bind_param(
    "i",
    $order_id
);

$item_stmt->execute();

$item_result =
    $item_stmt->get_result();


/* =========================================================
   DISPLAY ORDER TYPE
========================================================= */

switch (
    strtoupper(
        (string) $order["order_type"]
    )
) {

    case "DELIVERY":
        $display_order_type = "Delivery";
        break;

    case "TAKEAWAY":
        $display_order_type = "Takeaway";
        break;

    default:
        $display_order_type = "Dine-in";
}


/* =========================================================
   DISPLAY PAYMENT
========================================================= */

if (
    strtoupper(
        (string) $order["payment_method"]
    ) === "UPI"
) {

    $display_payment = "UPI";

} else {

    $display_payment = "Cash on Delivery";
}


/* =========================================================
   PAYMENT STATUS
========================================================= */

$raw_payment_status =
    strtolower(
        trim(
            (string) $order["payment_status"]
        )
    );

$payment_method_upper =
    strtoupper(
        trim(
            (string) $order["payment_method"]
        )
    );

$raw_order_status_for_payment =
    strtoupper(
        trim(
            (string) $order["order_status"]
        )
    );


/*
 * UPI
 * → Paid
 *
 * COD + Delivered
 * → Paid
 *
 * COD before Delivered
 * → Existing payment status
 */

if (
    $payment_method_upper === "UPI"
) {

    $display_payment_status =
        "Paid";

} elseif (
    $payment_method_upper !== "UPI"
    &&
    $raw_order_status_for_payment === "COMPLETED"
) {

    $display_payment_status =
        "Paid";

} elseif (
    $raw_payment_status === "failed"
) {

    $display_payment_status =
        "Failed";

} else {

    $display_payment_status =
        "Pending";
}


/* =========================================================
   ORDER STATUS
========================================================= */

$raw_order_status =
    strtoupper(
        trim(
            (string) $order["order_status"]
        )
    );


switch ($raw_order_status) {

    case "CONFIRMED":

        $display_order_status = "Confirmed";
        $details_step = 1;

        break;


    case "PREPARING":

        $display_order_status = "Preparing";
        $details_step = 2;

        break;


    case "READY":

        $display_order_status = "Ready";
        $details_step = 3;

        break;


    case "COMPLETED":

        $display_order_status = "Delivered";
        $details_step = 4;

        break;


    case "CANCELLED":

        $display_order_status = "Cancelled";
        $details_step = 0;

        break;


    default:

        $display_order_status =
            ucfirst(
                strtolower(
                    $order["order_status"]
                )
            );

        $details_step = 1;
}


/* =========================================================
   TRACKING ONLY FOR DELIVERY / TAKEAWAY
========================================================= */

$show_tracking =
    in_array(
        strtoupper(
            (string) $order["order_type"]
        ),
        ["DELIVERY", "TAKEAWAY"],
        true
    )
    &&
    $raw_order_status !== "CANCELLED";

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
        Order Details - Spicy Zone Restaurant
    </title>

    <link
        rel="stylesheet"
        href="css/order-details.css"
    >

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

        <a href="index.php">Home</a>

        <a href="menu.php">Menu</a>

        <a href="mood.php">Mood</a>

        <a href="favorites.php">Favorites</a>

        <a href="cart.php">Cart</a>

        <a href="my-bookings.php">My Bookings</a>

        <a href="profile.php">Profile</a>

    </nav>

</header>


<!-- =========================================================
     HEADER
========================================================= -->

<section class="details-header">

    <a
        href="orders.php"
        class="back-link"
    >
        ← Back to My Orders
    </a>


    <p class="small-heading">
        ORDER DETAILS
    </p>


    <h1>

        Order #

        <?php
        echo htmlspecialchars(
            $order["order_number"]
        );
        ?>

    </h1>


    <p>

        <?php
        echo date(
            "d M Y, h:i A",
            strtotime(
                $order["created_at"]
            )
        );
        ?>

    </p>

</section>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="details-page">


    <!-- =====================================================
         STATUS
    ====================================================== -->

    <section class="details-card status-card">

        <div>

            <span>
                Order Status
            </span>

            <strong class="status">

                <?php
                echo htmlspecialchars(
                    $display_order_status
                );
                ?>

            </strong>

        </div>


        <div>

            <span>
                Payment Status
            </span>

            <strong class="paid">

                <?php
                echo htmlspecialchars(
                    $display_payment_status
                );
                ?>

            </strong>

        </div>

    </section>


    <!-- =====================================================
         TRACKING
    ====================================================== -->

    <?php if ($show_tracking): ?>

        <section class="details-card tracking-card">

            <h2>
                Order Tracking
            </h2>


            <div class="details-tracking">


                <!-- CONFIRMED -->

                <div class="
                    details-track-step
                    <?php
                    echo $details_step >= 1
                        ? "completed"
                        : "";
                    ?>"
                >

                    <div class="details-track-dot">

                        <?php
                        if ($details_step >= 1) {
                            echo "✓";
                        }
                        ?>

                    </div>

                    <span>
                        Confirmed
                    </span>

                </div>


                <div class="
                    details-track-line
                    <?php
                    echo $details_step >= 2
                        ? "completed"
                        : "";
                    ?>"
                ></div>


                <!-- PREPARING -->

                <div class="
                    details-track-step
                    <?php
                    echo $details_step >= 2
                        ? "completed"
                        : "";
                    ?>"
                >

                    <div class="details-track-dot">

                        <?php
                        if ($details_step >= 2) {
                            echo "✓";
                        }
                        ?>

                    </div>

                    <span>
                        Preparing
                    </span>

                </div>


                <div class="
                    details-track-line
                    <?php
                    echo $details_step >= 3
                        ? "completed"
                        : "";
                    ?>"
                ></div>


                <!-- READY -->

                <div class="
                    details-track-step
                    <?php
                    echo $details_step >= 3
                        ? "completed"
                        : "";
                    ?>"
                >

                    <div class="details-track-dot">

                        <?php
                        if ($details_step >= 3) {
                            echo "✓";
                        }
                        ?>

                    </div>

                    <span>
                        Ready
                    </span>

                </div>


                <div class="
                    details-track-line
                    <?php
                    echo $details_step >= 4
                        ? "completed"
                        : "";
                    ?>"
                ></div>


                <!-- DELIVERED -->

                <div class="
                    details-track-step
                    <?php
                    echo $details_step >= 4
                        ? "completed"
                        : "";
                    ?>"
                >

                    <div class="details-track-dot">

                        <?php
                        if ($details_step >= 4) {
                            echo "✓";
                        }
                        ?>

                    </div>

                    <span>
                        Delivered
                    </span>

                </div>


            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         ORDERED ITEMS
    ====================================================== -->

    <section class="details-card">

        <h2>
            Ordered Items
        </h2>


        <div class="items-list">


            <?php
            while (
                $item =
                $item_result->fetch_assoc()
            ):
            ?>

                <div class="item-row">

                    <div>

                        <h3>
                            <?php
                            echo htmlspecialchars(
                                $item["food_name"]
                            );
                            ?>
                        </h3>

                        <p>

                            ₹<?php
                            echo number_format(
                                (float) $item["price"],
                                2
                            );
                            ?>

                            ×

                            <?php
                            echo (int) $item["quantity"];
                            ?>

                        </p>

                    </div>


                    <strong>

                        ₹<?php
                        echo number_format(
                            (float) $item["price"] *
                            (int) $item["quantity"],
                            2
                        );
                        ?>

                    </strong>

                </div>

            <?php endwhile; ?>


        </div>

    </section>


    <!-- =====================================================
         ORDER INFORMATION + REORDER
    ====================================================== -->

    <section class="details-card order-info-card">


        <div class="section-title-row">

            <h2>
                Order Information
            </h2>


            <?php if (
                $raw_order_status !== "CANCELLED"
            ): ?>

                <form
                    method="POST"
                    action=""
                    class="reorder-form"
                >

                    <button
                        type="submit"
                        name="reorder"
                        value="1"
                        class="reorder-btn"
                    >
                        Reorder
                    </button>

                </form>

            <?php endif; ?>


        </div>


        <div class="info-grid">


            <div>

                <span>
                    Order Type
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
                    Payment Method
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $display_payment
                    );
                    ?>
                </strong>

            </div>


        </div>


        <?php if ($reorder_message !== ""): ?>

            <div
                class="reorder-message
                <?php
                echo htmlspecialchars(
                    $reorder_type
                );
                ?>"
            >

                <?php
                echo htmlspecialchars(
                    $reorder_message
                );
                ?>

            </div>

        <?php endif; ?>


    </section>


    <!-- =====================================================
         BILL
    ====================================================== -->

    <section class="details-card bill-card">

        <h2>
            Bill Details
        </h2>


        <div class="bill-row">

            <span>
                Subtotal
            </span>

            <strong>

                ₹<?php
                echo number_format(
                    (float) $order["subtotal"],
                    2
                );
                ?>

            </strong>

        </div>


        <div class="bill-row discount-row">

            <span>
                Discount
            </span>

            <strong>

                -₹<?php
                echo number_format(
                    (float) $order["discount"],
                    2
                );
                ?>

            </strong>

        </div>


        <div class="bill-total">

            <span>
                Total
            </span>

            <strong>

                ₹<?php
                echo number_format(
                    (float) $order["total"],
                    2
                );
                ?>

            </strong>

        </div>

    </section>


</main>


<footer>

    <p>
        © 2026 Spicy Zone Restaurant.
        All Rights Reserved.
    </p>

</footer>


</body>
</html>