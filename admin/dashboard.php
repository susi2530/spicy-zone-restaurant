<?php

/* =========================================================
   SESSION SETTINGS
========================================================= */

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => false,
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "../db.php";


/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin-login.php");
    exit();
}

$admin_id = (int) $_SESSION["admin_id"];


/* =========================================================
   GET ADMIN DETAILS
========================================================= */

$admin_stmt = $conn->prepare(
    "SELECT
        admin_id,
        name,
        email
     FROM admins
     WHERE admin_id = ?
     LIMIT 1"
);

$admin_stmt->bind_param(
    "i",
    $admin_id
);

$admin_stmt->execute();

$admin_result =
    $admin_stmt->get_result();

$admin =
    $admin_result->fetch_assoc();

$admin_stmt->close();


if (!$admin) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params =
            session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: admin-login.php");
    exit();
}


/* =========================================================
   DASHBOARD COUNTS
========================================================= */


/* ================= USERS ================= */

$user_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users"
);

$user_count_row =
    $user_count_result->fetch_assoc();

$total_users =
    (int) $user_count_row["total"];


/* ================= ORDERS ================= */

$order_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders"
);

$order_count_row =
    $order_count_result->fetch_assoc();

$total_orders =
    (int) $order_count_row["total"];


/* ================= FOOD ITEMS ================= */

$food_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM foods"
);

$food_count_row =
    $food_count_result->fetch_assoc();

$total_foods =
    (int) $food_count_row["total"];


/* ================= RESERVATIONS ================= */

$reservation_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM reservations"
);

$reservation_count_row =
    $reservation_count_result->fetch_assoc();

$total_reservations =
    (int) $reservation_count_row["total"];


/* ================= REVENUE ================= */

$revenue_result = $conn->query(
    "SELECT
        COALESCE(SUM(total), 0) AS revenue
     FROM orders
     WHERE LOWER(payment_status) = 'paid'"
);

$revenue_row =
    $revenue_result->fetch_assoc();

$total_revenue =
    (float) $revenue_row["revenue"];


/* =========================================================
   RECENT ORDERS
========================================================= */

$recent_orders_stmt = $conn->prepare(
    "SELECT
        o.id,
        o.order_number,
        o.total,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.order_type,
        o.created_at,
        u.name AS user_name
     FROM orders o
     LEFT JOIN users u
        ON o.user_id = u.id
     ORDER BY o.created_at DESC
     LIMIT 6"
);

$recent_orders_stmt->execute();

$recent_orders_result =
    $recent_orders_stmt->get_result();


/* =========================================================
   RECENT RESERVATIONS
========================================================= */

$recent_reservation_stmt = $conn->prepare(
    "SELECT
        r.id,
        r.reservation_date,
        r.reservation_time,
        r.guests,
        r.status,
        u.name AS user_name,
        rt.table_number
     FROM reservations r
     LEFT JOIN users u
        ON r.user_id = u.id
     LEFT JOIN restaurant_tables rt
        ON r.table_id = rt.id
     ORDER BY r.reservation_date DESC,
              r.reservation_time DESC
     LIMIT 6"
);

$recent_reservation_stmt->execute();

$recent_reservations_result =
    $recent_reservation_stmt->get_result();

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
        Admin Dashboard - Spicy Zone
    </title>

    <link
        rel="stylesheet"
        href="css/dashboard.css"
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
                Spicy Zone Restaurant
            </strong>

            <span>
                Admin Panel
            </span>

        </div>

    </div>


    <nav class="admin-nav">

        <a
            href="dashboard.php"
            class="active"
        >
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
     PAGE HEADER
========================================================= -->

<main class="dashboard-page">


    <section class="dashboard-heading">

        <div>

            <p class="eyebrow">
                ADMIN PANEL
            </p>

            <h1>
                Dashboard
            </h1>

            <p class="dashboard-subtitle">

                Welcome back,
                <?php
                echo htmlspecialchars(
                    $admin["name"]
                );
                ?>.

                Here's your restaurant overview.

            </p>

        </div>


        <div class="admin-user-box">

            <strong>

                <?php
                echo htmlspecialchars(
                    $admin["name"]
                );
                ?>

            </strong>

            <span>

                <?php
                echo htmlspecialchars(
                    $admin["email"]
                );
                ?>

            </span>

        </div>

    </section>


    <!-- =====================================================
         STAT CARDS
    ====================================================== -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon">
                U
            </div>

            <div>

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

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                O
            </div>

            <div>

                <span>
                    Total Orders
                </span>

                <strong>

                    <?php
                    echo number_format(
                        $total_orders
                    );
                    ?>

                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                F
            </div>

            <div>

                <span>
                    Food Items
                </span>

                <strong>

                    <?php
                    echo number_format(
                        $total_foods
                    );
                    ?>

                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                R
            </div>

            <div>

                <span>
                    Reservations
                </span>

                <strong>

                    <?php
                    echo number_format(
                        $total_reservations
                    );
                    ?>

                </strong>

            </div>

        </div>


        <div class="stat-card revenue-card">

            <div class="stat-icon">
                ₹
            </div>

            <div>

                <span>
                    Paid Revenue
                </span>

                <strong>

                    ₹<?php
                    echo number_format(
                        $total_revenue,
                        2
                    );
                    ?>

                </strong>

            </div>

        </div>


    </section>


    <!-- =====================================================
         RECENT CONTENT
    ====================================================== -->

    <section class="dashboard-columns">


        <!-- ================= RECENT ORDERS ================= -->

        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        LATEST ACTIVITY
                    </p>

                    <h2>
                        Recent Orders
                    </h2>

                </div>


                <a href="orders.php">
                    View All
                </a>

            </div>


            <?php
            if (
                $recent_orders_result->num_rows > 0
            ):
            ?>

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php
                        while (
                            $recent_order =
                            $recent_orders_result->fetch_assoc()
                        ):
                        ?>

                            <?php

                            $recent_order_status =
                                strtoupper(
                                    (string)
                                    $recent_order[
                                        "order_status"
                                    ]
                                );

                            ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $recent_order[
                                                "order_number"
                                            ]
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $recent_order[
                                            "user_name"
                                        ] ?? "User"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            strtolower(
                                                (string)
                                                $recent_order[
                                                    "order_type"
                                                ]
                                            )
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    ₹<?php
                                    echo number_format(
                                        (float)
                                        $recent_order[
                                            "total"
                                        ],
                                        2
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span
                                        class="status-badge
                                        <?php
                                        echo strtolower(
                                            $recent_order_status
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            ucfirst(
                                                strtolower(
                                                    $recent_order_status
                                                )
                                            )
                                        );
                                        ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-panel">
                    No orders found.
                </div>

            <?php endif; ?>


        </div>


        <!-- ================= RECENT BOOKINGS ================= -->

        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        TABLE RESERVATIONS
                    </p>

                    <h2>
                        Recent Bookings
                    </h2>

                </div>


                <a href="bookings.php">
                    View All
                </a>

            </div>


            <?php
            if (
                $recent_reservations_result->num_rows > 0
            ):
            ?>

                <div class="booking-list">

                    <?php
                    while (
                        $booking =
                        $recent_reservations_result->fetch_assoc()
                    ):
                    ?>

                        <div class="booking-item">

                            <div class="booking-main">

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            "user_name"
                                        ] ?? "User"
                                    );
                                    ?>

                                </strong>


                                <span>

                                    Table

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            "table_number"
                                        ] ?? "-"
                                    );
                                    ?>

                                    ·

                                    <?php
                                    echo (int)
                                        $booking["guests"];
                                    ?>

                                    guests

                                </span>

                            </div>


                            <div class="booking-side">

                                <strong>

                                    <?php
                                    echo date(
                                        "d M",
                                        strtotime(
                                            $booking[
                                                "reservation_date"
                                            ]
                                        )
                                    );
                                    ?>

                                </strong>


                                <span>

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime(
                                            $booking[
                                                "reservation_time"
                                            ]
                                        )
                                    );
                                    ?>

                                </span>


                                <small
                                    class="booking-status
                                    <?php
                                    echo strtolower(
                                        (string)
                                        $booking["status"]
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            strtolower(
                                                (string)
                                                $booking["status"]
                                            )
                                        )
                                    );
                                    ?>

                                </small>

                            </div>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="empty-panel">
                    No reservations found.
                </div>

            <?php endif; ?>


        </div>


    </section>


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <section class="quick-actions-panel">

        <p class="panel-label">
            QUICK ACTIONS
        </p>

        <h2>
            Manage Restaurant
        </h2>


        <div class="quick-actions">

            <a href="orders.php">
                Manage Orders
            </a>

            <a href="bookings.php">
                Manage Bookings
            </a>

            <a href="foods.php">
                Manage Foods
            </a>

            <a href="users.php">
                View Users
            </a>

            <a href="reviews.php">
                View Reviews
            </a>

        </div>

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