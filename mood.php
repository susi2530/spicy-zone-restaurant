<?php

session_start();
require_once "db.php";


/* =========================================================
   SELECTED MOOD
========================================================= */

$selected_mood = isset($_GET["mood"])
    ? strtolower(
        trim(
            $_GET["mood"]
        )
    )
    : "";


/* =========================================================
   VALID MOODS
========================================================= */

$valid_moods = [
    "hungry",
    "sweet",
    "spicy"
];


if (
    $selected_mood !== "" &&
    !in_array(
        $selected_mood,
        $valid_moods,
        true
    )
) {

    $selected_mood = "";
}


/* =========================================================
   DISPLAY TITLE
========================================================= */

$display_title =
    "Mood Suggestions";


if ($selected_mood === "hungry") {

    $display_title =
        "Hungry Mood";

} elseif ($selected_mood === "sweet") {

    $display_title =
        "Sweet Mood";

} elseif ($selected_mood === "spicy") {

    $display_title =
        "Spicy Mood";
}


/* =========================================================
   GET MOOD FOODS
========================================================= */

if ($selected_mood !== "") {

    $stmt = $conn->prepare(
        "SELECT
            foods.id,
            foods.name,
            foods.price,
            foods.image,
            foods.description,
            foods.rating,
            categories.name AS category_name
         FROM mood_suggestions
         INNER JOIN foods
            ON mood_suggestions.food_id = foods.id
         INNER JOIN categories
            ON foods.category_id = categories.id
         WHERE mood_suggestions.mood = ?
           AND foods.is_available = 1
         ORDER BY mood_suggestions.id ASC
         LIMIT 10"
    );


    $stmt->bind_param(
        "s",
        $selected_mood
    );


    $stmt->execute();


    $food_result =
        $stmt->get_result();

} else {

    $food_result = false;
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

    <title>
        Mood Suggestions - Spicy Zone Restaurant
    </title>

    <link
        rel="stylesheet"
        href="css/mood.css"
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


        <a
            href="mood.php"
            class="active"
        >
            Mood
        </a>


        <a href="favorites.php">
            Favorites
        </a>


        <a href="cart.php">
            Cart
        </a>


        <?php if (
            isset(
                $_SESSION["user_id"]
            )
        ): ?>


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


<!-- =========================================================
     HEADER
========================================================= -->

<section class="mood-header">


    <p class="small-heading">
        MOOD SUGGESTION
    </p>


    <h1>
        What Are You In The Mood For?
    </h1>


    <p>
        Choose a mood and discover food suggestions from Spicy Zone.
    </p>


</section>


<!-- =========================================================
     MOODS
========================================================= -->

<section class="mood-selection">


    <a
        href="mood.php?mood=hungry"
        class="
            mood-card
            <?php
            echo (
                $selected_mood === "hungry"
            )
                ? "active"
                : "";
            ?>
        "
    >

        <h2>
            Hungry Mood
        </h2>

        <p>
            Filling and satisfying foods
        </p>

    </a>


    <a
        href="mood.php?mood=sweet"
        class="
            mood-card
            <?php
            echo (
                $selected_mood === "sweet"
            )
                ? "active"
                : "";
            ?>
        "
    >

        <h2>
            Sweet Mood
        </h2>

        <p>
            Desserts and sweet treats
        </p>

    </a>


    <a
        href="mood.php?mood=spicy"
        class="
            mood-card
            <?php
            echo (
                $selected_mood === "spicy"
            )
                ? "active"
                : "";
            ?>
        "
    >

        <h2>
            Spicy Mood
        </h2>

        <p>
            Bold and spicy flavours
        </p>

    </a>


</section>


<!-- =========================================================
     FOOD
========================================================= -->

<?php if (
    $selected_mood !== ""
): ?>


<section class="mood-food-section">


    <div class="section-heading">


        <p class="small-heading">

            <?php
            echo strtoupper(
                $display_title
            );
            ?>

        </p>


        <h2>
            Recommended For You
        </h2>


    </div>


    <?php if (
        $food_result &&
        $food_result->num_rows > 0
    ): ?>


        <div class="food-grid">


            <?php while (
                $food =
                $food_result->fetch_assoc()
            ): ?>


                <div class="food-card">


                    <!-- =========================================
                         IMAGE
                    ========================================== -->

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


                        <?php if (
                            isset(
                                $_SESSION["user_id"]
                            )
                        ): ?>


                            <button
                                type="button"
                                class="favorite-btn"
                                data-food-id="<?php
                                echo (int)
                                    $food["id"];
                                ?>"
                                title="Add to Favorites"
                            >
                                ♡
                            </button>


                        <?php endif; ?>


                    </div>


                    <!-- =========================================
                         DETAILS
                    ========================================== -->

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


                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $food["name"]
                            );

                            ?>

                        </h3>


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
                             RATING
                        ====================================== -->

                        <div class="food-bottom">


                            <div>


                                <p class="rating">

                                    ★

                                    <?php

                                    echo number_format(
                                        (float)
                                        $food[
                                            "rating"
                                        ],
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


        <div class="empty-mood">


            <h2>
                No Suggestions Found
            </h2>


            <p>
                No food items are currently available for this mood.
            </p>


        </div>


    <?php endif; ?>


</section>


<?php endif; ?>


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
     FAVORITE SCRIPT
========================================================= -->

<?php if (
    isset(
        $_SESSION["user_id"]
    )
): ?>


<script>


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


                    const currentButton =
                        this;


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
                                data.success
                            ) {


                                if (
                                    data.status ===
                                    "added"
                                ) {


                                    currentButton
                                        .classList
                                        .add(
                                            "active"
                                        );


                                    currentButton
                                        .innerHTML =
                                        "♥";


                                }


                                else if (
                                    data.status ===
                                    "removed"
                                ) {


                                    currentButton
                                        .classList
                                        .remove(
                                            "active"
                                        );


                                    currentButton
                                        .innerHTML =
                                        "♡";


                                }


                            } else {


                                alert(
                                    data.message
                                );


                            }

                        }
                    )


                    .catch(
                        error => {

                            console.log(
                                error
                            );

                            alert(
                                "Unable to update favorites."
                            );

                        }
                    );


                }
            );


        }
    );


</script>


<?php endif; ?>


</body>

</html>