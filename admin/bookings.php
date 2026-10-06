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
   STATUS UPDATE
========================================================= */

$message = "";
$message_type = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {

    $booking_id =
        isset($_POST["booking_id"])
        ? (int) $_POST["booking_id"]
        : 0;


    $new_status =
        strtolower(
            trim(
                $_POST["new_status"] ?? ""
            )
        );


    $allowed_statuses = [
        "pending",
        "confirmed",
        "occupied",
        "completed",
        "cancelled"
    ];


    if (
        $booking_id <= 0 ||
        !in_array(
            $new_status,
            $allowed_statuses,
            true
        )
    ) {

        $message =
            "Invalid booking status.";

        $message_type =
            "error";

    } else {


        /* =================================================
           GET CURRENT BOOKING
        ================================================= */

        $check_stmt = $conn->prepare(
            "SELECT
                id,
                table_id,
                reservation_date,
                reservation_time,
                start_time,
                end_time,
                status
             FROM reservations
             WHERE id = ?
             LIMIT 1"
        );


        $check_stmt->bind_param(
            "i",
            $booking_id
        );


        $check_stmt->execute();


        $check_result =
            $check_stmt->get_result();


        if (
            $check_result->num_rows !== 1
        ) {

            $message =
                "Booking not found.";

            $message_type =
                "error";

        } else {

            $booking =
                $check_result->fetch_assoc();


            /* =================================================
               PREPARE START / END TIME
            ================================================= */

            $booking_start_time =
                $booking["start_time"]
                ??
                $booking["reservation_time"];


            $booking_end_time =
                $booking["end_time"];


            /*
                Old bookings may not have start_time/end_time.

                Start = reservation_time
                End   = Start + 30 minutes
            */

            if (
                empty($booking_start_time)
            ) {

                $booking_start_time =
                    $booking["reservation_time"];
            }


            if (
                empty($booking_end_time) &&
                !empty($booking_start_time)
            ) {

                $booking_end_timestamp =
                    strtotime(
                        $booking_start_time
                    );


                if (
                    $booking_end_timestamp !== false
                ) {

                    $booking_end_time =
                        date(
                            "H:i:s",
                            $booking_end_timestamp + (30 * 60)
                        );
                }
            }


            /* =================================================
               PREVENT DOUBLE BOOKING WHEN CONFIRMING
            ================================================= */

            if (
                $new_status === "confirmed"
            ) {


                /*
                    Checks every table assigned
                    to the current booking.

                    Existing occupied booking:
                    blocks table regardless of time.

                    Existing pending / confirmed booking:
                    blocks only when 30-minute estimated
                    slots overlap.
                */

                $availability_stmt =
                    $conn->prepare(
                        "SELECT
                            r.id

                         FROM reservations r

                         WHERE r.id <> ?

                           AND r.reservation_date = ?

                           AND (

                               r.status = 'occupied'

                               OR (

                                   r.status IN (
                                       'pending',
                                       'confirmed'
                                   )

                                   AND COALESCE(
                                       r.start_time,
                                       r.reservation_time
                                   ) < ?

                                   AND COALESCE(
                                       r.end_time,
                                       ADDTIME(
                                           r.reservation_time,
                                           '00:30:00'
                                       )
                                   ) > ?
                               )

                           )

                           AND EXISTS (

                               SELECT 1

                               FROM (

                                   SELECT
                                       ?
                                       AS table_id

                                   UNION

                                   SELECT
                                       reservation_tables.table_id

                                   FROM reservation_tables

                                   WHERE reservation_tables.reservation_id = ?

                               ) AS current_booking_tables

                               INNER JOIN (

                                   SELECT
                                       reservation_tables2.table_id

                                   FROM reservation_tables reservation_tables2

                                   WHERE reservation_tables2.reservation_id = r.id

                                   UNION

                                   SELECT
                                       r.table_id

                               ) AS existing_booking_tables

                               ON current_booking_tables.table_id =
                                  existing_booking_tables.table_id

                           )

                         LIMIT 1"
                    );


                if (!$availability_stmt) {

                    $message =
                        "Availability check error: " .
                        $conn->error;

                    $message_type =
                        "error";

                } else {


                    $availability_stmt->bind_param(
                        "isssii",
                        $booking_id,
                        $booking["reservation_date"],
                        $booking_end_time,
                        $booking_start_time,
                        $booking["table_id"],
                        $booking_id
                    );


                    $availability_stmt->execute();


                    $availability_result =
                        $availability_stmt->get_result();


                    if (
                        $availability_result->num_rows > 0
                    ) {

                        $message =
                            "One of the selected tables is already unavailable during the selected time slot.";

                        $message_type =
                            "error";

                    } else {


                        /* =========================================
                           UPDATE STATUS
                        ========================================== */

                        $update_stmt =
                            $conn->prepare(
                                "UPDATE reservations
                                 SET status = ?
                                 WHERE id = ?"
                            );


                        $update_stmt->bind_param(
                            "si",
                            $new_status,
                            $booking_id
                        );


                        if (
                            $update_stmt->execute()
                        ) {

                            $message =
                                "Booking status updated successfully.";

                            $message_type =
                                "success";

                        } else {

                            $message =
                                "Unable to update booking status.";

                            $message_type =
                                "error";
                        }


                        $update_stmt->close();
                    }


                    $availability_stmt->close();
                }


            } else {


                /* =================================================
                   NORMAL STATUS UPDATE
                ================================================= */

                $update_stmt =
                    $conn->prepare(
                        "UPDATE reservations
                         SET status = ?
                         WHERE id = ?"
                    );


                $update_stmt->bind_param(
                    "si",
                    $new_status,
                    $booking_id
                );


                if (
                    $update_stmt->execute()
                ) {

                    $message =
                        "Booking status updated successfully.";

                    $message_type =
                        "success";

                } else {

                    $message =
                        "Unable to update booking status.";

                    $message_type =
                        "error";
                }


                $update_stmt->close();
            }
        }


        $check_stmt->close();
    }
}


/* =========================================================
   GET BOOKINGS

   Supports:
   - Old single-table bookings
   - New multi-table bookings
   - Start Time
   - End Time
========================================================= */

$booking_stmt = $conn->prepare(
    "SELECT
        r.id,
        r.user_id,
        r.table_id,
        r.reservation_date,
        r.reservation_time,
        r.start_time,
        r.end_time,
        r.guests,
        r.status,

        u.name AS user_name,
        u.email AS user_email,

        COALESCE(
            table_info.table_numbers,
            single_table.table_number
        ) AS table_numbers,

        COALESCE(
            table_info.total_seats,
            single_table.seats
        ) AS total_seats

     FROM reservations r

     LEFT JOIN users u
        ON r.user_id = u.id

     LEFT JOIN restaurant_tables single_table
        ON r.table_id = single_table.id

     LEFT JOIN (

         SELECT
             reservation_tables.reservation_id,

             GROUP_CONCAT(
                 DISTINCT restaurant_tables.table_number
                 ORDER BY restaurant_tables.id ASC
                 SEPARATOR ', '
             ) AS table_numbers,

             SUM(
                 restaurant_tables.seats
             ) AS total_seats

         FROM reservation_tables

         INNER JOIN restaurant_tables
            ON reservation_tables.table_id =
               restaurant_tables.id

         GROUP BY
             reservation_tables.reservation_id

     ) AS table_info

        ON r.id =
           table_info.reservation_id

     ORDER BY
        r.reservation_date DESC,
        COALESCE(
            r.start_time,
            r.reservation_time
        ) DESC,
        r.id DESC"
);


if (!$booking_stmt) {

    die(
        "Booking query error: " .
        $conn->error
    );
}


$booking_stmt->execute();


$booking_result =
    $booking_stmt->get_result();

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
        Manage Bookings - Spicy Zone Admin
    </title>


    <link
        rel="stylesheet"
        href="css/bookings.css"
    >


    <style>

        .multi-table-info {
            line-height: 1.45;
        }

        .multi-table-info strong {
            display: block;
        }

        .multi-table-info span {
            display: block;
            margin-top: 4px;
        }

        .time-note {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            opacity: 0.7;
            white-space: nowrap;
        }

        .time-text {
            white-space: nowrap;
        }

    </style>

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


        <a
            href="bookings.php"
            class="active"
        >
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
     PAGE
========================================================= -->

<main class="bookings-page">


    <section class="page-heading">


        <div>

            <p class="eyebrow">
                ADMIN PANEL
            </p>


            <h1>
                Manage Bookings
            </h1>


            <p>
                View table reservations and update their status.
            </p>

        </div>


    </section>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if (
        $message !== ""
    ): ?>


        <div
            class="admin-message
            <?php
            echo htmlspecialchars(
                $message_type
            );
            ?>"
        >

            <?php

            echo htmlspecialchars(
                $message
            );

            ?>

        </div>


    <?php endif; ?>


    <!-- =====================================================
         BOOKINGS TABLE
    ====================================================== -->

    <section class="bookings-panel">


        <?php if (
            $booking_result->num_rows > 0
        ): ?>


            <div class="bookings-table-wrap">


                <table>


                    <thead>


                        <tr>


                            <th>
                                Customer
                            </th>


                            <th>
                                Tables
                            </th>


                            <th>
                                Date
                            </th>


                            <th>
                                Start Time
                            </th>


                            <th>
                                End Time
                            </th>


                            <th>
                                Guests
                            </th>


                            <th>
                                Status
                            </th>


                            <th>
                                Update
                            </th>


                        </tr>


                    </thead>


                    <tbody>


                    <?php

                    while (
                        $booking =
                        $booking_result->fetch_assoc()
                    ):

                    ?>


                        <?php

                        $raw_status =
                            strtolower(
                                trim(
                                    (string)
                                    $booking["status"]
                                )
                            );


                        $display_status =
                            ucfirst(
                                $raw_status
                            );


                        $table_numbers =
                            $booking["table_numbers"]
                            ??
                            "-";


                        $total_seats =
                            (int)
                            (
                                $booking["total_seats"]
                                ??
                                0
                            );


                        /* =====================================
                           START TIME
                        ====================================== */

                        $start_time =
                            $booking["start_time"]
                            ??
                            $booking["reservation_time"];


                        /* =====================================
                           END TIME
                        ====================================== */

                        $end_time =
                            $booking["end_time"];


                        /*
                            Old bookings may not have end_time.
                            Automatically calculate estimated
                            end time as +30 minutes.
                        */

                        if (
                            empty($end_time) &&
                            !empty($start_time)
                        ) {

                            $end_timestamp =
                                strtotime(
                                    $start_time
                                );


                            if (
                                $end_timestamp !== false
                            ) {

                                $end_time =
                                    date(
                                        "H:i:s",
                                        $end_timestamp + (30 * 60)
                                    );
                            }
                        }

                        ?>


                        <tr>


                            <!-- =================================================
                                 CUSTOMER
                            ================================================== -->

                            <td>


                                <div class="customer-info">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $booking[
                                                "user_name"
                                            ]
                                            ??
                                            "User"
                                        );

                                        ?>

                                    </strong>


                                    <span>

                                        <?php

                                        echo htmlspecialchars(
                                            $booking[
                                                "user_email"
                                            ]
                                            ??
                                            ""
                                        );

                                        ?>

                                    </span>


                                </div>


                            </td>


                            <!-- =================================================
                                 TABLES
                            ================================================== -->

                            <td>


                                <div class="table-info multi-table-info">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $table_numbers
                                        );

                                        ?>

                                    </strong>


                                    <span>

                                        <?php

                                        echo $total_seats;

                                        ?>

                                        seats

                                    </span>


                                </div>


                            </td>


                            <!-- =================================================
                                 DATE
                            ================================================== -->

                            <td>


                                <strong class="date-text">

                                    <?php

                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $booking[
                                                "reservation_date"
                                            ]
                                        )
                                    );

                                    ?>

                                </strong>


                            </td>


                            <!-- =================================================
                                 START TIME
                            ================================================== -->

                            <td>


                                <strong class="time-text">

                                    <?php

                                    echo !empty($start_time)
                                        ? date(
                                            "h:i A",
                                            strtotime(
                                                $start_time
                                            )
                                        )
                                        : "-";

                                    ?>

                                </strong>


                            </td>


                            <!-- =================================================
                                 END TIME
                            ================================================== -->

                            <td>


                                <strong class="time-text">

                                    <?php

                                    echo !empty($end_time)
                                        ? date(
                                            "h:i A",
                                            strtotime(
                                                $end_time
                                            )
                                        )
                                        : "-";

                                    ?>

                                </strong>


                                <?php if (
                                    !empty($end_time)
                                ): ?>

                                    <small class="time-note">
                                        Estimated slot
                                    </small>

                                <?php endif; ?>


                            </td>


                            <!-- =================================================
                                 GUESTS
                            ================================================== -->

                            <td>


                                <span class="guest-count">

                                    <?php

                                    echo (int)
                                        $booking[
                                            "guests"
                                        ];

                                    ?>

                                </span>


                            </td>


                            <!-- =================================================
                                 STATUS
                            ================================================== -->

                            <td>


                                <span
                                    class="
                                        status-badge
                                        <?php
                                        echo htmlspecialchars(
                                            $raw_status
                                        );
                                        ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $display_status
                                    );

                                    ?>

                                </span>


                            </td>


                            <!-- =================================================
                                 UPDATE
                            ================================================== -->

                            <td>


                                <form
                                    method="POST"
                                    action=""
                                    class="status-form"
                                >


                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?php
                                        echo (int)
                                            $booking["id"];
                                        ?>"
                                    >


                                    <select
                                        name="new_status"
                                        class="status-select"
                                    >


                                        <option
                                            value="pending"
                                            <?php
                                            echo $raw_status ===
                                                "pending"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Pending
                                        </option>


                                        <option
                                            value="confirmed"
                                            <?php
                                            echo $raw_status ===
                                                "confirmed"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Confirmed
                                        </option>


                                        <option
                                            value="occupied"
                                            <?php
                                            echo $raw_status ===
                                                "occupied"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Occupied
                                        </option>


                                        <option
                                            value="completed"
                                            <?php
                                            echo $raw_status ===
                                                "completed"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Completed
                                        </option>


                                        <option
                                            value="cancelled"
                                            <?php
                                            echo $raw_status ===
                                                "cancelled"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Cancelled
                                        </option>


                                    </select>


                                    <button
                                        type="submit"
                                        name="update_status"
                                        value="1"
                                        class="update-btn"
                                    >
                                        Update
                                    </button>


                                </form>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div class="empty-bookings">


                <h2>
                    No Bookings Found
                </h2>


                <p>
                    There are no table reservations in the system yet.
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