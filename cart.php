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
   CART ITEMS
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        cart.id AS cart_id,
        cart.quantity,
        foods.id AS food_id,
        foods.name,
        foods.price,
        foods.image,
        foods.description
     FROM cart
     INNER JOIN foods
        ON cart.food_id = foods.id
     WHERE cart.user_id = ?
       AND foods.is_available = 1
     ORDER BY cart.created_at DESC"
);


$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$cart_result =
    $stmt->get_result();


/* =========================================================
   TOTAL
========================================================= */

$subtotal = 0;

$cart_items = [];


while (
    $row =
    $cart_result->fetch_assoc()
) {

    $item_total =
        (float) $row["price"] *
        (int) $row["quantity"];


    $row["item_total"] =
        $item_total;


    $subtotal +=
        $item_total;


    $cart_items[] =
        $row;
}


$stmt->close();


/* =========================================================
   CHECK FIRST ORDER
========================================================= */

$order_count = 0;


$order_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_orders
     FROM orders
     WHERE user_id = ?"
);


$order_stmt->bind_param(
    "i",
    $user_id
);


$order_stmt->execute();


$order_result =
    $order_stmt->get_result();


if (
    $order_row =
    $order_result->fetch_assoc()
) {

    $order_count =
        (int) $order_row["total_orders"];
}


$order_stmt->close();


/* =========================================================
   AUTOMATIC DISCOUNT
========================================================= */

$discount = 0;

$discount_label =
    "No discount";


if (
    $order_count === 0 &&
    $subtotal > 0
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
        Cart - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/cart.css"
    >


    <!-- =====================================================
         CURRENT PAGE ACTIVE COLOR
    ====================================================== -->

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


        <a
            href="cart.php"
            class="active"
        >
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
     CART HEADER
========================================================= -->

<section class="cart-header">


    <p class="small-heading">
        YOUR CART
    </p>


    <h1>
        Shopping Cart
    </h1>


    <p>
        Review your selected food before checkout.
    </p>


</section>


<!-- =========================================================
     CART CONTENT
========================================================= -->

<main class="cart-page">


    <?php if (
        count($cart_items) > 0
    ): ?>


        <div class="cart-layout">


            <!-- =================================================
                 ITEMS
            ================================================== -->

            <section class="cart-items">


                <?php foreach (
                    $cart_items as $item
                ): ?>


                    <div class="cart-item">


                        <div class="cart-image">

                            <img
                                src="images/<?php
                                echo htmlspecialchars(
                                    $item["image"]
                                );
                                ?>"
                                alt="<?php
                                echo htmlspecialchars(
                                    $item["name"]
                                );
                                ?>"
                            >

                        </div>


                        <div class="cart-info">


                            <p class="cart-category">
                                Spicy Zone
                            </p>


                            <h2>

                                <?php
                                echo htmlspecialchars(
                                    $item["name"]
                                );
                                ?>

                            </h2>


                            <p class="cart-price">

                                ₹<?php

                                echo number_format(
                                    (float)
                                    $item["price"],
                                    2
                                );

                                ?>

                            </p>


                            <div class="quantity-area">


                                <form
                                    method="POST"
                                    action="update_cart.php"
                                    class="quantity-form"
                                >


                                    <input
                                        type="hidden"
                                        name="cart_id"
                                        value="<?php
                                        echo (int)
                                            $item["cart_id"];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="quantity"
                                        value="<?php
                                        echo max(
                                            1,
                                            (int)
                                            $item["quantity"] - 1
                                        );
                                        ?>"
                                        class="quantity-btn"
                                    >
                                        −
                                    </button>


                                    <span class="quantity">

                                        <?php
                                        echo (int)
                                            $item["quantity"];
                                        ?>

                                    </span>


                                    <button
                                        type="submit"
                                        name="quantity"
                                        value="<?php
                                        echo
                                            (int)
                                            $item["quantity"] + 1;
                                        ?>"
                                        class="quantity-btn"
                                    >
                                        +
                                    </button>


                                </form>


                                <form
                                    method="POST"
                                    action="remove_from_cart.php"
                                >


                                    <input
                                        type="hidden"
                                        name="cart_id"
                                        value="<?php
                                        echo (int)
                                            $item["cart_id"];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="remove-btn"
                                    >
                                        Remove
                                    </button>


                                </form>


                            </div>


                        </div>


                        <div class="item-total">

                            ₹<?php

                            echo number_format(
                                $item["item_total"],
                                2
                            );

                            ?>

                        </div>


                    </div>


                <?php endforeach; ?>


            </section>


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <aside class="cart-summary">


                <h2>
                    Order Summary
                </h2>


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


                <div class="summary-row discount-row">


                    <span>

                        Discount

                        <?php if (
                            $discount > 0
                        ): ?>

                            (
                            <?php
                            echo $discount_label;
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


                <div class="automatic-note">


                    <?php if (
                        $order_count === 0 &&
                        $subtotal > 0
                    ): ?>


                        Your first-order 10% discount has been applied automatically.


                    <?php elseif (
                        $subtotal >= 500
                    ): ?>


                        20% discount has been applied automatically.


                    <?php else: ?>


                        Discounts are applied automatically when eligible.


                    <?php endif; ?>


                </div>


                <div class="summary-total">


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


                <a
                    href="checkout.php"
                    class="checkout-btn"
                >
                    Proceed to Checkout
                </a>


                <a
                    href="menu.php"
                    class="continue-btn"
                >
                    Continue Shopping
                </a>


            </aside>


        </div>


    <?php else: ?>


        <!-- =================================================
             EMPTY CART
        ================================================== -->

        <section class="empty-cart">


            <h2>
                Your Cart is Empty
            </h2>


            <p>
                Add some delicious food from our menu.
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
        © 2026 Spicy Zone Restaurant. All Rights Reserved.
    </p>

</footer>


</body>
</html>