<?php

session_start();
require_once "db.php";

$food_id = isset($_GET["food_id"])
    ? (int)$_GET["food_id"]
    : 0;

if ($food_id <= 0) {
    header("Location: menu.php");
    exit();
}


/* ================= FOOD DETAILS ================= */

$food_stmt = $conn->prepare(
    "SELECT
        foods.id,
        foods.name,
        foods.price,
        foods.image,
        foods.description,
        categories.name AS category_name
     FROM foods
     INNER JOIN categories
        ON foods.category_id = categories.id
     WHERE foods.id = ?"
);

$food_stmt->bind_param("i", $food_id);
$food_stmt->execute();

$food_result = $food_stmt->get_result();

if ($food_result->num_rows !== 1) {
    header("Location: menu.php");
    exit();
}

$food = $food_result->fetch_assoc();

$food_stmt->close();


/* ================= REVIEW SUMMARY ================= */

$summary_stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_reviews,
        ROUND(AVG(rating), 1) AS average_rating
     FROM reviews
     WHERE food_id = ?"
);

$summary_stmt->bind_param("i", $food_id);
$summary_stmt->execute();

$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();

$summary_stmt->close();

$total_reviews = (int)$summary["total_reviews"];
$average_rating = $summary["average_rating"]
    ? (float)$summary["average_rating"]
    : 0;


/* ================= ALL REVIEWS ================= */

$review_stmt = $conn->prepare(
    "SELECT
        reviews.rating,
        reviews.review_text,
        reviews.created_at,
        users.name
     FROM reviews
     INNER JOIN users
        ON reviews.user_id = users.id
     WHERE reviews.food_id = ?
     ORDER BY reviews.created_at DESC"
);

$review_stmt->bind_param("i", $food_id);
$review_stmt->execute();

$review_result = $review_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($food["name"]); ?>
        - Reviews
    </title>

    <link rel="stylesheet"
          href="css/food-reviews.css">

</head>

<body>


<!-- ================= NAVBAR ================= -->

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

        <?php if (isset($_SESSION["user_id"])): ?>

            <a href="my-bookings.php">
                My Bookings
            </a>

            <a href="profile.php">
                Profile
            </a>

        <?php else: ?>

            <a href="login.php">
                Login
            </a>

        <?php endif; ?>

    </nav>

</header>


<!-- ================= HEADER ================= -->

<section class="reviews-header">

    <a href="menu.php"
       class="back-link">
        ← Back to Menu
    </a>

    <p class="small-heading">
        FOOD REVIEWS
    </p>

    <h1>
        <?php echo htmlspecialchars($food["name"]); ?>
    </h1>

    <p>
        What customers are saying about this dish.
    </p>

</section>


<!-- ================= FOOD SUMMARY ================= -->

<main class="reviews-page">

    <section class="food-summary-card">

        <div class="food-summary-image">

            <img
                src="images/<?php echo htmlspecialchars(
                    $food["image"]
                ); ?>"
                alt="<?php echo htmlspecialchars(
                    $food["name"]
                ); ?>"
            >

        </div>


        <div class="food-summary-info">

            <p class="food-category">
                <?php echo htmlspecialchars(
                    $food["category_name"]
                ); ?>
            </p>

            <h2>
                <?php echo htmlspecialchars(
                    $food["name"]
                ); ?>
            </h2>

            <p class="food-description">
                <?php echo htmlspecialchars(
                    $food["description"]
                ); ?>
            </p>

            <p class="food-price">
                ₹<?php echo number_format(
                    (float)$food["price"],
                    2
                ); ?>
            </p>


            <div class="rating-summary">

                <strong>
                    ★
                    <?php echo number_format(
                        $average_rating,
                        1
                    ); ?>
                </strong>

                <span>
                    <?php echo $total_reviews; ?>
                    Reviews
                </span>

            </div>

        </div>

    </section>


    <!-- ================= REVIEWS ================= -->

    <section class="reviews-section">

        <div class="section-title">

            <h2>
                Customer Reviews
            </h2>

            <span>
                <?php echo $total_reviews; ?>
                Reviews
            </span>

        </div>


        <?php if ($review_result->num_rows > 0): ?>


            <div class="review-list">


                <?php while (
                    $review = $review_result->fetch_assoc()
                ): ?>


                    <article class="review-card">


                        <div class="review-top">

                            <div class="review-user">

                                <div class="review-avatar">

                                    <?php
                                    echo strtoupper(
                                        substr(
                                            trim(
                                                $review["name"]
                                            ),
                                            0,
                                            1
                                        )
                                    );
                                    ?>

                                </div>


                                <div>

                                    <h3>
                                        <?php
                                        echo htmlspecialchars(
                                            $review["name"]
                                        );
                                        ?>
                                    </h3>

                                    <p>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $review[
                                                    "created_at"
                                                ]
                                            )
                                        );
                                        ?>

                                    </p>

                                </div>

                            </div>


                            <div class="review-rating">

                                <?php
                                $rating =
                                    (int)$review["rating"];

                                for (
                                    $i = 1;
                                    $i <= 5;
                                    $i++
                                ) {

                                    if ($i <= $rating) {
                                        echo "★";
                                    } else {
                                        echo "☆";
                                    }

                                }
                                ?>

                            </div>

                        </div>


                        <p class="review-text">

                            <?php
                            echo htmlspecialchars(
                                $review["review_text"]
                            );
                            ?>

                        </p>


                    </article>


                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="no-reviews">

                <h2>
                    No Reviews Yet
                </h2>

                <p>
                    Be the first to review this food.
                </p>

            </div>


        <?php endif; ?>


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