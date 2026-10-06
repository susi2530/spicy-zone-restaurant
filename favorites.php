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
   GET USER'S FAVORITE FOODS
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        foods.id,
        foods.name,
        foods.price,
        foods.image,
        foods.description,
        foods.rating,
        categories.name AS category_name

     FROM favorites

     INNER JOIN foods
        ON favorites.food_id = foods.id

     INNER JOIN categories
        ON foods.category_id = categories.id

     WHERE favorites.user_id = ?
       AND foods.is_available = 1

     ORDER BY favorites.created_at DESC"
);


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$favorite_result =
    $stmt->get_result();

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
        Favorites - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/menu.css"
    >


    <style>

        /* =================================================
           CURRENT PAGE ACTIVE COLOR
        ================================================== */

        .nav-links a.active {
            color: #3a8f3a;
            font-weight: 700;
        }


        /* =================================================
           FAVORITES HEADER
        ================================================== */

        .favorites-header {
            padding: 50px 7% 30px;

            text-align: center;
        }


        .favorites-header h1 {
            font-size: 32px;

            margin-bottom: 10px;
        }


        .favorites-header p {
            color: #666666;

            font-size: 15px;
        }


        /* =================================================
           EMPTY FAVORITES
        ================================================== */

        .empty-favorites {
            width: 90%;

            max-width: 700px;

            margin: 30px auto 70px;

            padding: 60px 20px;

            text-align: center;

            border: 1px solid #e1e1e1;

            border-radius: 8px;

            background: #ffffff;
        }


        .empty-favorites h2 {
            margin-bottom: 10px;
        }


        .empty-favorites p {
            color: #777777;

            margin-bottom: 20px;
        }


        /* =================================================
           MENU BUTTON
        ================================================== */

        .menu-btn {
            display: inline-block;

            padding: 11px 20px;

            background: #3a8f3a;

            color: #ffffff;

            text-decoration: none;

            border-radius: 5px;

            font-size: 14px;

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


        <a
            href="favorites.php"
            class="active"
        >
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

<section class="favorites-header">


    <p class="small-heading">
        YOUR FAVORITES
    </p>


    <h1>
        Favourite Foods
    </h1>


    <p>
        Your saved dishes from Spicy Zone.
    </p>


</section>


<!-- =========================================================
     FAVORITES
========================================================= -->

<section class="food-section">


    <?php if (
        $favorite_result->num_rows > 0
    ): ?>


        <div class="food-grid">


            <?php while (
                $food =
                $favorite_result->fetch_assoc()
            ): ?>


                <div
                    class="food-card"
                    id="favorite-card-<?php
                    echo (int)
                        $food["id"];
                    ?>"
                >


                    <!-- =====================================
                         IMAGE
                    ====================================== -->

                    <div class="food-image">


                        <img
                            src="images/<?php
                            echo htmlspecialchars(
                                $food["image"]
                            );
                            ?>"
                            alt="<?php
                            echo htmlspecialchars(
                                $food["name"]
                            );
                            ?>"
                        >


                        <!-- =================================
                             FAVORITE HEART
                        ================================== -->

                        <button
                            type="button"
                            class="favorite-btn active"
                            data-food-id="<?php
                            echo (int)
                                $food["id"];
                            ?>"
                            title="Remove from Favorites"
                        >
                            ♥
                        </button>


                    </div>


                    <!-- =====================================
                         DETAILS
                    ====================================== -->

                    <div class="food-details">


                        <p class="food-category">

                            <?php
                            echo htmlspecialchars(
                                $food[
                                    "category_name"
                                ]
                            );
                            ?>

                        </p>


                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $food["name"]
                            );
                            ?>

                        </h2>


                        <?php if (
                            !empty(
                                $food["description"]
                            )
                        ): ?>


                            <p class="food-description">

                                <?php
                                echo htmlspecialchars(
                                    $food[
                                        "description"
                                    ]
                                );
                                ?>

                            </p>


                        <?php endif; ?>


                        <!-- =====================================
                             BOTTOM
                        ====================================== -->

                        <div class="food-bottom">


                            <div>


                                <p class="rating">

                                    ★

                                    <?php

                                    echo number_format(
                                        (float)
                                        $food["rating"],
                                        1
                                    );

                                    ?>

                                </p>


                                <p class="price">

                                    ₹<?php

                                    echo number_format(
                                        (float)
                                        $food["price"],
                                        2
                                    );

                                    ?>

                                </p>


                            </div>


                            <!-- =================================
                                 ADD TO CART
                            ================================== -->

                            <form
                                method="POST"
                                action="add_to_cart.php"
                            >


                                <input
                                    type="hidden"
                                    name="food_id"
                                    value="<?php
                                    echo (int)
                                        $food["id"];
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    class="cart-btn"
                                >
                                    Add to Cart
                                </button>


                            </form>


                        </div>


                    </div>


                </div>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <div class="empty-favorites">


            <h2>
                No Favorites Yet
            </h2>


            <p>
                You haven't added any food to your favorites.
            </p>


            <a
                href="menu.php"
                class="menu-btn"
            >
                Explore Menu
            </a>


        </div>


    <?php endif; ?>


</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>
        © 2026 Spicy Zone Restaurant.
        All Rights Reserved.
    </p>

</footer>


<!-- =========================================================
     FAVORITE JAVASCRIPT
========================================================= -->

<script>


/* =========================================================
   REMOVE FAVORITE
   HEART BUTTON ONLY
========================================================= */

function removeFavorite(
    foodId,
    card
) {


    const formData =
        new FormData();


    formData.append(
        "food_id",
        foodId
    );


    fetch(
        "favorite_action.php",
        {
            method: "POST",
            body: formData
        }
    )


    .then(
        response =>
            response.json()
    )


    .then(
        data => {


            if (
                data.success &&
                data.status === "removed"
            ) {


                if (card) {

                    card.remove();

                }


                if (
                    document
                        .querySelectorAll(
                            ".food-card"
                        )
                        .length === 0
                ) {

                    location.reload();

                }

            }


        }
    )


    .catch(
        error => {

            console.log(
                error
            );

        }
    );

}


/* =========================================================
   HEART BUTTON
========================================================= */

document
    .querySelectorAll(
        ".favorite-btn"
    )
    .forEach(
        function(button) {


            button.addEventListener(
                "click",
                function() {


                    const foodId =
                        this.getAttribute(
                            "data-food-id"
                        );


                    const card =
                        document.getElementById(
                            "favorite-card-" +
                            foodId
                        );


                    removeFavorite(
                        foodId,
                        card
                    );


                }
            );


        }
    );


</script>


</body>

</html>