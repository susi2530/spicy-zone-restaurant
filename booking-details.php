<?php

session_start();
require_once "db.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();
}


$user_id =
    (int) $_SESSION['user_id'];


$booking_id =
    isset($_GET['booking_id'])
        ? (int) $_GET['booking_id']
        : 0;


if ($booking_id <= 0) {

    header(
        "Location: my-bookings.php"
    );

    exit();
}


/* =========================================================
   GET BOOKING
   Supports old single-table and new multi-table bookings
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        reservations.id,
        reservations.reservation_date,
        reservations.reservation_time,
        reservations.start_time,
        reservations.end_time,
        reservations.guests,
        reservations.status,

        CASE
            WHEN COUNT(reservation_tables.table_id) > 0
            THEN GROUP_CONCAT(
                DISTINCT restaurant_tables_multi.table_number
                ORDER BY restaurant_tables_multi.id ASC
                SEPARATOR ', '
            )
            ELSE MAX(restaurant_tables_single.table_number)
        END AS table_numbers,

        CASE
            WHEN COUNT(reservation_tables.table_id) > 0
            THEN SUM(
                DISTINCT restaurant_tables_multi.seats
            )
            ELSE MAX(restaurant_tables_single.seats)
        END AS total_seats

     FROM reservations

     INNER JOIN restaurant_tables AS restaurant_tables_single
        ON reservations.table_id =
           restaurant_tables_single.id

     LEFT JOIN reservation_tables
        ON reservations.id =
           reservation_tables.reservation_id

     LEFT JOIN restaurant_tables AS restaurant_tables_multi
        ON reservation_tables.table_id =
           restaurant_tables_multi.id

     WHERE reservations.id = ?
       AND reservations.user_id = ?

     GROUP BY
        reservations.id,
        reservations.reservation_date,
        reservations.reservation_time,
        reservations.start_time,
        reservations.end_time,
        reservations.guests,
        reservations.status

     LIMIT 1"
);


if (!$stmt) {

    die(
        "Booking query error: " .
        $conn->error
    );
}


$stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if (
    $result->num_rows !== 1
) {

    header(
        "Location: my-bookings.php"
    );

    exit();
}


$booking =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   START / END TIME
========================================================= */

/*
    New bookings:
    start_time = selected booking time
    end_time   = estimated +30 minutes

    Old bookings:
    start_time falls back to reservation_time
    end_time is automatically calculated as +30 minutes
*/


$start_time =
    $booking['start_time']
    ?? $booking['reservation_time'];


$end_time =
    $booking['end_time'];


if (
    empty($start_time)
) {

    $start_time =
        $booking['reservation_time'];
}


if (
    empty($end_time) &&
    !empty($start_time)
) {

    $end_timestamp =
        strtotime($start_time);


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


/* =========================================================
   STATUS
========================================================= */

switch (
    strtolower(
        $booking['status']
    )
) {

    case 'confirmed':

        $display_status =
            'Confirmed';

        break;


    case 'pending':

        $display_status =
            'Pending';

        break;


    case 'cancelled':

        $display_status =
            'Cancelled';

        break;


    case 'completed':

        $display_status =
            'Completed';

        break;


    default:

        $display_status =
            ucfirst(
                strtolower(
                    $booking['status']
                )
            );
}


/* =========================================================
   TABLE DETAILS
========================================================= */

$table_numbers =
    $booking['table_numbers']
    ?? '-';


$total_seats =
    (int)
    (
        $booking['total_seats']
        ?? 0
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
        Booking Details - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/booking-details.css"
    >


    <style>

        .multi-table-value {
            line-height: 1.5;
        }

        .time-note {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            opacity: 0.7;
        }

        .booking-note {
            line-height: 1.5;
        }

        .booking-time-note {
            margin-top: 6px;
            font-size: 12px;
            line-height: 1.5;
            opacity: 0.75;
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


        <a
            href="my-bookings.php"
            class="active"
        >
            My Bookings
        </a>


        <a href="profile.php">
            Profile
        </a>


    </nav>


</header>


<!-- =========================================================
     PAGE
========================================================= -->

<main class="booking-details-page">


    <section class="booking-details-card">


        <div class="details-heading">


            <p class="small-heading">
                TABLE BOOKING
            </p>


            <h1>
                Booking Details
            </h1>


            <p>
                Your reserved dining table information.
            </p>


        </div>


        <!-- =================================================
             STATUS
        ================================================== -->

        <div class="booking-status-box">


            <span>
                Booking Status
            </span>


            <strong>

                <?php

                echo htmlspecialchars(
                    $display_status
                );

                ?>

            </strong>


        </div>


        <!-- =================================================
             DETAILS
        ================================================== -->

        <div class="details-grid">


            <!-- =================================================
                 TABLES
            ================================================== -->

            <div class="detail-box">


                <span>
                    Tables
                </span>


                <strong class="multi-table-value">

                    <?php

                    echo htmlspecialchars(
                        $table_numbers
                    );

                    ?>

                </strong>


            </div>


            <!-- =================================================
                 TOTAL SEATS
            ================================================== -->

            <div class="detail-box">


                <span>
                    Total Seats
                </span>


                <strong>

                    <?php

                    echo $total_seats;

                    ?>

                </strong>


            </div>


            <!-- =================================================
                 DATE
            ================================================== -->

            <div class="detail-box">


                <span>
                    Date
                </span>


                <strong>

                    <?php

                    echo date(
                        'd M Y',
                        strtotime(
                            $booking[
                                'reservation_date'
                            ]
                        )
                    );

                    ?>

                </strong>


            </div>


            <!-- =================================================
                 START TIME
            ================================================== -->

            <div class="detail-box">


                <span>
                    Start Time
                </span>


                <strong>

                    <?php

                    echo !empty($start_time)
                        ? date(
                            'h:i A',
                            strtotime(
                                $start_time
                            )
                        )
                        : '-';

                    ?>

                </strong>


            </div>


            <!-- =================================================
                 END TIME
            ================================================== -->

            <div class="detail-box">


                <span>
                    End Time
                </span>


                <strong>

                    <?php

                    echo !empty($end_time)
                        ? date(
                            'h:i A',
                            strtotime(
                                $end_time
                            )
                        )
                        : '-';

                    ?>

                </strong>


                <?php if (
                    !empty($end_time)
                ): ?>

                    <small class="time-note">
                        Estimated time slot
                    </small>

                <?php endif; ?>


            </div>


            <!-- =================================================
                 GUESTS
            ================================================== -->

            <div class="detail-box">


                <span>
                    Guests
                </span>


                <strong>

                    <?php

                    echo (int)
                        $booking['guests'];

                    ?>

                </strong>


            </div>


            <!-- =================================================
                 BOOKING ID
            ================================================== -->

            <div class="detail-box">


                <span>
                    Booking ID
                </span>


                <strong>

                    #<?php

                    echo (int)
                        $booking['id'];

                    ?>

                </strong>


            </div>


        </div>


        <!-- =================================================
             NOTE
        ================================================== -->

        <div class="booking-note">

            💡 <strong>Note:</strong>
            The selected time is your booking start time.
            The displayed end time is only an estimated time
            slot for table availability and is not a strict
            dining time limit.

        </div>


        <!-- =================================================
             ACTIONS
        ================================================== -->

        <div class="booking-actions">


            <a
                href="my-bookings.php"
                class="primary-btn"
            >
                My Bookings
            </a>


            <a
                href="index.php"
                class="secondary-btn"
            >
                Back to Home
            </a>


        </div>


    </section>


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