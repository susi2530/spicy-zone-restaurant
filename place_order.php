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


$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   POST CHECK
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: checkout.php");
    exit();
}


/* =========================================================
   GET FORM VALUES
========================================================= */

$order_type =
    $_POST["order_type"] ?? "delivery";


$payment_method =
    $_POST["payment_method"] ?? "online";


$payment_app =
    trim(
        $_POST["payment_app"] ?? "Google Pay"
    );


$reservation_date =
    trim(
        $_POST["reservation_date"] ?? ""
    );


$reservation_time =
    trim(
        $_POST["reservation_time"] ?? ""
    );


/* =========================================================
   AUTOMATIC END TIME
   Estimated slot = 30 minutes

   IMPORTANT:
   This is NOT a strict dining-time limit.
========================================================= */

$reservation_end_time = "";

if ($reservation_time !== "") {

    $start_timestamp =
        strtotime($reservation_time);

    if ($start_timestamp !== false) {

        $reservation_end_time =
            date(
                "H:i:s",
                $start_timestamp + (30 * 60)
            );
    }
}


$guests =
    isset($_POST["guests"])
        ? (int) $_POST["guests"]
        : 0;


/* =========================================================
   GET TABLE IDS
========================================================= */

$table_ids = [];


if (
    isset($_POST["table_ids"]) &&
    is_array($_POST["table_ids"])
) {

    foreach (
        $_POST["table_ids"] as $table_id
    ) {

        $table_id =
            (int) $table_id;


        if ($table_id > 0) {

            $table_ids[] =
                $table_id;
        }
    }

} elseif (
    isset($_POST["table_id"])
) {

    $old_table_id =
        (int) $_POST["table_id"];


    if ($old_table_id > 0) {

        $table_ids[] =
            $old_table_id;
    }
}


$table_ids =
    array_values(
        array_unique(
            $table_ids
        )
    );


/* =========================================================
   VALID PAYMENT APPS
========================================================= */

$allowed_payment_apps = [

    "Google Pay",
    "PhonePe",
    "Paytm"

];


if (
    $payment_method === "online" &&
    !in_array(
        $payment_app,
        $allowed_payment_apps,
        true
    )
) {

    $payment_app =
        "Google Pay";
}


/* =========================================================
   VALID ORDER TYPE
========================================================= */

$allowed_order_types = [

    "delivery" => "DELIVERY",

    "takeaway" => "TAKEAWAY",

    "dine_in" => "DINE_IN"

];


if (
    !isset(
        $allowed_order_types[$order_type]
    )
) {

    die(
        "Invalid order type."
    );
}


$db_order_type =
    $allowed_order_types[$order_type];


/* =========================================================
   VALID PAYMENT METHOD
========================================================= */

if (
    $payment_method === "online"
) {

    $db_payment_method =
        "UPI";

} elseif (
    $payment_method === "cod"
) {

    $db_payment_method =
        "PAY_AT_RESTAURANT";

} else {

    die(
        "Invalid payment method."
    );
}


/* =========================================================
   GET USER
========================================================= */

$user_stmt =
    $conn->prepare(
        "SELECT
            name,
            email,
            mobile,
            address
         FROM users
         WHERE id = ?
         LIMIT 1"
    );


if (!$user_stmt) {

    die(
        "Unable to load user details."
    );
}


$user_stmt->bind_param(
    "i",
    $user_id
);


$user_stmt->execute();


$user_result =
    $user_stmt->get_result();


$user =
    $user_result->fetch_assoc();


$user_stmt->close();


if (!$user) {

    die(
        "User account not found."
    );
}


/* =========================================================
   GET CART
========================================================= */

$cart_stmt =
    $conn->prepare(
        "SELECT
            cart.food_id,
            cart.quantity,
            foods.name,
            foods.price
         FROM cart

         INNER JOIN foods
            ON cart.food_id = foods.id

         WHERE cart.user_id = ?
           AND foods.is_available = 1"
    );


if (!$cart_stmt) {

    die(
        "Unable to load cart."
    );
}


$cart_stmt->bind_param(
    "i",
    $user_id
);


$cart_stmt->execute();


$cart_result =
    $cart_stmt->get_result();


$cart_items = [];

$subtotal = 0;


while (
    $item =
    $cart_result->fetch_assoc()
) {

    $item_total =
        (float)
        $item["price"] *
        (int)
        $item["quantity"];


    $item["item_total"] =
        $item_total;


    $item["food_id"] =
        (int)
        $item["food_id"];


    $item["quantity"] =
        (int)
        $item["quantity"];


    $item["price"] =
        (float)
        $item["price"];


    $subtotal +=
        $item_total;


    $cart_items[] =
        $item;
}


$cart_stmt->close();


/* =========================================================
   EMPTY CART
========================================================= */

if (
    count($cart_items) === 0
) {

    header(
        "Location: cart.php"
    );

    exit();
}


/* =========================================================
   FIRST ORDER CHECK

   IMPORTANT:
   Pending UPI checkout is NOT an order yet.
========================================================= */

$order_count_stmt =
    $conn->prepare(
        "SELECT
            COUNT(*) AS total_orders
         FROM orders
         WHERE user_id = ?"
    );


$order_count_stmt->bind_param(
    "i",
    $user_id
);


$order_count_stmt->execute();


$order_count_result =
    $order_count_stmt->get_result();


$order_count_row =
    $order_count_result->fetch_assoc();


$order_count =
    (int)
    $order_count_row["total_orders"];


$order_count_stmt->close();


/* =========================================================
   AUTOMATIC DISCOUNT
========================================================= */

$discount = 0;


if (
    $subtotal >= 499
) {

    $discount =
        $subtotal * 0.20;

} elseif (
    $order_count === 0
) {

    $discount =
        $subtotal * 0.10;
}


$total =
    $subtotal - $discount;


/* =========================================================
   DINE-IN TABLE VALIDATION
========================================================= */

$selected_tables = [];

$total_table_seats = 0;

$first_table_id = 0;


if (
    $db_order_type === "DINE_IN"
) {


    if (
        $reservation_date === "" ||
        $reservation_time === "" ||
        $guests <= 0 ||
        count($table_ids) === 0
    ) {

        die(
            "Please complete the dine-in details and select at least one table."
        );
    }


    /* =====================================================
       GET SELECTED TABLES
    ====================================================== */

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

        die(
            "Unable to validate selected tables."
        );
    }


    $table_stmt->bind_param(
        $types,
        ...$table_ids
    );


    $table_stmt->execute();


    $table_result =
        $table_stmt->get_result();


    while (
        $table =
        $table_result->fetch_assoc()
    ) {

        $selected_tables[] =
            $table;


        $total_table_seats +=
            (int)
            $table["seats"];


        if (
            $first_table_id === 0
        ) {

            $first_table_id =
                (int)
                $table["id"];
        }
    }


    $table_stmt->close();


    if (
        count($selected_tables) !==
        count($table_ids)
    ) {

        die(
            "One or more selected tables are invalid."
        );
    }


    /* =====================================================
       CHECK TABLE STATUS
    ====================================================== */

    foreach (
        $selected_tables as $table
    ) {

        if (
            strtolower(
                $table["status"]
            ) !== "available"
        ) {

            die(
                "One or more selected tables are currently unavailable."
            );
        }
    }


    /* =====================================================
       CHECK SEATS
    ====================================================== */

    if (
        $total_table_seats <
        $guests
    ) {

        die(
            "The selected tables do not have enough total seats for the number of guests."
        );
    }


    /* =====================================================
       CHECK EXISTING RESERVATION

       30 minutes is only an estimated
       availability slot.

       OCCUPIED:
       An occupied reservation blocks the table
       regardless of the selected time until
       admin changes the reservation to
       completed or cancelled.
    ====================================================== */

    $availability_stmt =
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

             LIMIT 1"
        );


    if (!$availability_stmt) {

        die(
            "Unable to check table availability."
        );
    }


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


    $availability_stmt->bind_param(
        $bind_types,
        ...$bind_values
    );


    $availability_stmt->execute();


    $availability_result =
        $availability_stmt->get_result();


    if (
        $availability_result->num_rows > 0
    ) {

        $availability_stmt->close();


        die(
            "One or more selected tables are already booked for the selected date and time. Please choose another table or time."
        );
    }


    $availability_stmt->close();
}


/* =========================================================
   UPI PAYMENT FLOW

   VERY IMPORTANT:

   DO NOT INSERT INTO:
   - orders
   - order_items
   - payments
   - reservations

   until payment succeeds.
========================================================= */

if (
    $db_payment_method === "UPI"
) {


    unset(
        $_SESSION["pending_upi_checkout"]
    );


    /* =====================================================
       SAVE COMPLETE CHECKOUT DATA IN SESSION
    ====================================================== */

    $_SESSION["pending_upi_checkout"] = [

        "user_id" =>
            $user_id,

        "order_type" =>
            $db_order_type,

        "payment_method" =>
            "UPI",

        "payment_app" =>
            $payment_app,

        "reservation_date" =>
            $reservation_date,

        "reservation_time" =>
            $reservation_time,

        "reservation_end_time" =>
            $reservation_end_time,

        "guests" =>
            $guests,

        "table_ids" =>
            $table_ids,

        "first_table_id" =>
            $first_table_id,

        "cart_items" =>
            $cart_items,

        "subtotal" =>
            $subtotal,

        "discount" =>
            $discount,

        "total" =>
            $total,

        "created_at" =>
            time()
    ];


    /* =====================================================
       DO NOT CLEAR CART
    ====================================================== */


    /* =====================================================
       OPEN PAYMENT APP
    ====================================================== */

    header(
        "Location: payment-app.php?app=" .
        urlencode(
            $payment_app
        )
    );

    exit();
}


/* =========================================================
   NON-UPI ORDER
========================================================= */

$order_number =
    "SZ" .
    date("YmdHis") .
    rand(
        100,
        999
    );


$conn->begin_transaction();


try {


    $payment_status =
        "pending";


    $order_status =
        "confirmed";


    /* =====================================================
       FINAL DINE-IN RECHECK

       IMPORTANT:
       OCCUPIED reservations are also blocked here.
    ====================================================== */

    if (
        $db_order_type === "DINE_IN"
    ) {

        $placeholders_final =
            implode(
                ",",
                array_fill(
                    0,
                    count($table_ids),
                    "?"
                )
            );


        $types_final =
            str_repeat(
                "i",
                count($table_ids)
            );


        $final_check_stmt =
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
                           $placeholders_final
                       )

                       OR EXISTS (

                           SELECT 1

                           FROM reservation_tables rt

                           WHERE rt.reservation_id =
                                 r.id

                             AND rt.table_id IN (
                                 $placeholders_final
                             )
                       )
                   )

                 LIMIT 1
                 FOR UPDATE"
            );


        if (!$final_check_stmt) {

            throw new Exception(
                "Unable to perform final table availability check."
            );
        }


        $final_bind_types =
            "sss" .
            $types_final .
            $types_final;


        $final_bind_values =
            array_merge(
                [
                    $reservation_date,
                    $reservation_end_time,
                    $reservation_time
                ],
                $table_ids,
                $table_ids
            );


        $final_check_stmt->bind_param(
            $final_bind_types,
            ...$final_bind_values
        );


        $final_check_stmt->execute();


        $final_check_result =
            $final_check_stmt->get_result();


        if (
            $final_check_result->num_rows > 0
        ) {

            $final_check_stmt->close();


            throw new Exception(
                "One or more selected tables were booked by another customer just now. Please choose another table or time."
            );
        }


        $final_check_stmt->close();
    }


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


    $order_stmt->bind_param(
        "isdddssss",
        $user_id,
        $order_number,
        $subtotal,
        $discount,
        $total,
        $db_payment_method,
        $db_order_type,
        $payment_status,
        $order_status
    );


    $order_stmt->execute();


    $order_id =
        $conn->insert_id;


    $order_stmt->close();


    /* =====================================================
       INSERT ORDER ITEMS
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


    foreach (
        $cart_items as $item
    ) {

        $item_stmt->bind_param(
            "iisdi",
            $order_id,
            $item["food_id"],
            $item["name"],
            $item["price"],
            $item["quantity"]
        );


        $item_stmt->execute();
    }


    $item_stmt->close();


    /* =====================================================
       INSERT PAYMENT
    ====================================================== */

    $transaction_id =
        null;


    $payment_status_db =
        "pending";


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


    $payment_stmt->bind_param(
        "issds",
        $order_id,
        $db_payment_method,
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
        $db_order_type === "DINE_IN"
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
            $conn->insert_id;


        $reservation_stmt->close();


        /* =================================================
           INSERT RESERVATION TABLES
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


        foreach (
            $table_ids as $selected_table_id
        ) {

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


    $clear_cart->bind_param(
        "i",
        $user_id
    );


    $clear_cart->execute();


    $clear_cart->close();


    $conn->commit();


    header(
        "Location: success.php?order_id=" .
        $order_id .
        "&type=cod"
    );

    exit();


} catch (
    Exception $e
) {

    $conn->rollback();


    die(
        htmlspecialchars(
            $e->getMessage()
        )
    );
}

?>