<?php

session_start();
require_once "db.php";


/* ================= CHECK RESET SESSION ================= */

if (!isset($_SESSION["reset_user_id"])) {

    header("Location: forgot-password.php");
    exit();
}

$user_id = (int)$_SESSION["reset_user_id"];

$email = $_SESSION["reset_user_email"] ?? "";


$message = "";
$message_type = "";


/* ================= UPDATE PASSWORD ================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";


    if ($new_password === "") {

        $message = "Please enter a new password.";
        $message_type = "error";

    } elseif ($confirm_password === "") {

        $message = "Please confirm your new password.";
        $message_type = "error";

    } elseif ($new_password !== $confirm_password) {

        $message = "Passwords do not match.";
        $message_type = "error";

    } elseif (strlen($new_password) < 6) {

        $message = "Password must contain at least 6 characters.";
        $message_type = "error";

    } else {

        /* Hash new password */

        $hashed_password = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );


        /* Update password */

        $stmt = $conn->prepare(
            "UPDATE users
             SET password = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $hashed_password,
            $user_id
        );


        if ($stmt->execute()) {

            /*
                Clear reset session after password change
            */

            unset($_SESSION["reset_user_id"]);
            unset($_SESSION["reset_user_email"]);


            /*
                Send user back to login
            */

            header(
                "Location: login.php?reset=success"
            );

            exit();

        } else {

            $message =
                "Unable to change password. Please try again.";

            $message_type = "error";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Reset Password - Spicy Zone Restaurant</title>

    <link rel="stylesheet"
          href="css/forgot-password.css">

</head>

<body>

<div class="forgot-page">

    <div class="forgot-card">

        <div class="logo-box">

            <img
                src="images/logo.png"
                alt="Spicy Zone Logo"
            >

        </div>


        <p class="small-heading">
            CHANGE PASSWORD
        </p>


        <h1>
            Create New Password
        </h1>


        <p class="subtitle">

            <?php if ($email !== ""): ?>

                Email:
                <?php echo htmlspecialchars($email); ?>

            <?php else: ?>

                Create a new password for your account.

            <?php endif; ?>

        </p>


        <?php if ($message !== ""): ?>

            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    placeholder="Enter new password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm new password"
                    required
                >

            </div>


            <button
                type="submit"
                class="green-btn"
            >
                Change Password
            </button>


        </form>


        <a
            href="login.php"
            class="back-link"
        >
            ← Back to Login
        </a>

    </div>

</div>

</body>
</html>