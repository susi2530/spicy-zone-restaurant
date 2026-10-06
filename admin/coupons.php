<?php

session_start();
require_once "../db.php";


/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin-login.php");
    exit();
}


/* =========================================================
   GET COUPONS
========================================================= */

$coupon_stmt = $conn->prepare(
    "SELECT
        id,
        coupon_name,
        coupon_code,
        discount_type,
        discount_value,
        minimum_amount,
        first_order_only,
        is_active,
        created_at
     FROM coupons
     ORDER BY id ASC"
);


if (!$coupon_stmt) {

    die(
        "Unable to load coupons: " .
        htmlspecialchars($conn->error)
    );
}


$coupon_stmt->execute();

$coupon_result =
    $coupon_stmt->get_result();


/* =========================================================
   TOTAL COUPONS
========================================================= */

$total_coupon_result =
    $conn->query(
        "SELECT COUNT(*) AS total
         FROM coupons"
    );


$total_coupon_row =
    $total_coupon_result->fetch_assoc();


$total_coupons =
    (int) $total_coupon_row["total"];

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
        Coupons - Spicy Zone Admin
    </title>

    <link
        rel="stylesheet"
        href="css/coupons.css"
    >

</head>

<body>


<!-- =========================================================
     ADMIN NAVBAR
========================================================= -->

<header class="admin-navbar">

    <div class="admin-brand">

        <a href="dashboard.php">

            <img
                src="../images/logo.png"
                alt="Spicy Zone Logo"
            >

        </a>

        <div>

            <strong>
                Spicy Zone
            </strong>

            <span>
                Admin Panel
            </span>

        </div>

    </div>


    <nav class="admin-nav">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="orders.php">
            Orders
        </a>

        <a href="bookings.php">
            Bookings
        </a>

        <a href="foods.php">
            Foods
        </a>

        <a href="categories.php">
            Categories
        </a>

        <a href="users.php">
            Users
        </a>

        <a href="reviews.php">
            Reviews
        </a>

        <a
            href="coupons.php"
            class="active"
        >
            Coupons
        </a>

        <a
            href="admin-logout.php"
            class="logout-link"
        >
            Logout
        </a>

    </nav>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="coupons-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <section class="page-heading">

        <p class="eyebrow">
            ADMIN PANEL
        </p>

        <h1>
            Coupons
        </h1>

        <p>
            View the available restaurant offers and discount rules.
        </p>

    </section>


    <!-- =====================================================
         COUPON PANEL
    ====================================================== -->

    <section class="coupons-panel">

        <div class="panel-heading">

            <div>

                <p class="panel-label">
                    DISCOUNT OFFERS
                </p>

                <h2>
                    Available Coupons
                </h2>

            </div>


            <span class="coupon-count">

                <?php
                echo $total_coupons;
                ?>

                Coupons

            </span>

        </div>


        <!-- =================================================
             COUPON LIST
        ================================================== -->

        <?php if ($coupon_result->num_rows > 0): ?>

            <div class="coupons-grid">


                <?php
                while (
                    $coupon =
                    $coupon_result->fetch_assoc()
                ):
                ?>


                    <?php

                    $discount_type =
                        strtolower(
                            (string)
                            $coupon["discount_type"]
                        );


                    $discount_value =
                        (float)
                        $coupon["discount_value"];


                    $minimum_amount =
                        (float)
                        (
                            $coupon["minimum_amount"]
                            ?? 0
                        );


                    $first_order_only =
                        (int)
                        (
                            $coupon["first_order_only"]
                            ?? 0
                        );


                    $is_active =
                        (int)
                        (
                            $coupon["is_active"]
                            ?? 0
                        );

                    ?>


                    <article class="coupon-card">


                        <!-- ================= TOP ================= -->

                        <div class="coupon-top">


                            <div class="coupon-icon">
                                %
                            </div>


                            <span
                                class="coupon-status
                                <?php
                                echo $is_active === 1
                                    ? "active"
                                    : "inactive";
                                ?>"
                            >

                                <?php
                                echo $is_active === 1
                                    ? "Active"
                                    : "Inactive";
                                ?>

                            </span>


                        </div>


                        <!-- ================= NAME ================= -->

                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $coupon["coupon_name"]
                            );
                            ?>

                        </h3>


                        <!-- ================= CODE ================= -->

                        <div class="coupon-code-box">

                            <span>
                                Coupon Code
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $coupon["coupon_code"]
                                    ?? "-"
                                );
                                ?>

                            </strong>

                        </div>


                        <!-- ================= DISCOUNT ================= -->

                        <div class="coupon-details">


                            <div class="coupon-detail">

                                <span>
                                    Discount
                                </span>

                                <strong>

                                    <?php

                                    if (
                                        $discount_type ===
                                        "percentage"
                                    ) {

                                        echo number_format(
                                            $discount_value,
                                            2
                                        );

                                        echo "%";

                                    } else {

                                        echo "₹";

                                        echo number_format(
                                            $discount_value,
                                            2
                                        );
                                    }

                                    ?>

                                </strong>

                            </div>


                            <div class="coupon-detail">

                                <span>
                                    Minimum Order
                                </span>

                                <strong>

                                    ₹<?php
                                    echo number_format(
                                        $minimum_amount,
                                        2
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="coupon-detail">

                                <span>
                                    Eligibility
                                </span>

                                <strong>

                                    <?php
                                    echo $first_order_only === 1
                                        ? "First Order"
                                        : "All Orders";
                                    ?>

                                </strong>

                            </div>


                        </div>


                        <!-- ================= FOOTER ================= -->

                        <div class="coupon-footer">

                            <?php

                            if (
                                !empty(
                                    $coupon["created_at"]
                                )
                            ) {

                                echo "Created ";

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $coupon["created_at"]
                                    )
                                );

                            }

                            ?>

                        </div>


                    </article>


                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="empty-coupons">

                <h2>
                    No Coupons Found
                </h2>

                <p>
                    No discount coupons are available.
                </p>

            </div>


        <?php endif; ?>


    </section>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="admin-footer">

    <p>
        © 2026 Spicy Zone Restaurant Admin Panel.
        All Rights Reserved.
    </p>

</footer>


</body>
</html>