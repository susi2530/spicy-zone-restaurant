<?php

session_start();
require_once "db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $message = "Please enter your registered email address.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            /*
                Local academic project:
                Store the verified user temporarily in session.
            */

            $_SESSION["reset_user_id"] = (int)$user["id"];
            $_SESSION["reset_user_email"] = $email;

            header("Location: reset-password.php");
            exit();

        } else {

            $message = "No account found with this email address.";
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

    <title>Forgot Password - Spicy Zone Restaurant</title>

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
            ACCOUNT RECOVERY
        </p>


        <h1>
            Forgot Password?
        </h1>


        <p class="subtitle">
            Enter your registered email address to continue.
        </p>


        <?php if ($message !== ""): ?>

            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
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
                    placeholder="Enter your registered email"
                    required
                >

            </div>


            <button
                type="submit"
                class="green-btn"
            >
                Continue
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