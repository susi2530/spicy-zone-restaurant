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

$message = "";
$message_type = "";


/* =========================================================
   CHANGE PASSWORD
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current_password =
        $_POST["current_password"] ?? "";

    $new_password =
        $_POST["new_password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    /* =====================================================
       EMPTY CHECK
    ===================================================== */

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {

        $message =
            "Please fill in all password fields.";

        $message_type =
            "error";

    }


    /* =====================================================
       GET CURRENT PASSWORD
    ===================================================== */

    else {

        $stmt = $conn->prepare(
            "SELECT password
             FROM users
             WHERE id = ?
             LIMIT 1"
        );


        if (!$stmt) {

            $message =
                "Unable to process your request.";

            $message_type =
                "error";

        } else {

            $stmt->bind_param(
                "i",
                $user_id
            );

            $stmt->execute();


            $result =
                $stmt->get_result();


            if (
                $result->num_rows !== 1
            ) {

                $message =
                    "Unable to verify your account.";

                $message_type =
                    "error";

            } else {

                $user =
                    $result->fetch_assoc();


                /* =================================================
                   VERIFY CURRENT PASSWORD
                ================================================= */

                if (
                    !password_verify(
                        $current_password,
                        $user["password"]
                    )
                ) {

                    $message =
                        "Current password is incorrect.";

                    $message_type =
                        "error";

                }


                /* =================================================
                   CHECK NEW PASSWORD
                ================================================= */

                elseif (
                    $new_password !== $confirm_password
                ) {

                    $message =
                        "Passwords do not match.";

                    $message_type =
                        "error";

                }


                /* =================================================
                   PASSWORD LENGTH
                ================================================= */

                elseif (
                    strlen($new_password) < 6
                ) {

                    $message =
                        "Password must contain at least 6 characters.";

                    $message_type =
                        "error";

                }


                /* =================================================
                   NEW PASSWORD SAME AS OLD
                ================================================= */

                elseif (
                    password_verify(
                        $new_password,
                        $user["password"]
                    )
                ) {

                    $message =
                        "New password must be different from your current password.";

                    $message_type =
                        "error";

                }


                /* =================================================
                   UPDATE PASSWORD
                ================================================= */

                else {

                    $hashed_password =
                        password_hash(
                            $new_password,
                            PASSWORD_DEFAULT
                        );


                    $update_stmt =
                        $conn->prepare(
                            "UPDATE users
                             SET password = ?
                             WHERE id = ?"
                        );


                    if (!$update_stmt) {

                        $message =
                            "Unable to change password.";

                        $message_type =
                            "error";

                    } else {

                        $update_stmt->bind_param(
                            "si",
                            $hashed_password,
                            $user_id
                        );


                        if (
                            $update_stmt->execute()
                        ) {

                            $message =
                                "Password changed successfully.";

                            $message_type =
                                "success";


                            /*
                                Clear form after
                                successful change.
                            */

                            $_POST = [];

                        } else {

                            $message =
                                "Unable to change password.";

                            $message_type =
                                "error";
                        }


                        $update_stmt->close();
                    }

                }

            }


            $stmt->close();
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
        Change Password - Spicy Zone
    </title>

    <link
        rel="stylesheet"
        href="css/change-password.css"
    >

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

</header>


<!-- =========================================================
     PAGE
========================================================= -->

<main class="profile-page">

    <div class="password-container">


        <!-- ================= BACK ================= -->

        <a
            href="profile.php"
            class="back-link"
        >
            ← Back to Profile
        </a>


        <!-- ================= CARD ================= -->

        <section class="password-card">


            <!-- ================= HEADING ================= -->

            <div class="password-heading">

                <h1>
                    Change Password
                </h1>

                <p>
                    Update your account password securely.
                </p>

            </div>


            <!-- ================= MESSAGE ================= -->

            <?php if ($message !== ""): ?>

                <div
                    class="message <?php echo htmlspecialchars($message_type); ?>"
                >

                    <?php
                    echo htmlspecialchars(
                        $message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- ================= FORM ================= -->

            <form
                method="POST"
                action=""
                autocomplete="off"
            >


                <!-- ================= CURRENT PASSWORD ================= -->

                <div class="form-group">

                    <label for="current_password">
                        Current Password
                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Enter your current password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- ================= NEW PASSWORD ================= -->

                <div class="form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Enter new password"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <!-- ================= CONFIRM PASSWORD ================= -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <!-- ================= BUTTON ================= -->

                <button
                    type="submit"
                    class="green-btn"
                >
                    Change Password
                </button>


            </form>


        </section>

    </div>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>
        © 2026 Spicy Zone Restaurant. All Rights Reserved.
    </p>

</footer>


</body>
</html>