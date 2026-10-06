<?php

session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$message = "";
$message_type = "";


/* ================= UPDATE PROFILE ================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $mobile = trim($_POST["mobile"]);
    $address = trim($_POST["address"]);

    if ($name === "" || $mobile === "") {

        $message = "Please fill in your name and mobile number.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "UPDATE users
             SET name = ?, mobile = ?, address = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssi",
            $name,
            $mobile,
            $address,
            $user_id
        );

        if ($stmt->execute()) {

            $_SESSION["user_name"] = $name;

            $message = "Profile updated successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to update profile.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* ================= GET USER DETAILS ================= */

$stmt = $conn->prepare(
    "SELECT name, email, mobile, address
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Profile - Spicy Zone Restaurant</title>

    <link rel="stylesheet" href="css/edit-profile.css">

</head>

<body>


<!-- ================= NAVBAR ================= -->

<header class="navbar">

    <div class="brand">

        <a href="index.php">
            <img src="images/logo.png" alt="Spicy Zone Logo">
        </a>

        <span>Spicy Zone Restaurant</span>

    </div>

    <nav>

        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="mood.php">Mood</a>
        <a href="favorites.php">Favorites</a>
        <a href="cart.php">Cart</a>
        <a href="my-bookings.php">My Bookings</a>
        <a href="profile.php">Profile</a>

    </nav>

</header>


<!-- ================= EDIT PROFILE ================= -->

<main class="profile-page">

    <div class="password-container">

        <a href="profile.php" class="back-link">
            ← Back to Profile
        </a>


        <section class="password-card">

            <div class="password-heading">

                <h1>Edit Profile</h1>

                <p>
                    Update your personal details and saved address.
                </p>

            </div>


            <?php if ($message !== ""): ?>

                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php endif; ?>


            <form method="POST">


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php echo htmlspecialchars($user["name"]); ?>"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        value="<?php echo htmlspecialchars($user["email"]); ?>"
                        readonly
                    >

                </div>


                <!-- MOBILE -->

                <div class="form-group">

                    <label for="mobile">
                        Mobile Number
                    </label>

                    <input
                        type="tel"
                        id="mobile"
                        name="mobile"
                        value="<?php echo htmlspecialchars($user["mobile"]); ?>"
                        required
                    >

                </div>


                <!-- ADDRESS -->

                <div class="form-group">

                    <label for="address">
                        Saved Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        rows="5"
                        placeholder="Enter your address"
                    ><?php echo htmlspecialchars($user["address"] ?? ""); ?></textarea>

                </div>


                <button
                    type="submit"
                    class="green-btn"
                >
                    Save Changes
                </button>


            </form>

        </section>

    </div>

</main>


<footer>

    <p>
        © 2026 Spicy Zone Restaurant. All Rights Reserved.
    </p>

</footer>

</body>
</html>