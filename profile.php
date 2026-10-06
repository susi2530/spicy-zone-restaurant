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
   GET USER
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        name,
        email,
        mobile,
        address
     FROM users
     WHERE id = ?"
);


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$user =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   USER INITIAL
========================================================= */

$initial =
    strtoupper(
        substr(
            trim(
                $user["name"]
            ),
            0,
            1
        )
    );

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
        My Profile - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/profile.css"
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


        <a href="cart.php">
            Cart
        </a>


        <a href="my-bookings.php">
            My Bookings
        </a>


        <a
            href="profile.php"
            class="active"
        >
            Profile
        </a>


    </nav>


</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="profile-page">


    <div class="profile-container">


        <!-- =================================================
             PROFILE HEADER
        ================================================== -->

        <section class="profile-header">


            <div class="profile-avatar">

                <?php

                echo htmlspecialchars(
                    $initial
                );

                ?>

            </div>


            <h1>

                <?php

                echo htmlspecialchars(
                    $user["name"]
                );

                ?>

            </h1>


            <p>

                <?php

                echo htmlspecialchars(
                    $user["email"]
                );

                ?>

            </p>


            <a
                href="edit-profile.php"
                class="edit-btn"
            >
                Edit Profile
            </a>


        </section>


        <!-- =================================================
             ACCOUNT DETAILS
        ================================================== -->

        <section class="profile-card">


            <h2>
                Personal Information
            </h2>


            <div class="detail-row">


                <span>
                    Name
                </span>


                <strong>

                    <?php

                    echo htmlspecialchars(
                        $user["name"]
                    );

                    ?>

                </strong>


            </div>


            <div class="detail-row">


                <span>
                    Email
                </span>


                <strong>

                    <?php

                    echo htmlspecialchars(
                        $user["email"]
                    );

                    ?>

                </strong>


            </div>


            <div class="detail-row">


                <span>
                    Mobile
                </span>


                <strong>

                    <?php

                    echo htmlspecialchars(
                        $user["mobile"]
                    );

                    ?>

                </strong>


            </div>


        </section>


        <!-- =================================================
             ADDRESS
        ================================================== -->

        <section class="profile-card">


            <div class="card-heading">


                <h2>
                    Saved Address
                </h2>


                <a href="edit-profile.php">
                    Edit
                </a>


            </div>


            <?php if (
                !empty(
                    $user["address"]
                )
            ): ?>


                <p class="address-text">

                    <?php

                    echo nl2br(
                        htmlspecialchars(
                            $user["address"]
                        )
                    );

                    ?>

                </p>


            <?php else: ?>


                <p class="empty-text">
                    No address saved yet.
                </p>


                <a
                    href="edit-profile.php"
                    class="small-btn"
                >
                    Add Address
                </a>


            <?php endif; ?>


        </section>


        <!-- =================================================
             ACCOUNT SETTINGS
        ================================================== -->

        <section class="profile-card">


            <h2>
                Account Settings
            </h2>


            <a
                href="change-password.php"
                class="setting-option"
            >


                <div>


                    <strong>
                        Change Password
                    </strong>


                    <p>
                        Update your account password
                    </p>


                </div>


                <span>
                    ›
                </span>


            </a>


            <a
                href="orders.php"
                class="setting-option"
            >


                <div>


                    <strong>
                        My Orders
                    </strong>


                    <p>
                        View your previous orders
                    </p>


                </div>


                <span>
                    ›
                </span>


            </a>


        </section>


        <!-- =================================================
             LOGOUT
        ================================================== -->

        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>


    </div>


</main>


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