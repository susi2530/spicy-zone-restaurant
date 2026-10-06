<?php

session_start();

require_once "../db.php";


/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION["admin_id"])) {

    header(
        "Location: admin-login.php"
    );

    exit();
}


/* =========================================================
   STATUS UPDATE MESSAGE
========================================================= */

$message = "";

$message_type = "";


/* =========================================================
   STATUS UPDATE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset(
        $_POST["update_status"]
    )
) {


    $order_id =
        isset(
            $_POST["order_id"]
        )
        ? (int)
            $_POST["order_id"]
        : 0;


    $new_status =
        strtoupper(
            trim(
                $_POST["new_status"] ?? ""
            )
        );


    /* =====================================================
       GET ORDER TYPE
    ====================================================== */

    $type_stmt =
        $conn->prepare(
            "SELECT
                order_type
             FROM orders
             WHERE id = ?
             LIMIT 1"
        );


    if (
        $type_stmt
    ) {

        $type_stmt->bind_param(
            "i",
            $order_id
        );


        $type_stmt->execute();


        $type_result =
            $type_stmt->get_result();


        $type_row =
            $type_result->fetch_assoc();


        $type_stmt->close();

    } else {

        $type_row =
            null;
    }


    $order_type_upper =
        strtoupper(
            (string)
            (
                $type_row["order_type"]
                ??
                ""
            )
        );


    /* =====================================================
       ALLOWED STATUS
    ====================================================== */

    $allowed_statuses = [

        "CONFIRMED",
        "PREPARING",
        "READY",
        "COMPLETED",
        "CANCELLED"

    ];


    if (
        $order_id <= 0
        ||
        !in_array(
            $new_status,
            $allowed_statuses,
            true
        )
    ) {

        $message =
            "Invalid order status.";

        $message_type =
            "error";


    } elseif (
        !$type_row
    ) {

        $message =
            "Order not found.";

        $message_type =
            "error";


    } else {


        /* =================================================
           UPDATE ORDER STATUS
        ================================================== */

        $update_stmt =
            $conn->prepare(
                "UPDATE orders
                 SET order_status = ?
                 WHERE id = ?"
            );


        if (
            !$update_stmt
        ) {

            $message =
                "Unable to prepare status update.";

            $message_type =
                "error";


        } else {


            $update_stmt->bind_param(
                "si",
                $new_status,
                $order_id
            );


            if (
                $update_stmt->execute()
            ) {

                $message =
                    "Order status updated successfully.";

                $message_type =
                    "success";


            } else {

                $message =
                    "Unable to update order status.";

                $message_type =
                    "error";
            }


            $update_stmt->close();
        }
    }
}


/* =========================================================
   GET ORDERS
========================================================= */

$order_stmt =
    $conn->prepare(
        "SELECT
            o.id,
            o.order_number,
            o.subtotal,
            o.discount,
            o.total,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.order_type,
            o.created_at,
            u.name AS user_name,
            u.email AS user_email
         FROM orders o
         LEFT JOIN users u
            ON o.user_id = u.id
         ORDER BY o.created_at DESC"
    );


if (
    !$order_stmt
) {

    die(
        "Unable to load orders."
    );
}


$order_stmt->execute();


$order_result =
    $order_stmt->get_result();

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
        Manage Orders - Spicy Zone Admin
    </title>


    <link
        rel="stylesheet"
        href="css/orders.css"
    >

</head>


<body>


<!-- =========================================================
     ADMIN NAVBAR
========================================================= -->

<header class="admin-navbar">


    <div class="admin-brand">


        <a
            href="dashboard.php"
            class="admin-brand-link"
        >

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


        <a
            href="dashboard.php"
        >
            Dashboard
        </a>


        <a
            href="orders.php"
            class="active"
        >
            Orders
        </a>


        <a
            href="bookings.php"
        >
            Bookings
        </a>


        <a
            href="foods.php"
        >
            Foods
        </a>


        <a
            href="categories.php"
        >
            Categories
        </a>


        <a
            href="users.php"
        >
            Users
        </a>


        <a
            href="reviews.php"
        >
            Reviews
        </a>


        <a
            href="coupons.php"
        >
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
     MAIN
========================================================= -->

<main class="orders-page">


    <!-- =====================================================
         PAGE HEADING
    ====================================================== -->

    <section class="page-heading">


        <div>

            <p class="eyebrow">
                ADMIN PANEL
            </p>


            <h1>
                Manage Orders
            </h1>


            <p>
                View orders and update their current status.
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
            class="
                admin-message
                <?php
                echo htmlspecialchars(
                    $message_type
                );
                ?>
            "
        >

            <?php

            echo htmlspecialchars(
                $message
            );

            ?>

        </div>


    <?php endif; ?>


    <!-- =====================================================
         ORDER LIST
    ====================================================== -->

    <section class="orders-panel">


        <?php if (
            $order_result->num_rows > 0
        ): ?>


            <div class="orders-table-wrap">


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
                                Payment
                            </th>

                            <th>
                                Total
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


                    <?php while (
                        $order =
                        $order_result->fetch_assoc()
                    ): ?>


                        <?php

                        /* =====================================
                           RAW ORDER STATUS
                        ====================================== */

                        $raw_status =
                            strtoupper(
                                trim(
                                    (string)
                                    $order[
                                        "order_status"
                                    ]
                                )
                            );


                        /* =====================================
                           RAW ORDER TYPE
                        ====================================== */

                        $order_type_upper =
                            strtoupper(
                                trim(
                                    (string)
                                    $order[
                                        "order_type"
                                    ]
                                )
                            );


                        /* =====================================
                           DISPLAY ORDER TYPE
                        ====================================== */

                        switch (
                            $order_type_upper
                        ) {

                            case "DINE_IN":

                                $display_order_type =
                                    "Dine-in";

                                break;


                            case "TAKEAWAY":

                                $display_order_type =
                                    "Takeaway";

                                break;


                            case "DELIVERY":

                                $display_order_type =
                                    "Delivery";

                                break;


                            default:

                                $display_order_type =
                                    ucfirst(
                                        strtolower(
                                            str_replace(
                                                "_",
                                                " ",
                                                $order_type_upper
                                            )
                                        )
                                    );

                                break;
                        }


                        /* =====================================
                           DISPLAY ORDER STATUS
                        ====================================== */

                        if (
                            $raw_status ===
                            "CONFIRMED"
                        ) {

                            $display_status =
                                "Confirmed";


                        } elseif (
                            $raw_status ===
                            "PREPARING"
                        ) {

                            $display_status =
                                "Preparing";


                        } elseif (
                            $raw_status ===
                            "READY"
                        ) {


                            if (
                                $order_type_upper ===
                                "TAKEAWAY"
                            ) {

                                $display_status =
                                    "Ready for Pickup";

                            } else {

                                $display_status =
                                    "Ready";
                            }


                        } elseif (
                            $raw_status ===
                            "COMPLETED"
                        ) {


                            if (
                                $order_type_upper ===
                                "DELIVERY"
                            ) {

                                $display_status =
                                    "Delivered";


                            } elseif (
                                $order_type_upper ===
                                "TAKEAWAY"
                            ) {

                                $display_status =
                                    "Picked Up";


                            } elseif (
                                $order_type_upper ===
                                "DINE_IN"
                            ) {

                                $display_status =
                                    "Served";


                            } else {

                                $display_status =
                                    "Completed";
                            }


                        } elseif (
                            $raw_status ===
                            "CANCELLED"
                        ) {

                            $display_status =
                                "Cancelled";


                        } else {

                            $display_status =
                                ucfirst(
                                    strtolower(
                                        str_replace(
                                            "_",
                                            " ",
                                            $raw_status
                                        )
                                    )
                                );
                        }


                        /* =====================================
                           PAYMENT METHOD
                        ====================================== */

                        $payment_method_upper =
                            strtoupper(
                                trim(
                                    (string)
                                    $order[
                                        "payment_method"
                                    ]
                                )
                            );


                        if (
                            $payment_method_upper ===
                            "UPI"
                        ) {

                            $payment_method =
                                "UPI";


                        } else {

                            $payment_method =
                                "Cash on Delivery";
                        }


                        /* =====================================
                           RAW PAYMENT STATUS
                        ====================================== */

                        $raw_payment_status =
                            strtolower(
                                trim(
                                    (string)
                                    $order[
                                        "payment_status"
                                    ]
                                )
                            );


                        /* =====================================
                           DISPLAY PAYMENT STATUS
                        ====================================== */

                        /*
                         * UPI payment:
                         * Once payment is completed,
                         * display Paid.
                         *
                         * COD:
                         * Completed means the order has
                         * been delivered / picked up / served,
                         * so display Paid.
                         *
                         * Other COD states keep their
                         * database payment status.
                         */

                        if (
                            $payment_method_upper ===
                            "UPI"
                        ) {

                            $payment_status =
                                "Paid";


                        } elseif (
                            $payment_method_upper !==
                            "UPI"
                            &&
                            $raw_status ===
                            "COMPLETED"
                        ) {

                            $payment_status =
                                "Paid";


                        } else {

                            if (
                                $raw_payment_status ===
                                "paid"
                            ) {

                                $payment_status =
                                    "Paid";

                            } elseif (
                                $raw_payment_status ===
                                "pending"
                            ) {

                                $payment_status =
                                    "Pending";

                            } elseif (
                                $raw_payment_status ===
                                "failed"
                            ) {

                                $payment_status =
                                    "Failed";

                            } else {

                                $payment_status =
                                    ucfirst(
                                        $raw_payment_status
                                    );
                            }
                        }


                        /* =====================================
                           PAYMENT CSS CLASS
                           
                           IMPORTANT FIX:
                           Use the DISPLAYED status,
                           not the raw database status.
                           
                           This prevents:
                           text = Paid
                           class = payment-pending
                           
                           which was causing the
                           yellow COD Paid badge.
                        ====================================== */

                        if (
                            strtolower(
                                $payment_status
                            ) === "paid"
                        ) {

                            $payment_status_class =
                                "payment-paid";


                        } elseif (
                            strtolower(
                                $payment_status
                            ) === "pending"
                        ) {

                            $payment_status_class =
                                "payment-pending";


                        } elseif (
                            strtolower(
                                $payment_status
                            ) === "failed"
                        ) {

                            $payment_status_class =
                                "payment-failed";


                        } else {

                            $payment_status_class =
                                "payment-other";
                        }


                        /* =====================================
                           STATUS OPTIONS
                        ====================================== */

                        if (
                            $order_type_upper ===
                            "DELIVERY"
                        ) {

                            $status_options = [

                                "CONFIRMED" =>
                                    "Confirmed",

                                "PREPARING" =>
                                    "Preparing",

                                "READY" =>
                                    "Ready",

                                "COMPLETED" =>
                                    "Delivered",

                                "CANCELLED" =>
                                    "Cancelled"

                            ];


                        } elseif (
                            $order_type_upper ===
                            "TAKEAWAY"
                        ) {

                            $status_options = [

                                "CONFIRMED" =>
                                    "Confirmed",

                                "PREPARING" =>
                                    "Preparing",

                                "READY" =>
                                    "Ready for Pickup",

                                "COMPLETED" =>
                                    "Picked Up",

                                "CANCELLED" =>
                                    "Cancelled"

                            ];


                        } elseif (
                            $order_type_upper ===
                            "DINE_IN"
                        ) {

                            $status_options = [

                                "CONFIRMED" =>
                                    "Confirmed",

                                "PREPARING" =>
                                    "Preparing",

                                "READY" =>
                                    "Ready",

                                "COMPLETED" =>
                                    "Served",

                                "CANCELLED" =>
                                    "Cancelled"

                            ];


                        } else {

                            $status_options = [

                                "CONFIRMED" =>
                                    "Confirmed",

                                "PREPARING" =>
                                    "Preparing",

                                "READY" =>
                                    "Ready",

                                "COMPLETED" =>
                                    "Completed",

                                "CANCELLED" =>
                                    "Cancelled"

                            ];
                        }

                        ?>


                        <tr>


                            <!-- =================================
                                 ORDER
                            ================================== -->

                            <td>


                                <div class="order-number">


                                    <strong>

                                        #

                                        <?php

                                        echo htmlspecialchars(
                                            $order[
                                                "order_number"
                                            ]
                                        );

                                        ?>

                                    </strong>


                                    <span>

                                        <?php

                                        echo date(
                                            "d M Y, h:i A",
                                            strtotime(
                                                $order[
                                                    "created_at"
                                                ]
                                            )
                                        );

                                        ?>

                                    </span>


                                </div>


                            </td>


                            <!-- =================================
                                 CUSTOMER
                            ================================== -->

                            <td>


                                <div class="customer-info">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $order[
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
                                            $order[
                                                "user_email"
                                            ]
                                            ??
                                            ""
                                        );

                                        ?>

                                    </span>


                                </div>


                            </td>


                            <!-- =================================
                                 TYPE
                            ================================== -->

                            <td>


                                <span
                                    class="
                                        order-type-badge
                                        <?php
                                        echo strtolower(
                                            $order_type_upper
                                        );
                                        ?>
                                    "
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $display_order_type
                                    );

                                    ?>

                                </span>


                            </td>


                            <!-- =================================
                                 PAYMENT
                            ================================== -->

                            <td>


                                <div class="payment-info">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $payment_method
                                        );

                                        ?>

                                    </strong>


                                    <span
                                        class="
                                            <?php
                                            echo
                                                $payment_status_class;
                                            ?>
                                        "
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $payment_status
                                        );

                                        ?>

                                    </span>


                                </div>


                            </td>


                            <!-- =================================
                                 TOTAL
                            ================================== -->

                            <td>


                                <strong
                                    class="order-total"
                                >

                                    ₹<?php

                                    echo number_format(
                                        (float)
                                        $order[
                                            "total"
                                        ],
                                        2
                                    );

                                    ?>

                                </strong>


                            </td>


                            <!-- =================================
                                 CURRENT STATUS
                            ================================== -->

                            <td>


                                <span
                                    class="
                                        status-badge
                                        <?php
                                        echo strtolower(
                                            $raw_status
                                        );
                                        ?>
                                    "
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $display_status
                                    );

                                    ?>

                                </span>


                            </td>


                            <!-- =================================
                                 UPDATE
                            ================================== -->

                            <td>


                                <form
                                    method="POST"
                                    action=""
                                    class="status-form"
                                >


                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?php
                                        echo (int)
                                            $order[
                                                "id"
                                            ];
                                        ?>"
                                    >


                                    <select
                                        name="new_status"
                                        class="status-select"
                                    >


                                        <?php foreach (
                                            $status_options
                                            as $status_value =>
                                            $status_label
                                        ): ?>


                                            <option
                                                value="<?php
                                                echo htmlspecialchars(
                                                    $status_value
                                                );
                                                ?>"
                                                <?php

                                                echo
                                                    $raw_status ===
                                                    $status_value
                                                    ? "selected"
                                                    : "";

                                                ?>
                                            >

                                                <?php

                                                echo htmlspecialchars(
                                                    $status_label
                                                );

                                                ?>

                                            </option>


                                        <?php endforeach; ?>


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


            <div class="empty-orders">


                <h2>
                    No Orders Found
                </h2>


                <p>
                    There are no orders in the system yet.
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