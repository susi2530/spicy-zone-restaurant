<?php

session_start();

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
        Spicy Zone Restaurant
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <!-- =====================================================
         ACTIVE NAV COLOR
    ====================================================== -->

    <style>

        .nav-links a.active {
            color: #3a8f3a;
            font-weight: 700;
        }


        /* =================================================
           CONTACT US
        ================================================= */

        .contact-section {
            width: 90%;
            max-width: 1100px;

            margin: 0 auto 60px;

            padding: 45px 30px;

            text-align: center;

            background: #ffffff;

            border-top: 1px solid #e1e1e1;
        }


        .contact-section h2 {
            color: #333333;

            font-size: 28px;

            font-weight: 800;

            margin-bottom: 10px;
        }


        .contact-section > p {
            color: #666666;

            font-size: 15px;

            line-height: 1.5;

            margin-bottom: 28px;
        }


        .contact-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }


        .contact-card {
            padding: 22px 18px;

            background: #ffffff;

            border: 1px solid #e1e1e1;

            border-radius: 8px;
        }


        .contact-card h3 {
            color: #3a8f3a;

            font-size: 16px;

            font-weight: 800;

            margin-bottom: 8px;
        }


        .contact-card p {
            color: #666666;

            font-size: 13px;

            line-height: 1.5;
        }


        .contact-link {
            color: #666666;

            text-decoration: none;
        }


        .contact-link:hover {
            color: #3a8f3a;
        }


        @media (max-width: 700px) {

            .contact-section {
                padding: 40px 20px;
            }

            .contact-grid {
                grid-template-columns: 1fr;
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


        <a
            href="index.php"
            class="active"
        >
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


        <?php if (
            isset(
                $_SESSION['user_id']
            )
        ): ?>


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
     HERO
========================================================= -->

<section class="hero">


    <img
        src="images/banner.png"
        alt="Spicy Zone Restaurant"
    >


    <div class="hero-content">


        <p class="welcome">
            WELCOME TO
        </p>


        <h1>
            Spicy Zone Restaurant
        </h1>


        <p class="hero-text">
            Fresh flavours. Great food. Happy moments.
        </p>


        <div class="hero-buttons">


            <a
                href="menu.php"
                class="green-btn"
            >
                Explore Menu
            </a>


            <a
                href="booking.php"
                class="green-btn"
            >
                Book a Table
            </a>


        </div>


    </div>


</section>


<!-- =========================================================
     MENU CATEGORIES
========================================================= -->

<section class="categories">


    <p class="small-heading">
        EXPLORE OUR MENU
    </p>


    <h2>
        Something Delicious for Everyone
    </h2>


    <div class="category-grid">


        <!-- Morning Bites -->

        <a
            href="menu.php?category=Morning%20Bites"
            class="category-card"
        >

            <img
                src="images/idli.png"
                alt="Morning Bites"
            >


            <div class="category-info">

                <h3>
                    Morning Bites
                </h3>

                <p>
                    Dosa, Idli & more
                </p>

            </div>

        </a>


        <!-- Signature Meals -->

        <a
            href="menu.php?category=Signature%20Meals"
            class="category-card"
        >

            <img
                src="images/veg_biryani.png"
                alt="Signature Meals"
            >


            <div class="category-info">

                <h3>
                    Signature Meals
                </h3>

                <p>
                    Biryani, Meals & rice
                </p>

            </div>

        </a>


        <!-- Dinner Delights -->

        <a
            href="menu.php?category=Dinner%20Delights"
            class="category-card"
        >

            <img
                src="images/veg_fried_rice.png"
                alt="Dinner Delights"
            >


            <div class="category-info">

                <h3>
                    Dinner Delights
                </h3>

                <p>
                    Naan, Fried Rice & more
                </p>

            </div>

        </a>


        <!-- Quick Cravings -->

        <a
            href="menu.php?category=Quick%20Cravings"
            class="category-card"
        >

            <img
                src="images/veg_burger.png"
                alt="Quick Cravings"
            >


            <div class="category-info">

                <h3>
                    Quick Cravings
                </h3>

                <p>
                    Burgers, Fries & snacks
                </p>

            </div>

        </a>


        <!-- Chill & Sip -->

        <a
            href="menu.php?category=Chill%20%26%20Sip"
            class="category-card"
        >

            <img
                src="images/mango_juice.png"
                alt="Chill and Sip"
            >


            <div class="category-info">

                <h3>
                    Chill & Sip
                </h3>

                <p>
                    Juices, Shakes & drinks
                </p>

            </div>

        </a>


        <!-- Sweet Treats -->

        <a
            href="menu.php?category=Sweet%20Treats"
            class="category-card"
        >

            <img
                src="images/vanilla_ice_cream.png"
                alt="Sweet Treats"
            >


            <div class="category-info">

                <h3>
                    Sweet Treats
                </h3>

                <p>
                    Ice Cream, Brownie & more
                </p>

            </div>

        </a>


    </div>


</section>


<!-- =========================================================
     WHY CHOOSE US
========================================================= -->

<section class="why-us">


    <p class="small-heading">
        WHY CHOOSE SPICY ZONE?
    </p>


    <h2>
        Good Food. Simple Experience.
    </h2>


    <div class="why-grid">


        <div class="why-card">

            <h3>
                Fresh & Tasty
            </h3>

            <p>
                Carefully prepared food with delicious flavours.
            </p>

        </div>


        <div class="why-card">

            <h3>
                Easy Ordering
            </h3>

            <p>
                Order online, choose takeaway or enjoy dine-in.
            </p>

        </div>


        <div class="why-card">

            <h3>
                Comfortable Dining
            </h3>

            <p>
                Reserve your table and enjoy your meal with ease.
            </p>

        </div>


    </div>


</section>


<!-- =========================================================
     MOOD
========================================================= -->

<section class="mood-section">


    <p class="small-heading">
        MOOD SUGGESTION
    </p>


    <h2>
        Not Sure What to Eat?
    </h2>


    <p>
        Pick your mood and discover something delicious.
    </p>


    <a
        href="mood.php"
        class="green-btn"
    >
        Find My Food
    </a>


</section>


<!-- =========================================================
     DINING
========================================================= -->

<section class="dining-section">


    <p class="small-heading">
        DINE WITH US
    </p>


    <h2>
        Planning to Visit?
    </h2>


    <p>
        Reserve your table before you arrive.
    </p>


    <a
        href="booking.php"
        class="green-btn"
    >
        Book a Table
    </a>


</section>





<!-- =========================================================
     CONTACT US
========================================================= -->

<section class="contact-section">


    <p class="small-heading">
        CONTACT US
    </p>


    <h2>
        We'd Love to Hear From You
    </h2>


    <p>
        Have a question, need help with an order,
        or want to know more about Spicy Zone?
    </p>


    <div class="contact-grid">


        <!-- ================= ADDRESS ================= -->

        <div class="contact-card">

            <h3>
                Visit Us
            </h3>

            <p>
                Spicy Zone Restaurant
            </p>

        </div>


        <!-- ================= PHONE ================= -->

        <div class="contact-card">

            <h3>
                Call Us
            </h3>

            <p>

                <a
                    href="tel:+919876543021"
                    class="contact-link"
                >
                    +91 98765 43021
                </a>

            </p>

        </div>


        <!-- ================= EMAIL ================= -->

        <div class="contact-card">

            <h3>
                Email Us
            </h3>

            <p>

                <a
                    href="mailto:contact@spicyzone.com"
                    class="contact-link"
                >
                    contact@spicyzone.com
                </a>

            </p>

        </div>


    </div>


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


</body>
</html>