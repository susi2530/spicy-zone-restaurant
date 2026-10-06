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

require_once "db.php";


$message = "";
$message_type = "";


/* =========================================================
   REGISTERED MESSAGE
========================================================= */

if (
    isset($_GET["registered"]) &&
    $_GET["registered"] == "1"
) {

    $message =
        "Account created successfully. Please login.";

    $message_type =
        "success";
}


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
            "Please enter your email and password.";

        $message_type =
            "error";


    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

        $message_type =
            "error";


    } else {


        $stmt =
            $conn->prepare(
                "SELECT
                    id,
                    name,
                    email,
                    password
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );


        if (!$stmt) {

            $message =
                "Unable to process login.";

            $message_type =
                "error";

        } else {

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

                $user =
                    $result->fetch_assoc();


                if (
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {


                    /* =========================================
                       CREATE NEW SESSION ID
                    ========================================== */

                    session_regenerate_id(
                        true
                    );


                    $_SESSION["user_id"] =
                        (int)
                        $user["id"];


                    $_SESSION["user_name"] =
                        $user["name"];


                    $_SESSION["user_email"] =
                        $user["email"];


                    $_SESSION["user_logged_in"] =
                        true;


                    /* =========================================
                       SAVE SESSION BEFORE REDIRECT
                    ========================================== */

                    session_write_close();


                    header(
                        "Location: index.php"
                    );

                    exit();


                } else {

                    $message =
                        "Incorrect email or password.";

                    $message_type =
                        "error";
                }


            } else {

                $message =
                    "Incorrect email or password.";

                $message_type =
                    "error";
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
        Login - Spicy Zone Restaurant
    </title>


    <link
        rel="stylesheet"
        href="css/login.css"
    >

</head>

<body>


<div class="login-page">


    <div class="login-box">


        <div class="login-logo">


            <img
                src="images/logo.png"
                alt="Spicy Zone Logo"
            >


        </div>


        <h1>
            Welcome Back
        </h1>


        <p class="subtitle">
            Login to Spicy Zone Restaurant
        </p>


        <?php if (
            $message !== ""
        ): ?>


            <div
                class="message
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


        <form
            method="POST"
            action=""
        >


            <div class="form-group">


                <label for="email">
                    Email Address
                </label>


                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
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
                    placeholder="Enter your password"
                    required
                >


            </div>


            <div class="forgot-password">


                <a href="forgot-password.php">
                    Forgot Password?
                </a>


            </div>


            <button
                type="submit"
                class="login-btn"
            >
                Login
            </button>


        </form>


        <p class="register-text">


            Don't have an account?


            <a href="register.php">
                Create an Account
            </a>


        </p>


    </div>


</div>


</body>
</html>