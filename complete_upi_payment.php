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
   POST CHECK
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    header(
        "Location: checkout.php"
    );

    exit();

}


/* =========================================================
   PENDING CHECKOUT
========================================================= */

if (
    !isset(
        $_SESSION["pending_upi_checkout"]
    )
) {

    header(
        "Location: checkout.php"
    );

    exit();

}


$checkout =
    $_SESSION["pending_upi_checkout"];


/* =========================================================
   USER CHECK
========================================================= */

if (
    (int)
    ($checkout["user_id"] ?? 0)
    !==
    $user_id
) {

    unset(
        $_SESSION["pending_upi_checkout"]
    );

    header(
        "Location: checkout.php"
    );

    exit();

}


/* =========================================================
   UPI ID
========================================================= */

$upi_id =
    trim(
        $_POST["upi_id"] ?? ""
    );


if (
    $upi_id === ""
) {

    header(
        "Location: payment-app.php?app=" .
        urlencode(
            $checkout["payment_app"] ?? "Google Pay"
        ) .
        "&error=" .
        urlencode(
            "Please enter your UPI ID."
        )
    );

    exit();

}


if (
    !preg_match(
        '/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/',
        $upi_id
    )
) {

    header(
        "Location: payment-app.php?app=" .
        urlencode(
            $checkout["payment_app"] ?? "Google Pay"
        ) .
        "&error=" .
        urlencode(
            "Please enter a valid UPI ID."
        )
    );

    exit();

}


/* =========================================================
   GET CHECKOUT DATA
========================================================= */

$order_type =
    strtoupper(
        trim(
            (string)
            (
                $checkout["order_type"]
                ??
                ""
            )
        )
    );


$payment_method =
    "UPI";


$payment_app =
    trim(
        $checkout["payment_app"]
        ??
        "Google Pay"
    );


$reservation_date =
    $checkout["reservation_date"]
    ??
    "";


$reservation_time =
    $checkout["reservation_time"]
    ??
    "";


/* =========================================================
   AUTOMATIC END TIME
   ESTIMATED SLOT = 30 MINUTES
========================================================= */

$reservation_end_time =
    $checkout["reservation_end_time"]
    ??
    "";


/*
 * Backward compatibility:
 * If older checkout session does not contain
 * reservation_end_time, calculate it here.
 */

if (
    $reservation_end_time === ""
    &&
    $reservation_time !== ""
) {

    $start_timestamp =
        strtotime(
            $reservation_time
        );

    if (
        $start_timestamp !== false
    ) {

        $reservation_end_time =
            date(
                "H:i:s",
                $start_timestamp + (30 * 60)
            );

    }

}


$guests =
    (int)
    (
        $checkout["guests"]
        ??
        0
    );


$table_ids =
    isset(
        $checkout["table_ids"]
    )
    &&
    is_array(
        $checkout["table_ids"]
    )
    ?
    $checkout["table_ids"]
    :
    [];


$first_table_id =
    (int)
    (
        $checkout["first_table_id"]
        ??
        0
    );


$cart_items =
    isset(
        $checkout["cart_items"]
    )
    &&
    is_array(
        $checkout["cart_items"]
    )
    ?
    $checkout["cart_items"]
    :
    [];


$subtotal =
    (float)
    (
        $checkout["subtotal"]
        ??
        0
    );


$discount =
    (float)
    (
        $checkout["discount"]
        ??
        0
    );


$total =
    (float)
    (
        $checkout["total"]
        ??
        0
    );


/* =========================================================
   BASIC CHECK
========================================================= */

if (
    count($cart_items) === 0
    ||
    $total <= 0
) {

    header(
        "Location: checkout.php"
    );

    exit();

}


/* =========================================================
   TRANSACTION
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       FINAL DINE-IN CHECK
    ====================================================== */

    if (
        $order_type === "DINE_IN"
    ) {


        if (
            $reservation_date === ""
            ||
            $reservation_time === ""
            ||
            $reservation_end_time === ""
            ||
            $guests <= 0
            ||
            count($table_ids) === 0
        ) {

            throw new Exception(
                "Please complete the dine-in details."
            );

        }


        $table_ids =
            array_values(
                array_unique(
                    array_map(
                        "intval",
                        $table_ids
                    )
                )
            );


        $placeholders =
            implode(
                ",",
                array_fill(
                    0,
                    count($table_ids),
                    "?"
                )
            );


        $types =
            str_repeat(
                "i",
                count($table_ids)
            );


        /*
         * FINAL TABLE AVAILABILITY CHECK
         *
         * pending / confirmed:
         * Uses estimated 30-minute slot.
         *
         * occupied:
         * Table is unavailable regardless of
         * selected time until admin changes
         * reservation to completed/cancelled.
         *
         * Example:
         *
         * Existing confirmed:
         * 11:00 - 11:30
         *
         * New:
         * 11:30 - 12:00
         * Allowed.
         *
         * Existing:
         * 11:00 - 11:30
         *
         * New:
         * 11:15 - 11:45
         * Not allowed.
         */

        $check_stmt =
            $conn->prepare(
                "SELECT
                    r.id

                 FROM reservations r

                 WHERE r.reservation_date = ?

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

                   AND (

                       r.table_id IN (
                           $placeholders
                       )

                       OR EXISTS (

                           SELECT 1

                           FROM reservation_tables rt

                           WHERE rt.reservation_id =
                                 r.id

                             AND rt.table_id IN (
                                 $placeholders
                             )

                       )

                   )

                 LIMIT 1

                 FOR UPDATE"
            );


        if (
            !$check_stmt
        ) {

            throw new Exception(
                "Unable to check table availability."
            );

        }


        /*
         * Date
         * End time
         * Start time
         * Table IDs
         * Table IDs again
         */

        $bind_types =
            "sss" .
            $types .
            $types;


        $bind_values =
            array_merge(
                [
                    $reservation_date,
                    $reservation_end_time,
                    $reservation_time
                ],
                $table_ids,
                $table_ids
            );


        $check_stmt->bind_param(
            $bind_types,
            ...$bind_values
        );


        $check_stmt->execute();


        $check_result =
            $check_stmt->get_result();


        if (
            $check_result->num_rows > 0
        ) {

            $check_stmt->close();

            throw new Exception(
                "Selected table is no longer available. Please choose another table or time."
            );

        }


        $check_stmt->close();


        /*
         * Make sure the first selected table
         * belongs to the selected table list.
         */

        if (
            !in_array(
                $first_table_id,
                $table_ids,
                true
            )
        ) {

            $first_table_id =
                $table_ids[0];

        }

    }


    /* =====================================================
       ORDER NUMBER
    ====================================================== */

    $order_number =
        "SZ" .
        date("YmdHis") .
        rand(
            100,
            999
        );


    /* =====================================================
       ORDER STATUS
    ====================================================== */

    $payment_status =
        "paid";


    $order_status =
        "confirmed";


    /* =====================================================
       INSERT ORDER
    ====================================================== */

    $order_stmt =
        $conn->prepare(
            "INSERT INTO orders
            (
                user_id,
                order_number,
                subtotal,
                discount,
                total,
                payment_method,
                order_type,
                payment_status,
                order_status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )"
        );


    if (
        !$order_stmt
    ) {

        throw new Exception(
            "Unable to create order."
        );

    }


    $order_stmt->bind_param(
        "isdddssss",
        $user_id,
        $order_number,
        $subtotal,
        $discount,
        $total,
        $payment_method,
        $order_type,
        $payment_status,
        $order_status
    );


    $order_stmt->execute();


    $order_id =
        (int)
        $conn->insert_id;


    $order_stmt->close();


    /* =====================================================
       ORDER ITEMS
    ====================================================== */

    $item_stmt =
        $conn->prepare(
            "INSERT INTO order_items
            (
                order_id,
                food_id,
                food_name,
                price,
                quantity
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )"
        );


    if (
        !$item_stmt
    ) {

        throw new Exception(
            "Unable to save order items."
        );

    }


    foreach (
        $cart_items as $item
    ) {

        $food_id =
            (int)
            (
                $item["food_id"]
                ??
                0
            );


        $food_name =
            (string)
            (
                $item["name"]
                ??
                ""
            );


        $price =
            (float)
            (
                $item["price"]
                ??
                0
            );


        $quantity =
            (int)
            (
                $item["quantity"]
                ??
                0
            );


        if (
            $food_id <= 0
            ||
            $quantity <= 0
        ) {

            throw new Exception(
                "Invalid order item."
            );

        }


        $item_stmt->bind_param(
            "iisdi",
            $order_id,
            $food_id,
            $food_name,
            $price,
            $quantity
        );


        $item_stmt->execute();

    }


    $item_stmt->close();


    /* =====================================================
       PAYMENT
    ====================================================== */

    $transaction_id =
        "UPI" .
        date("YmdHis") .
        rand(
            1000,
            9999
        );


    $payment_status_db =
        "success";


    $payment_stmt =
        $conn->prepare(
            "INSERT INTO payments
            (
                order_id,
                payment_method,
                transaction_id,
                amount,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )"
        );


    if (
        !$payment_stmt
    ) {

        throw new Exception(
            "Unable to save payment."
        );

    }


    $payment_stmt->bind_param(
        "issds",
        $order_id,
        $payment_method,
        $transaction_id,
        $total,
        $payment_status_db
    );


    $payment_stmt->execute();


    $payment_stmt->close();


    /* =====================================================
       DINE-IN RESERVATION
    ====================================================== */

    if (
        $order_type === "DINE_IN"
    ) {


        $reservation_status =
            "confirmed";


        $reservation_stmt =
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
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );


        if (
            !$reservation_stmt
        ) {

            throw new Exception(
                "Unable to create reservation."
            );

        }


        $reservation_stmt->bind_param(
            "iissssis",
            $user_id,
            $first_table_id,
            $reservation_date,
            $reservation_time,
            $reservation_time,
            $reservation_end_time,
            $guests,
            $reservation_status
        );


        $reservation_stmt->execute();


        $reservation_id =
            (int)
            $conn->insert_id;


        $reservation_stmt->close();


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
                VALUES
                (
                    ?,
                    ?
                )"
            );


        if (
            !$reservation_table_stmt
        ) {

            throw new Exception(
                "Unable to save reservation tables."
            );

        }


        foreach (
            $table_ids as $selected_table_id
        ) {

            $selected_table_id =
                (int)
                $selected_table_id;


            $reservation_table_stmt->bind_param(
                "ii",
                $reservation_id,
                $selected_table_id
            );


            $reservation_table_stmt->execute();

        }


        $reservation_table_stmt->close();

    }


    /* =====================================================
       CLEAR CART
    ====================================================== */

    $clear_cart =
        $conn->prepare(
            "DELETE FROM cart
             WHERE user_id = ?"
        );


    if (
        !$clear_cart
    ) {

        throw new Exception(
            "Unable to clear cart."
        );

    }


    $clear_cart->bind_param(
        "i",
        $user_id
    );


    $clear_cart->execute();


    $clear_cart->close();


    /* =====================================================
       COMMIT
    ====================================================== */

    $conn->commit();


    /* =====================================================
       SAVE PAYMENT SUCCESS DATA
    ====================================================== */

    $_SESSION["upi_payment_app"] =
        $payment_app;


    $_SESSION["upi_id"] =
        $upi_id;


    $_SESSION["upi_payment_amount"] =
        $total;


    $_SESSION["upi_order_number"] =
        $order_number;


    $_SESSION["upi_transaction_id"] =
        $transaction_id;


    $_SESSION["upi_order_id"] =
        $order_id;


    $_SESSION["upi_payment_status"] =
        "paid";


    /* =====================================================
       CLEAR TEMPORARY CHECKOUT DATA
    ====================================================== */

    unset(
        $_SESSION["pending_upi_checkout"]
    );


    /* =====================================================
       PAYMENT SUCCESS PAGE
    ====================================================== */

    header(
        "Location: payment-success.php?order_id=" .
        $order_id
    );

    exit();


} catch (
    Exception $e
) {

    $conn->rollback();


    header(
        "Location: payment-app.php?app=" .
        urlencode(
            $payment_app
        ) .
        "&error=" .
        urlencode(
            $e->getMessage()
        )
    );

    exit();

}

?>