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
   SEARCH
========================================================= */

$search = trim(
    $_GET["search"] ?? ""
);


/* =========================================================
   GET USERS
========================================================= */

if ($search !== "") {

    $user_stmt = $conn->prepare(
        "SELECT
            id,
            name,
            email,
            mobile,
            address,
            created_at
         FROM users
         WHERE
            name LIKE ?
            OR email LIKE ?
            OR mobile LIKE ?
         ORDER BY id DESC"
    );


    $search_value =
        "%" . $search . "%";


    $user_stmt->bind_param(
        "sss",
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $user_stmt = $conn->prepare(
        "SELECT
            id,
            name,
            email,
            mobile,
            address,
            created_at
         FROM users
         ORDER BY id DESC"
    );
}


$user_stmt->execute();

$user_result =
    $user_stmt->get_result();


/* =========================================================
   TOTAL USERS
========================================================= */

$total_user_result =
    $conn->query(
        "SELECT COUNT(*) AS total
         FROM users"
    );

$total_user_row =
    $total_user_result->fetch_assoc();

$total_users =
    (int) $total_user_row["total"];

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
        Users - Spicy Zone Admin
    </title>

    <link
        rel="stylesheet"
        href="css/users.css"
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

        <a
            href="users.php"
            class="active"
        >
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
     MAIN PAGE
========================================================= -->

<main class="users-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <section class="page-heading">

        <div>

            <p class="eyebrow">
                ADMIN PANEL
            </p>

            <h1>
                Users
            </h1>

            <p>
                View registered customer details.
            </p>

        </div>


        <div class="user-count-box">

            <span>
                Total Users
            </span>

            <strong>
                <?php
                echo number_format(
                    $total_users
                );
                ?>
            </strong>

        </div>

    </section>


    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <section class="search-panel">

        <form
            method="GET"
            action="users.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="<?php
                echo htmlspecialchars(
                    $search
                );
                ?>"
                placeholder="Search by name, email or mobile..."
            >


            <button
                type="submit"
                class="search-btn"
            >
                Search
            </button>


            <?php if ($search !== ""): ?>

                <a
                    href="users.php"
                    class="clear-btn"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </section>


    <!-- =====================================================
         USERS TABLE
    ====================================================== -->

    <section class="users-panel">


        <?php if ($user_result->num_rows > 0): ?>


            <div class="users-table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                User
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Mobile
                            </th>

                            <th>
                                Address
                            </th>

                            <th>
                                Joined
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php
                    while (
                        $user =
                        $user_result->fetch_assoc()
                    ):
                    ?>


                        <tr>


                            <!-- ================= ID ================= -->

                            <td>

                                <span class="user-id">
                                    #
                                    <?php
                                    echo (int)
                                        $user["id"];
                                    ?>
                                </span>

                            </td>


                            <!-- ================= USER ================= -->

                            <td>

                                <div class="user-info">

                                    <div class="user-avatar">

                                        <?php
                                        echo strtoupper(
                                            substr(
                                                (string)
                                                $user["name"],
                                                0,
                                                1
                                            )
                                        );
                                        ?>

                                    </div>


                                    <div>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $user["name"]
                                            );
                                            ?>

                                        </strong>

                                        <span>
                                            Registered User
                                        </span>

                                    </div>

                                </div>

                            </td>


                            <!-- ================= EMAIL ================= -->

                            <td>

                                <a
                                    href="mailto:<?php echo htmlspecialchars($user["email"]); ?>"
                                    class="email-link"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $user["email"]
                                    );
                                    ?>

                                </a>

                            </td>


                            <!-- ================= MOBILE ================= -->

                            <td>

                                <span class="mobile-text">

                                    <?php
                                    echo htmlspecialchars(
                                        $user["mobile"]
                                        ?? "-"
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- ================= ADDRESS ================= -->

                            <td>

                                <span class="address-text">

                                    <?php
                                    echo htmlspecialchars(
                                        $user["address"]
                                        ?? "Address not available"
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- ================= JOINED ================= -->

                            <td>

                                <span class="joined-date">

                                    <?php

                                    if (
                                        !empty(
                                            $user["created_at"]
                                        )
                                    ) {

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $user["created_at"]
                                            )
                                        );

                                    } else {

                                        echo "-";
                                    }

                                    ?>

                                </span>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty-users">

                <h2>
                    No Users Found
                </h2>

                <p>

                    <?php
                    if ($search !== "") {
                        echo "No users matched your search.";
                    } else {
                        echo "There are no registered users yet.";
                    }
                    ?>

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