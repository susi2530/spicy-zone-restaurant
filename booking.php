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


$message = "";
$message_type = "";


/* =========================================================
   GET FORM VALUES
========================================================= */

$selected_date =
    $_POST["reservation_date"] ?? "";


$selected_time =
    $_POST["reservation_time"] ?? "";


/* =========================================================
   RESERVATION DURATION
   Default booking duration = 30 minutes
========================================================= */

$start_timestamp =
    strtotime($selected_time);

$reservation_end_time =
    $start_timestamp !== false
        ? date(
            "H:i:s",
            $start_timestamp + (30 * 60)
        )
        : "";


$selected_guests =
    isset($_POST["guests"])
        ? (int) $_POST["guests"]
        : 0;


$selected_table_ids = [];

if (
    isset($_POST["table_ids"]) &&
    is_array($_POST["table_ids"])
) {

    foreach (
        $_POST["table_ids"] as $table_id
    ) {

        $table_id =
            (int) $table_id;


        if (
            $table_id > 0
        ) {

            $selected_table_ids[] =
                $table_id;
        }
    }
}


/* Remove duplicate table IDs */

$selected_table_ids =
    array_values(
        array_unique(
            $selected_table_ids
        )
    );


/* =========================================================
   BOOKING SUBMIT
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {


    /* =====================================================
       BASIC VALIDATION
    ====================================================== */

    if (
        $selected_date === "" ||
        $selected_time === "" ||
        $selected_guests <= 0 ||
        count($selected_table_ids) === 0
    ) {

        $message =
            "Please fill in all booking details and select at least one table.";

        $message_type =
            "error";

    } else {


        /* =================================================
           VALIDATE START TIME
        ================================================= */

        $start_timestamp =
            strtotime($selected_time);


        if (
            $start_timestamp === false
        ) {

            $message =
                "Invalid booking time.";

            $message_type =
                "error";

        } else {


            /* =================================================
               CALCULATE END TIME
               30 minutes after start time
            ================================================= */

            $reservation_end_time =
                date(
                    "H:i:s",
                    $start_timestamp + (30 * 60)
                );


            /* =================================================
               GET SELECTED TABLES
            ================================================= */

            $placeholders =
                implode(
                    ",",
                    array_fill(
                        0,
                        count($selected_table_ids),
                        "?"
                    )
                );


            $types =
                str_repeat(
                    "i",
                    count($selected_table_ids)
                );


            $table_stmt =
                $conn->prepare(
                    "SELECT
                        id,
                        table_number,
                        seats,
                        status
                     FROM restaurant_tables
                     WHERE id IN ($placeholders)
                     ORDER BY id ASC"
                );


            if (!$table_stmt) {

                $message =
                    "Unable to validate selected tables.";

                $message_type =
                    "error";

            } else {


                $table_stmt->bind_param(
                    $types,
                    ...$selected_table_ids
                );


                $table_stmt->execute();


                $table_result =
                    $table_stmt->get_result();


                $selected_tables = [];


                while (
                    $table =
                    $table_result->fetch_assoc()
                ) {

                    $selected_tables[] =
                        $table;
                }


                $table_stmt->close();


                /* =================================================
                   CHECK ALL TABLES EXIST
                ================================================= */

                if (
                    count($selected_tables) !==
                    count($selected_table_ids)
                ) {

                    $message =
                        "One or more selected tables are invalid.";

                    $message_type =
                        "error";

                } else {


                    /* =================================================
                       TOTAL SEAT CAPACITY
                    ================================================= */

                    $total_seats = 0;

                    $table_names = [];


                    $invalid_table =
                        false;


                    foreach (
                        $selected_tables as $table
                    ) {

                        $total_seats +=
                            (int)
                            $table["seats"];


                        $table_names[] =
                            $table["table_number"];


                        if (
                            strtolower(
                                $table["status"]
                            ) !== "available"
                        ) {

                            $invalid_table =
                                true;
                        }
                    }


                    /* =================================================
                       TABLE STATUS CHECK
                    ================================================= */

                    if (
                        $invalid_table
                    ) {

                        $message =
                            "One or more selected tables are currently unavailable.";

                        $message_type =
                            "error";


                    /* =================================================
                       TOTAL SEATS CHECK
                    ================================================= */

                    } elseif (
                        $total_seats <
                        $selected_guests
                    ) {

                        $message =
                            "The selected tables do not have enough total seats for the number of guests.";

                        $message_type =
                            "error";


                    } else {


                        /* =================================================
                           CHECK TABLE OVERLAPPING RESERVATIONS

                           NORMAL BOOKINGS:

                           Existing Start < New End
                           AND
                           Existing End > New Start

                           OCCUPIED BOOKINGS:

                           Occupied table remains unavailable
                           until admin changes status to
                           completed/cancelled.

                           Therefore occupied bookings DO NOT
                           use the 30-minute estimated end time.
                        ================================================= */

                        $already_booked =
                            false;


                        $availability_stmt =
                            $conn->prepare(
                                "SELECT
                                    r.id

                                 FROM reservations r

                                 WHERE r.reservation_date = ?

                                   AND (
                                       r.table_id = ?

                                       OR EXISTS (
                                           SELECT 1
                                           FROM reservation_tables rt
                                           WHERE rt.reservation_id = r.id
                                             AND rt.table_id = ?
                                       )
                                   )

                                   AND (
                                       
                                       /* =================================
                                          OCCUPIED BOOKING

                                          No time limit.
                                          Remains unavailable until
                                          admin marks it completed.
                                       ================================= */

                                       r.status = 'occupied'

                                       OR

                                       /* =================================
                                          NORMAL PENDING / CONFIRMED

                                          Uses 30-minute estimated slot.
                                       ================================= */

                                       (
                                           r.status IN (
                                               'pending',
                                               'confirmed'
                                           )

                                           AND

                                           COALESCE(
                                               r.start_time,
                                               r.reservation_time
                                           ) < ?

                                           AND

                                           COALESCE(
                                               r.end_time,
                                               ADDTIME(
                                                   r.reservation_time,
                                                   '00:30:00'
                                               )
                                           ) > ?
                                       )

                                   )

                                 LIMIT 1"
                            );


                        if (
                            !$availability_stmt
                        ) {

                            $message =
                                "Unable to check table availability.";

                            $message_type =
                                "error";

                        } else {


                            foreach (
                                $selected_table_ids as $table_id
                            ) {


                                $availability_stmt->bind_param(
                                    "siiss",
                                    $selected_date,
                                    $table_id,
                                    $table_id,
                                    $reservation_end_time,
                                    $selected_time
                                );


                                $availability_stmt->execute();


                                $availability_result =
                                    $availability_stmt->get_result();


                                if (
                                    $availability_result->num_rows > 0
                                ) {

                                    $already_booked =
                                        true;

                                    break;
                                }
                            }


                            $availability_stmt->close();


                            if (
                                $already_booked
                            ) {

                                $message =
                                    "One of the selected tables is already booked for the selected time. Please choose another table or time.";

                                $message_type =
                                    "error";

                            } else {


                                /* =================================================
                                   CREATE RESERVATION
                                ================================================== */

                                $booking_status =
                                    "confirmed";


                                $first_table_id =
                                    (int)
                                    $selected_table_ids[0];


                                /*
                                    Keep first table_id in reservations
                                    for compatibility with old pages.
                                */


                                $conn->begin_transaction();


                                try {


                                    $insert_stmt =
                                        $conn->prepare(
                                            "INSERT INTO reservations
                                            (
                                                user_id,
                                                table_id,
                                                reservation_date,
                                                reservation_time,
                                                start_time,
                                                end_time,
                                                guests,
                                                status
                                            )
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                                        );


                                    if (
                                        !$insert_stmt
                                    ) {

                                        throw new Exception(
                                            "Unable to create reservation."
                                        );
                                    }


                                    $insert_stmt->bind_param(
                                        "iissssis",
                                        $user_id,
                                        $first_table_id,
                                        $selected_date,
                                        $selected_time,
                                        $selected_time,
                                        $reservation_end_time,
                                        $selected_guests,
                                        $booking_status
                                    );


                                    if (
                                        !$insert_stmt->execute()
                                    ) {

                                        throw new Exception(
                                            "Unable to complete your booking."
                                        );
                                    }


                                    $reservation_id =
                                        $conn->insert_id;


                                    $insert_stmt->close();


                                    /* =================================================
                                       SAVE ALL SELECTED TABLES
                                    ================================================== */

                                    $reservation_table_stmt =
                                        $conn->prepare(
                                            "INSERT INTO reservation_tables
                                            (
                                                reservation_id,
                                                table_id
                                            )
                                            VALUES (?, ?)"
                                        );


                                    if (
                                        !$reservation_table_stmt
                                    ) {

                                        throw new Exception(
                                            "Unable to save selected tables."
                                        );
                                    }


                                    foreach (
                                        $selected_table_ids as $table_id
                                    ) {

                                        $reservation_table_stmt->bind_param(
                                            "ii",
                                            $reservation_id,
                                            $table_id
                                        );


                                        if (
                                            !$reservation_table_stmt->execute()
                                        ) {

                                            throw new Exception(
                                                "Unable to save one or more selected tables."
                                            );
                                        }
                                    }


                                    $reservation_table_stmt->close();


                                    $conn->commit();


                                    $message =
                                        implode(
                                            ", ",
                                            $table_names
                                        ) .
                                        " booked successfully!";


                                    $message_type =
                                        "success";


                                    $selected_table_ids =
                                        [];


                                } catch (
                                    Exception $e
                                ) {

                                    $conn->rollback();


                                    $message =
                                        $e->getMessage();

                                    $message_type =
                                        "error";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
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
        Book a Table - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/booking.css"
    >


    <style>

        .table-card.selected {
            border-color: #3a8f3a;

            box-shadow:
                0 0 0 2px rgba(
                    58,
                    143,
                    58,
                    0.08
                );

            background: #f4fbf4;
        }


        .table-card.disabled {
            opacity: 0.45;

            cursor: not-allowed;
        }


        .selected-table-note {
            margin-top: 12px;

            color: #777777;

            font-size: 11px;

            line-height: 1.5;
        }


        /* =================================================
           END TIME DISPLAY
        ================================================== */

        .end-time-display {
            margin-top: 8px;

            font-size: 13px;

            color: #666666;
        }


        .end-time-display strong {
            color: #333333;
        }

    </style>

</head>

<body>


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


        <a href="profile.php">
            Profile
        </a>


    </nav>


</header>


<section class="booking-header">


    <p class="small-heading">
        DINE WITH US
    </p>


    <h1>
        Reserve Your Table
    </h1>


    <p>
        Choose your date, time and table.
    </p>


</section>


<?php if (
    $message !== ""
): ?>


    <div
        class="booking-message
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


<main class="booking-page">


<form
    method="POST"
    action=""
    id="bookingForm"
>


    <section class="booking-card">


        <h2>
            Booking Details
        </h2>


        <div class="form-grid">


            <!-- ================= DATE ================= -->

            <div class="form-group">


                <label for="reservation_date">
                    Date
                </label>


                <input
                    type="date"
                    id="reservation_date"
                    name="reservation_date"
                    value="<?php
                    echo htmlspecialchars(
                        $selected_date
                    );
                    ?>"
                    min="<?php
                    echo date(
                        "Y-m-d"
                    );
                    ?>"
                    required
                >


            </div>


            <!-- ================= START TIME ================= -->

            <div class="form-group">


                <label for="reservation_time">
                    Start Time
                </label>


                <input
                    type="time"
                    id="reservation_time"
                    name="reservation_time"
                    value="<?php
                    echo htmlspecialchars(
                        $selected_time
                    );
                    ?>"
                    required
                >


                <!-- ================= END TIME ================= -->

                <div class="end-time-display">

                    End Time:
                    <strong id="reservationEndTimeText">
                        —
                    </strong>

                </div>


                <!-- Hidden field for JS display -->

                <input
                    type="hidden"
                    id="reservation_end_time"
                    name="reservation_end_time"
                    value="<?php
                    echo htmlspecialchars(
                        $reservation_end_time
                    );
                    ?>"
                >


            </div>


            <!-- ================= GUESTS ================= -->

            <div class="form-group">


                <label for="guests">
                    Number of Guests
                </label>


                <input
                    type="number"
                    id="guests"
                    name="guests"
                    min="1"
                    max="100"
                    value="<?php
                    echo $selected_guests > 0
                        ? $selected_guests
                        : "";
                    ?>"
                    placeholder="Enter guests"
                    required
                >


            </div>


        </div>


    </section>


    <section class="booking-card">


        <div class="table-heading">


            <h2>
                Choose Your Tables
            </h2>


            <p>
                Select the available tables you need.
            </p>


        </div>


        <div
            class="table-grid"
            id="bookingTables"
        >


            <p class="table-loading-message">

                Select date and time to see table availability.

            </p>


        </div>


        <p
            class="selected-table-note"
            id="selectedTableNote"
        >
            No tables selected.
        </p>


    </section>


    <section class="booking-confirm">


        <button
            type="submit"
            class="book-btn"
        >
            Confirm Table Booking
        </button>



        <a
            href="index.php"
            class="back-btn"
        >
            Back to Home
        </a>


        <p class="booking-note">
            💡 <strong>Note:</strong> The selected time is your booking start time.
            The displayed end time is only an estimated time slot for table availability
            and is not a strict dining time limit.
        </p>


    </section>


</form>


</main>


<footer>


    <p>
        © 2026 Spicy Zone Restaurant.
        All Rights Reserved.
    </p>


</footer>


<script>


/* =========================================================
   ELEMENTS
========================================================= */

const bookingDate =
    document.getElementById(
        "reservation_date"
    );


const bookingTime =
    document.getElementById(
        "reservation_time"
    );


const bookingEndTime =
    document.getElementById(
        "reservation_end_time"
    );


const bookingEndTimeText =
    document.getElementById(
        "reservationEndTimeText"
    );


const guestsInput =
    document.getElementById(
        "guests"
    );


const bookingTables =
    document.getElementById(
        "bookingTables"
    );


const selectedTableNote =
    document.getElementById(
        "selectedTableNote"
    );


/* =========================================================
   CALCULATE END TIME
   30 MINUTES AFTER START TIME
========================================================= */

function updateBookingEndTime() {


    if (
        !bookingTime.value
    ) {

        bookingEndTime.value =
            "";

        bookingEndTimeText.textContent =
            "—";

        return;

    }


    const parts =
        bookingTime.value.split(":");


    const date =
        new Date();


    date.setHours(
        parseInt(
            parts[0],
            10
        ),
        parseInt(
            parts[1],
            10
        ),
        0,
        0
    );


    date.setMinutes(
        date.getMinutes() + 30
    );


    const hours =
        String(
            date.getHours()
        ).padStart(
            2,
            "0"
        );


    const minutes =
        String(
            date.getMinutes()
        ).padStart(
            2,
            "0"
        );


    bookingEndTime.value =
        hours +
        ":" +
        minutes;


    let displayHours =
        date.getHours();


    const displayMinutes =
        String(
            date.getMinutes()
        ).padStart(
            2,
            "0"
        );


    const ampm =
        displayHours >= 12
            ? "PM"
            : "AM";


    displayHours =
        displayHours % 12;


    if (
        displayHours === 0
    ) {

        displayHours = 12;

    }


    bookingEndTimeText.textContent =
        displayHours +
        ":" +
        displayMinutes +
        " " +
        ampm;
}


/* =========================================================
   GET TABLE IMAGE
========================================================= */

function getTableImage(
    seats
) {

    switch (
        parseInt(
            seats
        )
    ) {

        case 2:

            return "table-2.png";


        case 4:

            return "table-4.png";


        case 7:

            return "table-7.png";


        case 10:

            return "table-10.png";


        default:

            return "";

    }

}


/* =========================================================
   UPDATE SELECTED TABLE SUMMARY
========================================================= */

function updateSelectedTables() {


    const selectedTables =
        document.querySelectorAll(
            'input[name="table_ids[]"]:checked'
        );


    let totalSeats =
        0;


    const tableNames = [];


    selectedTables.forEach(
        function(table) {


            totalSeats +=
                parseInt(
                    table.getAttribute(
                        "data-seats"
                    )
                );


            tableNames.push(
                table.getAttribute(
                    "data-table-name"
                )
            );

        }
    );


    if (
        selectedTables.length === 0
    ) {

        selectedTableNote.textContent =
            "No tables selected.";

        return;

    }


    selectedTableNote.textContent =
        selectedTables.length +
        " table(s) selected: " +
        tableNames.join(
            ", "
        ) +
        " • Total seats: " +
        totalSeats;

}


/* =========================================================
   LOAD TABLES
========================================================= */

function loadBookingTables() {


    const date =
        bookingDate.value;


    const time =
        bookingTime.value;


    if (
        date === "" ||
        time === ""
    ) {


        bookingTables.innerHTML = `

            <p class="table-loading-message">

                Select date and time to see table availability.

            </p>

        `;


        selectedTableNote.textContent =
            "No tables selected.";


        return;

    }


    bookingTables.innerHTML = `

        <p class="table-loading-message">

            Checking table availability...

        </p>

    `;


    fetch(
        "check-table-availability.php?date=" +
        encodeURIComponent(
            date
        ) +
        "&time=" +
        encodeURIComponent(
            time
        )
    )


    .then(
        function(response) {

            return response.json();

        }
    )


    .then(
        function(data) {


            if (
                !data.success
            ) {


                bookingTables.innerHTML = `

                    <div class="no-tables">

                        <h3>
                            Unable to Check Tables
                        </h3>

                        <p>
                            Please try again.
                        </p>

                    </div>

                `;


                return;

            }


            bookingTables.innerHTML =
                "";


            data.tables.forEach(
                function(table) {


                    const isBooked =
                        parseInt(
                            table.is_booked
                        ) === 1;


                    const image =
                        getTableImage(
                            table.seats
                        );


                    const card =
                        document.createElement(
                            "label"
                        );


                    card.className =
                        "table-card";


                    if (
                        isBooked
                    ) {

                        card.classList.add(
                            "booked"
                        );

                    }


                    card.innerHTML = `

                        <input
                            type="checkbox"
                            name="table_ids[]"
                            value="${table.id}"
                            data-seats="${table.seats}"
                            data-table-name="${table.table_number}"
                            ${
                                isBooked
                                ? "disabled"
                                : ""
                            }
                        >


                        <div class="table-content">


                            <div class="table-image">

                                ${
                                    image !== ""
                                    ? `
                                        <img
                                            src="images/${image}"
                                            alt="${table.table_number}"
                                        >
                                      `
                                    : ""
                                }

                            </div>


                            <div class="table-info">


                                <h3>
                                    ${table.table_number}
                                </h3>


                                <p>
                                    ${table.seats} Seats
                                </p>


                                <span
                                    class="${
                                        isBooked
                                        ? "table-unavailable"
                                        : "table-available"
                                    }"
                                >

                                    ${
                                        isBooked
                                        ? "Already Booked"
                                        : "Available"
                                    }

                                </span>


                            </div>


                        </div>

                    `;


                    const checkbox =
                        card.querySelector(
                            'input[name="table_ids[]"]'
                        );


                    if (
                        !isBooked
                    ) {


                        checkbox.addEventListener(
                            "change",
                            function() {


                                if (
                                    checkbox.checked
                                ) {

                                    card.classList.add(
                                        "selected"
                                    );

                                } else {

                                    card.classList.remove(
                                        "selected"
                                    );

                                }


                                updateSelectedTables();

                            }
                        );


                    }


                    bookingTables.appendChild(
                        card
                    );

                }
            );


            updateSelectedTables();

        }
    )


    .catch(
        function(error) {


            console.log(
                error
            );


            bookingTables.innerHTML = `

                <div class="no-tables">

                    <h3>
                        Unable to Check Tables
                    </h3>

                    <p>
                        Please try again.
                    </p>

                </div>

            `;

        }
    );

}


/* =========================================================
   DATE
========================================================= */

bookingDate.addEventListener(
    "change",
    function() {

        loadBookingTables();

    }
);


/* =========================================================
   TIME
========================================================= */

bookingTime.addEventListener(
    "change",
    function() {

        updateBookingEndTime();

        loadBookingTables();

    }
);


/* =========================================================
   GUESTS
========================================================= */

guestsInput.addEventListener(
    "input",
    function() {

        updateSelectedTables();

    }
);


/* =========================================================
   FORM VALIDATION
========================================================= */

document
    .getElementById(
        "bookingForm"
    )
    .addEventListener(
        "submit",
        function(event) {


            const selectedTables =
                document.querySelectorAll(
                    'input[name="table_ids[]"]:checked'
                );


            if (
                !bookingDate.value ||
                !bookingTime.value ||
                !guestsInput.value
            ) {

                event.preventDefault();


                alert(
                    "Please complete all booking details."
                );


                return;

            }


            if (
                selectedTables.length === 0
            ) {

                event.preventDefault();


                alert(
                    "Please select at least one available table."
                );


                return;

            }


            updateBookingEndTime();


            const guests =
                parseInt(
                    guestsInput.value
                ) || 0;


            let totalSeats =
                0;


            selectedTables.forEach(
                function(table) {


                    totalSeats +=
                        parseInt(
                            table.getAttribute(
                                "data-seats"
                            )
                        );

                }
            );


            if (
                totalSeats <
                guests
            ) {

                event.preventDefault();


                alert(
                    "The selected tables do not have enough total seats for " +
                    guests +
                    " guest(s). Please select another table."
                );


                return;

            }

        }
    );


/* =========================================================
   INITIAL LOAD
========================================================= */

updateBookingEndTime();


if (
    bookingDate.value !== "" &&
    bookingTime.value !== ""
) {

    loadBookingTables();

}

</script>


</body>
</html>