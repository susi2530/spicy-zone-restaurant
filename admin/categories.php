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
   GET CATEGORIES
========================================================= */

$category_stmt = $conn->prepare(
    "SELECT
        id,
        name
     FROM categories
     ORDER BY id ASC"
);

$category_stmt->execute();

$category_result =
    $category_stmt->get_result();

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
        Categories - Spicy Zone Admin
    </title>

    <link
        rel="stylesheet"
        href="css/categories.css"
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

        <a
            href="categories.php"
            class="active"
        >
            Categories
        </a>

        <a href="users.php">
            Users
        </a>

        <a href="reviews.php">
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

<main class="categories-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <section class="page-heading">

        <p class="eyebrow">
            MENU
        </p>

        <h1>
            Categories
        </h1>

        <p>
            View food menu categories.
        </p>

    </section>


    <!-- =====================================================
         CATEGORY PANEL
    ====================================================== -->

    <section class="categories-panel">

        <div class="panel-heading">

            <div>

                <p class="panel-label">
                    MENU CATEGORIES
                </p>

                <h2>
                    Available Categories
                </h2>

            </div>


            <span class="category-count">

                <?php
                echo $category_result->num_rows;
                ?>

                Categories

            </span>

        </div>


        <!-- =================================================
             CATEGORY LIST
        ================================================== -->

        <?php if ($category_result->num_rows > 0): ?>

            <div class="categories-grid">


                <?php
                while (
                    $category =
                    $category_result->fetch_assoc()
                ):
                ?>

                    <article class="category-card">


                        <div class="category-number">

                            <?php
                            echo (int)
                                $category["id"];
                            ?>

                        </div>


                        <div class="category-content">

                            <h3>

                                <?php
                                echo htmlspecialchars(
                                    $category["name"]
                                );
                                ?>

                            </h3>

                        </div>


                    </article>

                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="empty-categories">

                <h2>
                    No Categories Found
                </h2>

                <p>
                    No menu categories are available.
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