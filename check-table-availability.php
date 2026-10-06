<?php

session_start();
require_once "db.php";

header("Content-Type: application/json");


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Please login first."
    ]);

    exit();
}


/* =========================================================
   GET DATE & START TIME
========================================================= */

$date =
    trim($_GET["date"] ?? "");

$time =
    trim($_GET["time"] ?? "");


if (
    $date === "" ||
    $time === ""
) {

    echo json_encode([
        "success" => false,
        "message" => "Date and time are required."
    ]);

    exit();
}


/* =========================================================
   CALCULATE ESTIMATED END TIME
   Default slot = 30 minutes

   NOTE:
   This is only for normal table availability checking.
   It is NOT a strict dining-time limit.

   OCCUPIED:
   An occupied table stays unavailable until admin
   changes the booking status to completed/cancelled.
========================================================= */

$start_timestamp =
    strtotime($time);


if (
    $start_timestamp === false
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid time."
    ]);

    exit();
}


$end_time =
    date(
        "H:i:s",
        $start_timestamp + (30 * 60)
    );


/* =========================================================
   CHECK TABLE AVAILABILITY

   NORMAL BOOKINGS:
   Existing Start < New End
   AND
   Existing End > New Start

   OCCUPIED BOOKINGS:
   Table remains unavailable regardless of the
   estimated 30-minute end time.

   Example:

   Confirmed:
   Existing 11:00 - 11:30
   New      11:30 - 12:00
   = Available

   Confirmed:
   Existing 11:00 - 11:30
   New      11:15 - 11:45
   = Booked

   Occupied:
   Customer is still using table
   = Booked until admin marks Completed
========================================================= */

$stmt =
    $conn->prepare(
        "SELECT
            rt.id,
            rt.table_number,
            rt.seats,

            CASE
                WHEN EXISTS (

                    SELECT 1

                    FROM reservations r

                    WHERE (
                        r.table_id = rt.id

                        OR EXISTS (

                            SELECT 1

                            FROM reservation_tables rtt

                            WHERE rtt.reservation_id = r.id

                            AND rtt.table_id = rt.id

                        )
                    )

                    AND r.reservation_date = ?

                    AND (

                        /* =====================================
                           OCCUPIED BOOKING

                           No time limit.
                           Remains unavailable until completed.
                        ====================================== */

                        r.status = 'occupied'

                        OR

                        /* =====================================
                           NORMAL PENDING / CONFIRMED BOOKING

                           Uses estimated 30-minute slot.
                        ====================================== */

                        (
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

                )

                THEN 1

                ELSE 0

            END AS is_booked

        FROM restaurant_tables rt

        ORDER BY
            rt.seats ASC,
            rt.id ASC"
    );


/* =========================================================
   PREPARE ERROR
========================================================= */

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare table query."
    ]);

    exit();
}


/* =========================================================
   BIND PARAMETERS

   date
   new end time
   new start time
========================================================= */

$stmt->bind_param(
    "sss",
    $date,
    $end_time,
    $time
);


/* =========================================================
   EXECUTE
========================================================= */

if (
    !$stmt->execute()
) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to check table availability."
    ]);

    $stmt->close();

    exit();
}


/* =========================================================
   GET RESULT
========================================================= */

$result =
    $stmt->get_result();


$tables = [];


while (
    $row =
    $result->fetch_assoc()
) {

    $tables[] = [

        "id" =>
            (int)
            $row["id"],

        "table_number" =>
            $row["table_number"],

        "seats" =>
            (int)
            $row["seats"],

        "is_booked" =>
            (int)
            $row["is_booked"]

    ];
}


/* =========================================================
   CLOSE
========================================================= */

$stmt->close();


/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([

    "success" => true,

    "tables" => $tables

]);

?>