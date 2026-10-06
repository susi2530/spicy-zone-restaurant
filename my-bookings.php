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
   GET BOOKINGS
   Supports:
   - Old single-table bookings
   - New multi-table bookings
   - Start Time
   - End Time
========================================================= */

$stmt = $conn->prepare(
    "SELECT
        r.id,
        r.reservation_date,
        r.reservation_time,
        r.start_time,
        r.end_time,
        r.guests,
        r.status,

        COALESCE(
            rt_info.table_numbers,
            single_table.table_number
        ) AS table_numbers,

        COALESCE(
            rt_info.total_seats,
            single_table.seats
        ) AS total_seats

     FROM reservations r

     INNER JOIN restaurant_tables single_table
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

     ) AS rt_info

        ON r.id =
           rt_info.reservation_id

     WHERE r.user_id = ?

     ORDER BY
        r.reservation_date DESC,
        COALESCE(
            r.start_time,
            r.reservation_time
        ) DESC"
);


if (!$stmt) {

    die(
        "Booking query error: " .
        $conn->error
    );
}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
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
        My Bookings - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/my-bookings.css"
    >


    <!-- =====================================================
         CURRENT PAGE ACTIVE COLOR
    ====================================================== -->

    <style>

        .nav-links a.active {
            color: #3a8f3a;
            font-weight: 700;
        }


        .booking-tables {
            color: #222222;
        }


        .booking-seats {
            color: #777777;
        }


        .booking-time-note {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #777777;
        }


        .booking-slot-note {
            margin-top: 15px;
            padding: 10px 12px;
            line-height: 1.5;
            font-size: 13px;
            color: #555555;
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
     PAGE HEADER
========================================================= -->

<section class="bookings-header">


    <p class="small-heading">
        DINE WITH US
    </p>


    <h1>
        My Bookings
    </h1>


    <p>
        View your reserved tables and dining details.
    </p>


</section>


<!-- =========================================================
     BOOKINGS
========================================================= -->

<main class="bookings-page">


<?php if (
    $result->num_rows > 0
): ?>


    <?php while (
        $booking =
        $result->fetch_assoc()
    ): ?>


        <?php

        /* ================= STATUS ================= */

        $status =
            strtolower(
                $booking["status"]
            );


        if (
            $status === "confirmed"
        ) {

            $display_status =
                "Confirmed";

        } elseif (
            $status === "pending"
        ) {

            $display_status =
                "Pending";

        } elseif (
            $status === "cancelled"
        ) {

            $display_status =
                "Cancelled";

        } elseif (
            $status === "completed"
        ) {

            $display_status =
                "Completed";

        } else {

            $display_status =
                ucfirst(
                    $status
                );
        }


        /* ================= TABLE DETAILS ================= */

        $table_numbers =
            $booking["table_numbers"]
            ?? "-";


        $total_seats =
            (int)
            (
                $booking["total_seats"]
                ?? 0
            );


        /* ================= START TIME ================= */

        $start_time =
            $booking["start_time"]
            ?? $booking["reservation_time"];


        if (
            empty($start_time)
        ) {

            $start_time =
                $booking["reservation_time"];
        }


        /* ================= END TIME ================= */

        $end_time =
            $booking["end_time"];


        /*
            For old bookings where end_time is empty,
            calculate estimated end time as +30 minutes.
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


        <!-- =================================================
             BOOKING CARD
        ================================================== -->

        <section class="booking-card">


            <!-- ================= TOP ================= -->

            <div class="booking-top">


                <div>


                    <h2 class="booking-tables">

                        <?php

                        echo htmlspecialchars(
                            $table_numbers
                        );

                        ?>

                    </h2>


                    <p class="booking-seats">

                        <?php

                        echo $total_seats;

                        ?>

                        Seats

                    </p>


                </div>


                <span class="booking-status">

                    <?php

                    echo htmlspecialchars(
                        $display_status
                    );

                    ?>

                </span>


            </div>


            <!-- ================= DETAILS ================= -->

            <div class="booking-details">


                <!-- ================= DATE ================= -->

                <div>


                    <span>
                        Date
                    </span>


                    <strong>

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


                </div>


                <!-- ================= START TIME ================= -->

                <div>


                    <span>
                        Start Time
                    </span>


                    <strong>

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


                </div>


                <!-- ================= END TIME ================= -->

                <div>


                    <span>
                        End Time
                    </span>


                    <strong>

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

                        <small class="booking-time-note">
                            Estimated slot
                        </small>

                    <?php endif; ?>


                </div>


                <!-- ================= GUESTS ================= -->

                <div>


                    <span>
                        Guests
                    </span>


                    <strong>

                        <?php

                        echo (int)
                            $booking["guests"];

                        ?>

                    </strong>


                </div>


            </div>


            


            <!-- =================================================
                 VIEW DETAILS
            ================================================== -->

            <a
                href="booking-details.php?booking_id=<?php echo (int) $booking['id']; ?>"
                class="view-details-btn"
            >
                View Details
            </a>


            <!-- =================================================
                 CANCEL BOOKING
            ================================================== -->

            <?php if (
                strtolower(
                    $booking["status"]
                ) === "confirmed"
            ): ?>


                <button
                    type="button"
                    class="cancel-booking-btn"
                    onclick="showBookingCancelConfirmation(<?php echo (int) $booking['id']; ?>)"
                >
                    Cancel Booking
                </button>


                <!-- =============================================
                     INLINE CONFIRMATION
                ============================================== -->

                <div
                    class="booking-cancel-confirm-box"
                    id="booking-cancel-confirm-<?php echo (int) $booking['id']; ?>"
                >


                    <p>
                        Are you sure you want to cancel this booking?
                    </p>


                    <div class="booking-cancel-actions">


                        <a
                            href="cancel-booking.php?booking_id=<?php echo (int) $booking['id']; ?>"
                            class="confirm-booking-cancel-btn"
                        >
                            Yes, Cancel Booking
                        </a>


                        <button
                            type="button"
                            class="keep-booking-btn"
                            onclick="hideBookingCancelConfirmation(<?php echo (int) $booking['id']; ?>)"
                        >
                            Keep Booking
                        </button>


                    </div>


                </div>


            <?php endif; ?>


        </section>


    <?php endwhile; ?>


<?php else: ?>


    <!-- =====================================================
         EMPTY
    ====================================================== -->

    <section class="empty-bookings">


        <h2>
            No Table Bookings Yet
        </h2>


        <p>
            Reserve a table and enjoy your dining experience at Spicy Zone.
        </p>


        <a
            href="booking.php"
            class="book-now-btn"
        >
            Book a Table
        </a>


    </section>


<?php endif; ?>


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


<script>

/* =========================================================
   SHOW BOOKING CANCEL
========================================================= */

function showBookingCancelConfirmation(
    bookingId
) {

    const box =
        document.getElementById(
            "booking-cancel-confirm-" +
            bookingId
        );


    if (box) {

        box.style.display =
            "block";
    }

}


/* =========================================================
   HIDE BOOKING CANCEL
========================================================= */

function hideBookingCancelConfirmation(
    bookingId
) {

    const box =
        document.getElementById(
            "booking-cancel-confirm-" +
            bookingId
        );


    if (box) {

        box.style.display =
            "none";
    }

}

</script>


</body>
</html>