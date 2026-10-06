<?php

session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$order_id = isset($_GET['order_id'])
    ? (int)$_GET['order_id']
    : (int)($_POST['order_id'] ?? 0);

$food_id = isset($_GET['food_id'])
    ? (int)$_GET['food_id']
    : (int)($_POST['food_id'] ?? 0);

if ($order_id <= 0 || $food_id <= 0) {
    header("Location: orders.php");
    exit();
}


/* ================= CHECK USER ORDER ================= */

$check_stmt = $conn->prepare(
    "SELECT
        orders.id,
        orders.order_status,
        order_items.food_id,
        order_items.food_name
     FROM orders
     INNER JOIN order_items
        ON orders.id = order_items.order_id
     WHERE orders.id = ?
       AND orders.user_id = ?
       AND order_items.food_id = ?"
);

$check_stmt->bind_param(
    "iii",
    $order_id,
    $user_id,
    $food_id
);

$check_stmt->execute();

$check_result = $check_stmt->get_result();

if ($check_result->num_rows !== 1) {
    header("Location: orders.php");
    exit();
}

$order_item = $check_result->fetch_assoc();

$check_stmt->close();


/* ================= GET FOOD ================= */

$food_stmt = $conn->prepare(
    "SELECT
        foods.name,
        foods.image,
        foods.description
     FROM foods
     WHERE foods.id = ?"
);

$food_stmt->bind_param("i", $food_id);
$food_stmt->execute();

$food_result = $food_stmt->get_result();

if ($food_result->num_rows !== 1) {
    header("Location: orders.php");
    exit();
}

$food = $food_result->fetch_assoc();

$food_stmt->close();


/* ================= EXISTING REVIEW ================= */

$review_stmt = $conn->prepare(
    "SELECT
        id,
        rating,
        review_text
     FROM reviews
     WHERE user_id = ?
       AND food_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

$review_stmt->bind_param(
    "ii",
    $user_id,
    $food_id
);

$review_stmt->execute();

$review_result = $review_stmt->get_result();

$existing_review = null;

if ($review_result->num_rows === 1) {
    $existing_review = $review_result->fetch_assoc();
}

$review_stmt->close();


/* ================= FORM VALUES ================= */

$rating = $existing_review
    ? (int)$existing_review['rating']
    : 0;

$review_text = $existing_review
    ? $existing_review['review_text']
    : '';

$message = '';
$message_type = '';


/* ================= SAVE REVIEW ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rating = isset($_POST['rating'])
        ? (int)$_POST['rating']
        : 0;

    $review_text = trim(
        $_POST['review_text'] ?? ''
    );


    if ($rating < 1 || $rating > 5) {

        $message = "Please select a rating from 1 to 5.";
        $message_type = "error";

    } elseif ($review_text === '') {

        $message = "Please write your review.";
        $message_type = "error";

    } else {


        /* Update existing review */

        if ($existing_review) {

            $update_stmt = $conn->prepare(
                "UPDATE reviews
                 SET rating = ?,
                     review_text = ?
                 WHERE id = ?
                   AND user_id = ?
                   AND food_id = ?"
            );

            $update_stmt->bind_param(
                "isiii",
                $rating,
                $review_text,
                $existing_review['id'],
                $user_id,
                $food_id
            );

            if ($update_stmt->execute()) {

                $message = "Your review has been updated.";
                $message_type = "success";

            } else {

                $message = "Unable to update your review.";
                $message_type = "error";
            }

            $update_stmt->close();


        } else {


            /* Insert new review */

            $insert_stmt = $conn->prepare(
                "INSERT INTO reviews
                (
                    user_id,
                    food_id,
                    rating,
                    review_text
                )
                VALUES (?, ?, ?, ?)"
            );

            $insert_stmt->bind_param(
                "iiis",
                $user_id,
                $food_id,
                $rating,
                $review_text
            );

            if ($insert_stmt->execute()) {

                $message = "Thank you! Your review has been added.";
                $message_type = "success";

                $existing_review = [
                    'id' => $insert_stmt->insert_id,
                    'rating' => $rating,
                    'review_text' => $review_text
                ];

            } else {

                $message = "Unable to add your review.";
                $message_type = "error";
            }

            $insert_stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Rate & Review - Spicy Zone Restaurant
    </title>

    <link rel="stylesheet"
          href="css/rate-review.css">

</head>

<body>


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

    <nav class="nav-links">

        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="mood.php">Mood</a>
        <a href="favorites.php">Favorites</a>
        <a href="cart.php">Cart</a>
        <a href="my-bookings.php">My Bookings</a>
        <a href="profile.php">Profile</a>

    </nav>

</header>


<main class="review-page">

    <a
        href="order-details.php?order_id=<?php echo $order_id; ?>"
        class="back-link"
    >
        ← Back to Order
    </a>


    <section class="review-card">


        <div class="food-preview">

            <div class="food-image">

                <img
                    src="images/<?php echo htmlspecialchars($food['image']); ?>"
                    alt="<?php echo htmlspecialchars($food['name']); ?>"
                >

            </div>


            <div class="food-info">

                <p class="small-heading">
                    YOUR FOOD
                </p>

                <h1>
                    <?php echo htmlspecialchars($food['name']); ?>
                </h1>

                <?php if (!empty($food['description'])): ?>

                    <p>
                        <?php echo htmlspecialchars($food['description']); ?>
                    </p>

                <?php endif; ?>

            </div>

        </div>


        <?php if ($message !== ''): ?>

            <div class="review-message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <div class="review-heading">

            <h2>
                <?php echo $existing_review
                    ? 'Edit Your Review'
                    : 'Rate This Food'; ?>
            </h2>

            <p>
                How was your experience with this dish?
            </p>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="order_id"
                value="<?php echo $order_id; ?>"
            >

            <input
                type="hidden"
                name="food_id"
                value="<?php echo $food_id; ?>"
            >


            <div class="rating-box">

                <label>
                    Your Rating
                </label>

                <div class="stars">

                    <?php for ($i = 5; $i >= 1; $i--): ?>

                        <input
                            type="radio"
                            id="star<?php echo $i; ?>"
                            name="rating"
                            value="<?php echo $i; ?>"
                            <?php echo ($rating === $i)
                                ? 'checked'
                                : ''; ?>
                        >

                        <label
                            for="star<?php echo $i; ?>"
                        >
                            ★
                        </label>

                    <?php endfor; ?>

                </div>

            </div>


            <div class="form-group">

                <label for="review_text">
                    Your Review
                </label>

                <textarea
                    id="review_text"
                    name="review_text"
                    rows="6"
                    placeholder="Tell us about the taste, quality and your experience..."
                    required
                ><?php echo htmlspecialchars($review_text); ?></textarea>

            </div>


            <button
                type="submit"
                class="submit-review-btn"
            >
                <?php echo $existing_review
                    ? 'Update Review'
                    : 'Submit Review'; ?>
            </button>

        </form>


    </section>

</main>


<footer>

    <p>
        © 2026 Spicy Zone Restaurant.
        All Rights Reserved.
    </p>

</footer>


</body>
</html>