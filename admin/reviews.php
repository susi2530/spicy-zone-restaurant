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
   GET REVIEWS
========================================================= */

$review_stmt = $conn->prepare(
    "SELECT
        r.id,
        r.rating,
        r.review_text,
        r.created_at,
        u.name AS user_name,
        u.email AS user_email,
        f.name AS food_name
     FROM reviews r
     LEFT JOIN users u
        ON r.user_id = u.id
     LEFT JOIN foods f
        ON r.food_id = f.id
     ORDER BY r.created_at DESC"
);


if (!$review_stmt) {

    die(
        "Unable to load reviews: " .
        htmlspecialchars($conn->error)
    );
}


$review_stmt->execute();

$review_result =
    $review_stmt->get_result();


/* =========================================================
   TOTAL REVIEWS
========================================================= */

$total_review_result =
    $conn->query(
        "SELECT COUNT(*) AS total
         FROM reviews"
    );


$total_review_row =
    $total_review_result->fetch_assoc();


$total_reviews =
    (int) $total_review_row["total"];

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
        Reviews - Spicy Zone Admin
    </title>

    <link
        rel="stylesheet"
        href="css/reviews.css"
    >

</head>

<body>


<!-- =========================================================
     NAVBAR
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

        <a
            href="reviews.php"
            class="active"
        >
            Reviews
        </a>

        <a href="coupons.php">
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

<main class="reviews-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <section class="page-heading">


        <div>

            <p class="eyebrow">
                ADMIN PANEL
            </p>

            <h1>
                Reviews
            </h1>

            <p>
                View customer feedback and food ratings.
            </p>

        </div>


        <div class="review-count-box">

            <span>
                Total Reviews
            </span>

            <strong>
                <?php
                echo number_format(
                    $total_reviews
                );
                ?>
            </strong>

        </div>


    </section>


    <!-- =====================================================
         REVIEWS
    ====================================================== -->

    <section class="reviews-panel">


        <?php if ($review_result->num_rows > 0): ?>


            <div class="reviews-list">


                <?php
                while (
                    $review =
                    $review_result->fetch_assoc()
                ):
                ?>


                    <article class="review-card">


                        <!-- ================= CUSTOMER ================= -->

                        <div class="review-top">


                            <div class="customer-info">


                                <div class="customer-avatar">

                                    <?php

                                    $user_name =
                                        $review["user_name"]
                                        ?? "User";

                                    echo htmlspecialchars(
                                        strtoupper(
                                            substr(
                                                $user_name,
                                                0,
                                                1
                                            )
                                        )
                                    );

                                    ?>

                                </div>


                                <div>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $user_name
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $review[
                                                "user_email"
                                            ] ?? ""
                                        );
                                        ?>

                                    </span>

                                </div>


                            </div>


                            <span class="review-date">

                                <?php

                                if (
                                    !empty(
                                        $review[
                                            "created_at"
                                        ]
                                    )
                                ) {

                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $review[
                                                "created_at"
                                            ]
                                        )
                                    );

                                } else {

                                    echo "-";

                                }

                                ?>

                            </span>


                        </div>


                        <!-- ================= FOOD ================= -->

                        <div class="food-name">

                            <span>
                                Food
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $review[
                                        "food_name"
                                    ] ?? "Food"
                                );
                                ?>

                            </strong>

                        </div>


                        <!-- ================= RATING ================= -->

                        <div class="rating-row">


                            <div class="stars">

                                <?php

                                $rating =
                                    (int)
                                    $review[
                                        "rating"
                                    ];


                                for (
                                    $i = 1;
                                    $i <= 5;
                                    $i++
                                ) {

                                    echo (
                                        $i <= $rating
                                    )
                                        ? "★"
                                        : "☆";
                                }

                                ?>

                            </div>


                            <strong class="rating-number">

                                <?php
                                echo $rating;
                                ?>/5

                            </strong>


                        </div>


                        <!-- ================= REVIEW TEXT ================= -->

                        <div class="review-text">

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $review[
                                        "review_text"
                                    ] ?? ""
                                )
                            );

                            ?>

                        </div>


                    </article>


                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="empty-reviews">

                <h2>
                    No Reviews Found
                </h2>

                <p>
                    No customer reviews are available yet.
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