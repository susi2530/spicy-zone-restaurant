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
    : 0;

if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}


/* ================= CHECK ORDER ================= */

$order_stmt = $conn->prepare(
    "SELECT
        id,
        order_number,
        created_at
     FROM orders
     WHERE id = ?
       AND user_id = ?"
);

$order_stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);

$order_stmt->execute();

$order_result = $order_stmt->get_result();

if ($order_result->num_rows !== 1) {
    header("Location: orders.php");
    exit();
}

$order = $order_result->fetch_assoc();

$order_stmt->close();


/* ================= GET ORDER FOODS ================= */

$item_stmt = $conn->prepare(
    "SELECT
        order_items.food_id,
        order_items.food_name,
        order_items.price,
        order_items.quantity,
        foods.image
     FROM order_items
     LEFT JOIN foods
        ON order_items.food_id = foods.id
     WHERE order_items.order_id = ?
     ORDER BY order_items.id ASC"
);

$item_stmt->bind_param(
    "i",
    $order_id
);

$item_stmt->execute();

$item_result = $item_stmt->get_result();

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
          href="css/rate-order.css">

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


<main class="rate-order-page">

    <a
        href="orders.php"
        class="back-link"
    >
        ← Back to My Orders
    </a>


    <section class="page-header">

        <p class="small-heading">
            YOUR ORDER
        </p>

        <h1>
            Rate & Review
        </h1>

        <p>
            Order #
            <?php echo htmlspecialchars($order['order_number']); ?>
        </p>

    </section>


    <section class="food-list">

        <?php if ($item_result->num_rows > 0): ?>

            <?php while ($item = $item_result->fetch_assoc()): ?>

                <div class="food-review-card">


                    <div class="food-preview">

                        <div class="food-image">

                            <?php if (!empty($item['image'])): ?>

                                <img
                                    src="images/<?php echo htmlspecialchars($item['image']); ?>"
                                    alt="<?php echo htmlspecialchars($item['food_name']); ?>"
                                >

                            <?php endif; ?>

                        </div>


                        <div class="food-info">

                            <h2>
                                <?php echo htmlspecialchars(
                                    $item['food_name']
                                ); ?>
                            </h2>

                            <p>
                                Quantity:
                                <?php echo (int)$item['quantity']; ?>
                            </p>

                        </div>

                    </div>


                    <a
                        href="rate-review.php?order_id=<?php echo $order_id; ?>&food_id=<?php echo (int)$item['food_id']; ?>"
                        class="rate-btn"
                    >
                        Rate & Review
                    </a>


                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-review">

                <h2>
                    No Items Found
                </h2>

                <a href="orders.php">
                    Back to My Orders
                </a>

            </div>

        <?php endif; ?>

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