<?php

session_start();
require_once "db.php";


/* =========================================================
   FILTER VALUES
========================================================= */

$category_name = isset($_GET['category'])
    ? trim($_GET['category'])
    : '';

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';


/* =========================================================
   PAGE TITLE
========================================================= */

$selected_category_name = 'Our Menu';

if ($category_name !== '') {

    $selected_category_name = $category_name;
}


/* =========================================================
   MENU TIMINGS
   Food items are NOT hidden based on these timings.
   These are only displayed inside Menu Card.
========================================================= */

$menu_timings = [

    'Morning Bites' => [
        'time' => '7:00 AM - 11:00 AM',
        'type' => 'Breakfast'
    ],

    'Signature Meals' => [
        'time' => '12:00 PM - 3:30 PM',
        'type' => 'Lunch'
    ],

    'Dinner Delights' => [
        'time' => '7:00 PM - 11:00 PM',
        'type' => 'Dinner'
    ],

    'Quick Cravings' => [
        'time' => 'Available All Day',
        'type' => 'Snacks'
    ],

    'Chill & Sip' => [
        'time' => 'Available All Day',
        'type' => 'Juice / Drinks'
    ],

    'Sweet Treats' => [
        'time' => 'Available All Day',
        'type' => 'Sweets / Desserts'
    ]

];


/* =========================================================
   GET CATEGORIES
========================================================= */

$category_sql = "
    SELECT
        id,
        name
    FROM categories
    ORDER BY id ASC
";

$category_result =
    $conn->query($category_sql);


/* =========================================================
   BUILD FOOD QUERY
========================================================= */

$where = "
    WHERE foods.is_available = 1
";

$params = [];

$types = '';


/* =========================================================
   CATEGORY FILTER
========================================================= */

if ($category_name !== '') {

    $where .= "
        AND categories.name = ?
    ";

    $params[] =
        $category_name;

    $types .= 's';
}


/* =========================================================
   SEARCH FILTER
========================================================= */

if ($search !== '') {

    $where .= "
        AND (
            foods.name LIKE ?
            OR foods.description LIKE ?
            OR categories.name LIKE ?
        )
    ";

    $search_value =
        '%' . $search . '%';


    $params[] =
        $search_value;

    $params[] =
        $search_value;

    $params[] =
        $search_value;

    $types .= 'sss';
}


/* =========================================================
   FOOD QUERY
========================================================= */

$sql = "
    SELECT

        foods.id,

        foods.name,

        foods.price,

        foods.image,

        foods.description,

        categories.name AS category_name,

        ROUND(
            COALESCE(
                AVG(reviews.rating),
                0
            ),
            1
        ) AS average_rating,

        COUNT(reviews.id) AS review_count

    FROM foods

    INNER JOIN categories
        ON foods.category_id = categories.id

    LEFT JOIN reviews
        ON foods.id = reviews.food_id

    $where

    GROUP BY

        foods.id,

        foods.name,

        foods.price,

        foods.image,

        foods.description,

        categories.name

    ORDER BY foods.id ASC
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        'Food query error: ' .
        $conn->error
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();


$food_result =
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
        Spicy Zone Restaurant - Menu
    </title>

    <link
        rel="stylesheet"
        href="css/menu.css"
    >


    <!-- =====================================================
         MENU CARD STYLE
    ====================================================== -->

    <style>

        /* =====================================================
           ACTIVE NAV
        ====================================================== */

        .nav-links a.active {

            color: #3a8f3a;

            font-weight: 700;

        }


        /* =====================================================
           MENU CARD BUTTON
        ====================================================== */

        .menu-card-trigger {

            position: fixed;

            right: 0;

            top: 50%;

            transform: translateY(-50%);

            z-index: 9998;

            border: none;

            background: #3a8f3a;

            color: #ffffff;

            padding: 15px 12px;

            border-radius:
                10px 0 0 10px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 700;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.18);

            writing-mode:
                vertical-rl;

            text-orientation:
                mixed;

            transition:
                0.25s ease;

        }


        .menu-card-trigger:hover {

            padding-right: 16px;

            background: #2f7830;

        }


        /* =====================================================
           OVERLAY
        ====================================================== */

        .menu-card-overlay {

            position: fixed;

            inset: 0;

            background:
                rgba(0, 0, 0, 0.25);

            z-index: 9999;

            opacity: 0;

            visibility: hidden;

            transition:
                opacity 0.25s ease,
                visibility 0.25s ease;

        }


        .menu-card-overlay.show {

            opacity: 1;

            visibility: visible;

        }


        /* =====================================================
           SIDE MENU CARD
        ====================================================== */

        .menu-card-panel {

            position: fixed;

            top: 0;

            right: 0;

            width: 360px;

            max-width: 90%;

            height: 100vh;

            background: #ffffff;

            z-index: 10000;

            padding: 28px 25px;

            box-sizing: border-box;

            overflow-y: auto;

            box-shadow:
                -5px 0 25px
                rgba(0, 0, 0, 0.18);

            transform:
                translateX(100%);

            transition:
                transform 0.3s ease;

        }


        .menu-card-panel.show {

            transform:
                translateX(0);

        }


        /* =====================================================
           PANEL HEADER
        ====================================================== */

        .menu-card-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding-bottom: 18px;

            border-bottom:
                1px solid #eeeeee;

            margin-bottom: 18px;

        }


        .menu-card-header h2 {

            margin: 0;

            color: #222222;

            font-size: 22px;

        }


        .menu-card-header p {

            margin: 5px 0 0;

            color: #888888;

            font-size: 12px;

        }


        .menu-card-close {

            width: 34px;

            height: 34px;

            border: none;

            border-radius: 50%;

            background: #f2f2f2;

            color: #444444;

            font-size: 20px;

            cursor: pointer;

            line-height: 34px;

        }


        .menu-card-close:hover {

            background: #e8e8e8;

        }


        /* =====================================================
           TIMING ITEMS
        ====================================================== */

        .menu-timing-item {

            padding: 17px 0;

            border-bottom:
                1px solid #eeeeee;

        }


        .menu-timing-item:last-child {

            border-bottom: none;

        }


        .menu-timing-name {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 7px;

        }


        .menu-timing-name strong {

            color: #333333;

            font-size: 15px;

        }


        .menu-timing-type {

            font-size: 10px;

            font-weight: 700;

            padding: 4px 7px;

            border-radius: 20px;

            background: #f0f7f0;

            color: #3a8f3a;

            white-space: nowrap;

        }


        .menu-timing-time {

            color: #3a8f3a;

            font-size: 13px;

            font-weight: 700;

        }


        .menu-timing-note {

            margin-top: 15px;

            padding: 12px;

            background: #f8f8f8;

            border-radius: 8px;

            color: #777777;

            font-size: 11px;

            line-height: 1.5;

        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 600px) {

            .menu-card-panel {

                width: 320px;

                max-width: 88%;

            }


            .menu-card-trigger {

                font-size: 11px;

                padding: 13px 10px;

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


        <a
            href="menu.php"
            class="active"
        >
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


        <?php if (
            isset(
                $_SESSION['user_id']
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
     MENU CARD FLOATING BUTTON
========================================================= -->

<button
    type="button"
    class="menu-card-trigger"
    id="menuCardTrigger"
>
    🍽 Menu Card
</button>


<!-- =========================================================
     OVERLAY
========================================================= -->

<div
    class="menu-card-overlay"
    id="menuCardOverlay"
></div>


<!-- =========================================================
     MENU CARD SIDE PANEL
========================================================= -->

<aside
    class="menu-card-panel"
    id="menuCardPanel"
>


    <div class="menu-card-header">


        <div>

            <h2>
                Menu Card
            </h2>

            <p>
                Food timings
            </p>

        </div>


        <button
            type="button"
            class="menu-card-close"
            id="menuCardClose"
        >
            ×
        </button>


    </div>


    <!-- =====================================================
         TIMINGS
    ====================================================== -->

    <?php foreach (
        $menu_timings as $menu_name => $menu_data
    ): ?>


        <div class="menu-timing-item">


            <div class="menu-timing-name">


                <strong>

                    <?php
                    echo htmlspecialchars(
                        $menu_name
                    );
                    ?>

                </strong>


                <span class="menu-timing-type">

                    <?php
                    echo htmlspecialchars(
                        $menu_data['type']
                    );
                    ?>

                </span>


            </div>


            <div class="menu-timing-time">

                <?php
                echo htmlspecialchars(
                    $menu_data['time']
                );
                ?>

            </div>


        </div>


    <?php endforeach; ?>


    <div class="menu-timing-note">

        <strong>
            Note:
        </strong>

        Explore the menu timings and order your favourite food at the right time.

    </div>


</aside>


<!-- =========================================================
     MENU HEADER
========================================================= -->

<section class="menu-header">


    <p class="small-heading">
        SPICY ZONE MENU
    </p>


    <h1>

        <?php
        echo htmlspecialchars(
            $selected_category_name
        );
        ?>

    </h1>


    <p>
        Explore our delicious selection of food.
    </p>


</section>


<!-- =========================================================
     SEARCH
========================================================= -->

<section class="search-section">


    <form
        method="GET"
        action="menu.php"
        class="search-form"
    >


        <?php if (
            $category_name !== ''
        ): ?>


            <input
                type="hidden"
                name="category"
                value="<?php
                echo htmlspecialchars(
                    $category_name
                );
                ?>"
            >


        <?php endif; ?>


        <input
            type="text"
            name="search"
            value="<?php
            echo htmlspecialchars(
                $search
            );
            ?>"
            placeholder="Search for food..."
            class="search-input"
        >


        <button
            type="submit"
            class="search-btn"
        >
            Search
        </button>


        <?php if (
            $search !== ''
        ): ?>


            <?php if (
                $category_name !== ''
            ): ?>


                <a
                    href="menu.php?category=<?php
                    echo urlencode(
                        $category_name
                    );
                    ?>"
                    class="clear-btn"
                >
                    Clear
                </a>


            <?php else: ?>


                <a
                    href="menu.php"
                    class="clear-btn"
                >
                    Clear
                </a>


            <?php endif; ?>


        <?php endif; ?>


    </form>


</section>


<!-- =========================================================
     CATEGORY FILTER
========================================================= -->

<section class="category-filter">


    <a
        href="menu.php"
        class="<?php
        echo (
            $category_name === ''
        )
            ? 'active'
            : '';
        ?>"
    >
        All
    </a>


    <?php while (
        $category =
        $category_result->fetch_assoc()
    ): ?>


        <a
            href="menu.php?category=<?php
            echo urlencode(
                $category['name']
            );
            ?>"
            class="<?php
            echo (
                $category_name ===
                $category['name']
            )
                ? 'active'
                : '';
            ?>"
        >

            <?php
            echo htmlspecialchars(
                $category['name']
            );
            ?>

        </a>


    <?php endwhile; ?>


</section>


<!-- =========================================================
     FOOD SECTION
========================================================= -->

<section class="food-section">


    <?php if (
        $food_result->num_rows > 0
    ): ?>


        <div class="food-grid">


            <?php while (
                $food =
                $food_result->fetch_assoc()
            ): ?>


                <div class="food-card">


                    <!-- =============================================
                         IMAGE
                    ============================================== -->

                    <div class="food-image">


                        <img
                            src="images/<?php
                            echo htmlspecialchars(
                                $food['image']
                            );
                            ?>"
                            alt="<?php
                            echo htmlspecialchars(
                                $food['name']
                            );
                            ?>"
                        >


                        <?php if (
                            isset(
                                $_SESSION['user_id']
                            )
                        ): ?>


                            <button
                                type="button"
                                class="favorite-btn"
                                data-food-id="<?php
                                echo (int)
                                    $food['id'];
                                ?>"
                                title="Add to Favorites"
                            >
                                ♡
                            </button>


                        <?php endif; ?>


                    </div>


                    <!-- =============================================
                         FOOD DETAILS
                    ============================================== -->

                    <div class="food-details">


                        <p class="food-category">

                            <?php
                            echo htmlspecialchars(
                                $food[
                                    'category_name'
                                ]
                            );
                            ?>

                        </p>


                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $food['name']
                            );
                            ?>

                        </h2>


                        <?php if (
                            !empty(
                                $food['description']
                            )
                        ): ?>


                            <p class="food-description">

                                <?php
                                echo htmlspecialchars(
                                    $food[
                                        'description'
                                    ]
                                );
                                ?>

                            </p>


                        <?php endif; ?>


                        <!-- =============================================
                             RATING
                        ============================================== -->

                        <div class="rating">


                            <span class="rating-star">
                                ★
                            </span>


                            <strong>


                                <?php if (
                                    (int)
                                    $food[
                                        'review_count'
                                    ] > 0
                                ): ?>


                                    <?php

                                    echo number_format(
                                        (float)
                                        $food[
                                            'average_rating'
                                        ],
                                        1
                                    );

                                    ?>


                                <?php else: ?>


                                    New


                                <?php endif; ?>


                            </strong>


                            <span class="review-count">


                                <?php if (
                                    (int)
                                    $food[
                                        'review_count'
                                    ] > 0
                                ): ?>


                                    (
                                    <?php
                                    echo (int)
                                        $food[
                                            'review_count'
                                        ];
                                    ?>
                                    Reviews)


                                <?php else: ?>


                                    (No Reviews)


                                <?php endif; ?>


                            </span>


                        </div>


                        <!-- =============================================
                             BOTTOM
                        ============================================== -->

                        <div class="food-bottom">


                            <div>

                                <p class="price">

                                    ₹<?php

                                    echo number_format(
                                        (float)
                                        $food['price'],
                                        2
                                    );

                                    ?>

                                </p>

                            </div>


                            <!-- =============================================
                                 ADD TO CART
                            ============================================== -->

                            <?php if (
                                isset(
                                    $_SESSION['user_id']
                                )
                            ): ?>


                                <form
                                    method="POST"
                                    action="add_to_cart.php"
                                >


                                    <input
                                        type="hidden"
                                        name="food_id"
                                        value="<?php
                                        echo (int)
                                            $food['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="cart-btn"
                                    >
                                        Add to Cart
                                    </button>


                                </form>


                            <?php else: ?>


                                <a
                                    href="login.php"
                                    class="cart-btn"
                                >
                                    Login to Order
                                </a>


                            <?php endif; ?>


                        </div>


                        <!-- =============================================
                             VIEW REVIEWS
                        ============================================== -->

                        <?php if (
                            (int)
                            $food[
                                'review_count'
                            ] > 0
                        ): ?>


                            <a
                                href="food-reviews.php?food_id=<?php
                                echo (int)
                                    $food['id'];
                                ?>"
                                class="view-reviews"
                            >
                                View Reviews
                            </a>


                        <?php endif; ?>


                    </div>


                </div>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <div class="empty-menu">


            <h2>
                No Food Found
            </h2>


            <p>
                Try another food name or category.
            </p>


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
     FAVORITE SCRIPT
========================================================= -->

<?php if (
    isset(
        $_SESSION['user_id']
    )
): ?>


<script>

document
    .querySelectorAll(
        '.favorite-btn'
    )
    .forEach(
        function(button) {


            button.addEventListener(
                'click',
                function() {


                    const foodId =
                        this.getAttribute(
                            'data-food-id'
                        );


                    const currentButton =
                        this;


                    const formData =
                        new FormData();


                    formData.append(
                        'food_id',
                        foodId
                    );


                    fetch(
                        'favorite_action.php',
                        {
                            method: 'POST',
                            body: formData
                        }
                    )

                    .then(
                        function(response) {

                            return response.json();

                        }
                    )

                    .then(
                        function(data) {


                            if (
                                data.success
                            ) {


                                if (
                                    data.status ===
                                    'added'
                                ) {

                                    currentButton
                                        .classList
                                        .add(
                                            'active'
                                        );

                                    currentButton
                                        .innerHTML =
                                        '♥';

                                }


                                else if (
                                    data.status ===
                                    'removed'
                                ) {

                                    currentButton
                                        .classList
                                        .remove(
                                            'active'
                                        );

                                    currentButton
                                        .innerHTML =
                                        '♡';

                                }


                            } else {

                                alert(
                                    data.message
                                );

                            }

                        }
                    )

                    .catch(
                        function(error) {

                            console.log(error);

                            alert(
                                'Unable to update favorites.'
                            );

                        }
                    );

                }
            );

        }
    );

</script>


<?php endif; ?>


<!-- =========================================================
     MENU CARD SCRIPT
========================================================= -->

<script>


const menuCardTrigger =
    document.getElementById(
        'menuCardTrigger'
    );


const menuCardPanel =
    document.getElementById(
        'menuCardPanel'
    );


const menuCardOverlay =
    document.getElementById(
        'menuCardOverlay'
    );


const menuCardClose =
    document.getElementById(
        'menuCardClose'
    );


/* =====================================================
   OPEN MENU CARD
===================================================== */

function openMenuCard() {

    menuCardPanel.classList.add(
        'show'
    );

    menuCardOverlay.classList.add(
        'show'
    );

    document.body.style.overflow =
        'hidden';
}


/* =====================================================
   CLOSE MENU CARD
===================================================== */

function closeMenuCard() {

    menuCardPanel.classList.remove(
        'show'
    );

    menuCardOverlay.classList.remove(
        'show'
    );

    document.body.style.overflow =
        '';
}


/* =====================================================
   BUTTON
===================================================== */

menuCardTrigger.addEventListener(
    'click',
    openMenuCard
);


/* =====================================================
   CLOSE BUTTON
===================================================== */

menuCardClose.addEventListener(
    'click',
    closeMenuCard
);


/* =====================================================
   OVERLAY
===================================================== */

menuCardOverlay.addEventListener(
    'click',
    closeMenuCard
);


/* =====================================================
   ESC KEY
===================================================== */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeMenuCard();

        }

    }
);

</script>


</body>

</html>