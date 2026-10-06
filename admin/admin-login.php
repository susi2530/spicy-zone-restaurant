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
   ALREADY LOGGED IN
========================================================= */

if (isset($_SESSION["admin_id"])) {

    header("Location: dashboard.php");
    exit();
}


$message = "";


/* =========================================================
   LOGIN
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $email =
        trim(
            $_POST["email"] ?? ""
        );


    $password =
        $_POST["password"] ?? "";


    if (
        $email === "" ||
        $password === ""
    ) {

        $message =
            "Please enter email and password.";

    } else {

        $stmt =
            $conn->prepare(
                "SELECT
                    admin_id,
                    name,
                    email,
                    password
                 FROM admins
                 WHERE email = ?
                 LIMIT 1"
            );


        if (!$stmt) {

            die(
                "Database error: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "s",
            $email
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        if (
            $result->num_rows === 1
        ) {

            $admin =
                $result->fetch_assoc();


            if (
                password_verify(
                    $password,
                    $admin["password"]
                )
            ) {


                /* =========================================
                   CREATE NEW SESSION ID AFTER LOGIN
                ========================================== */

                session_regenerate_id(
                    true
                );


                $_SESSION["admin_id"] =
                    (int)
                    $admin["admin_id"];


                $_SESSION["admin_name"] =
                    $admin["name"];


                $_SESSION["admin_email"] =
                    $admin["email"];


                $_SESSION["admin_logged_in"] =
                    true;


                /* =========================================
                   SAVE SESSION BEFORE REDIRECT
                ========================================== */

                session_write_close();


                header(
                    "Location: dashboard.php"
                );

                exit();


            } else {

                $message =
                    "Invalid email or password.";
            }

        } else {

            $message =
                "Invalid email or password.";
        }


        $stmt->close();
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
        Admin Login - Spicy Zone
    </title>


    <link
        rel="stylesheet"
        href="css/admin-login.css"
    >

</head>

<body>


<main class="admin-login-page">


    <section class="admin-login-card">


        <div class="admin-logo">

            <img
                src="../images/logo.png"
                alt="Spicy Zone Logo"
            >

        </div>


        <p class="admin-label">
            ADMIN PANEL
        </p>


        <h1>
            Admin Login
        </h1>


        <p class="admin-subtitle">
            Sign in to manage Spicy Zone.
        </p>


        <?php if (
            $message !== ""
        ): ?>


            <div class="login-message error">

                <?php

                echo htmlspecialchars(
                    $message
                );

                ?>

            </div>


        <?php endif; ?>


        <form method="POST">


            <div class="form-group">


                <label for="email">
                    Email Address
                </label>


                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin@spicyzone.com"
                    required
                >


            </div>


            <div class="form-group">


                <label for="password">
                    Password
                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >


            </div>


            <button
                type="submit"
                class="login-btn"
            >
                Login to Admin
            </button>


        </form>


    </section>


</main>


</body>
</html>