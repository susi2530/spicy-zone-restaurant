<?php

session_start();
require_once "../db.php";


/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin-login.php");
    exit();
}


/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$message_type = "";


/* =========================================================
   DELETE FOOD
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_food"])
) {

    $food_id = isset($_POST["food_id"])
        ? (int) $_POST["food_id"]
        : 0;


    if ($food_id > 0) {

        $image_stmt = $conn->prepare(
            "SELECT image
             FROM foods
             WHERE id = ?
             LIMIT 1"
        );

        $image_stmt->bind_param(
            "i",
            $food_id
        );

        $image_stmt->execute();

        $image_result =
            $image_stmt->get_result();

        $image_row =
            $image_result->fetch_assoc();

        $image_stmt->close();


        $delete_stmt = $conn->prepare(
            "DELETE FROM foods
             WHERE id = ?"
        );

        $delete_stmt->bind_param(
            "i",
            $food_id
        );


        if ($delete_stmt->execute()) {

            if (
                $image_row &&
                !empty($image_row["image"])
            ) {

                $image_path =
                    "../images/" .
                    basename(
                        $image_row["image"]
                    );

                if (
                    is_file($image_path)
                ) {
                    unlink($image_path);
                }
            }


            $message =
                "Food deleted successfully.";

            $message_type =
                "success";

        } else {

            $message =
                "Unable to delete food.";

            $message_type =
                "error";
        }


        $delete_stmt->close();
    }
}


/* =========================================================
   ADD / EDIT FOOD
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_food"])
) {

    $food_id = isset($_POST["food_id"])
        ? (int) $_POST["food_id"]
        : 0;

    $name =
        trim(
            $_POST["name"] ?? ""
        );

    $description =
        trim(
            $_POST["description"] ?? ""
        );

    $price =
        (float) (
            $_POST["price"] ?? 0
        );

    $category_id = isset($_POST["category_id"])
        ? (int) $_POST["category_id"]
        : 0;


    /*
        Availability control removed from UI.
        Foods are saved as available.
    */

    $is_available = 1;


    /* =====================================================
       VALIDATION
    ====================================================== */

    if (
        $name === "" ||
        $description === "" ||
        $price <= 0 ||
        $category_id <= 0
    ) {

        $message =
            "Please fill all required food details.";

        $message_type =
            "error";

    } else {

        $image_name = "";


        /* =================================================
           GET EXISTING IMAGE
        ================================================= */

        if ($food_id > 0) {

            $old_image_stmt = $conn->prepare(
                "SELECT image
                 FROM foods
                 WHERE id = ?
                 LIMIT 1"
            );

            $old_image_stmt->bind_param(
                "i",
                $food_id
            );

            $old_image_stmt->execute();

            $old_image_result =
                $old_image_stmt->get_result();

            $old_image_row =
                $old_image_result->fetch_assoc();

            $old_image_stmt->close();


            if ($old_image_row) {

                $image_name =
                    $old_image_row["image"] ?? "";
            }
        }


        /* =================================================
           IMAGE UPLOAD
        ================================================= */

        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] === UPLOAD_ERR_OK
        ) {

            $upload_dir =
                "../images/";


            if (!is_dir($upload_dir)) {

                mkdir(
                    $upload_dir,
                    0777,
                    true
                );
            }


            $tmp_name =
                $_FILES["image"]["tmp_name"];

            $original_name =
                $_FILES["image"]["name"];

            $file_size =
                (int) $_FILES["image"]["size"];


            $extension =
                strtolower(
                    pathinfo(
                        $original_name,
                        PATHINFO_EXTENSION
                    )
                );


            $allowed_extensions = [
                "png",
                "jpg",
                "jpeg",
                "webp"
            ];


            if (
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {

                $message =
                    "Only PNG, JPG, JPEG and WEBP images are allowed.";

                $message_type =
                    "error";

            } elseif (
                $file_size > 5 * 1024 * 1024
            ) {

                $message =
                    "Image size must be below 5 MB.";

                $message_type =
                    "error";

            } else {

                $new_file_name =
                    uniqid(
                        "food_",
                        true
                    ) .
                    "." .
                    $extension;


                $destination =
                    $upload_dir .
                    $new_file_name;


                if (
                    move_uploaded_file(
                        $tmp_name,
                        $destination
                    )
                ) {

                    /* Delete old image */

                    if (
                        $image_name !== ""
                    ) {

                        $old_path =
                            $upload_dir .
                            basename(
                                $image_name
                            );

                        if (
                            is_file(
                                $old_path
                            )
                        ) {

                            unlink(
                                $old_path
                            );
                        }
                    }


                    $image_name =
                        $new_file_name;

                } else {

                    $message =
                        "Unable to upload image.";

                    $message_type =
                        "error";
                }
            }
        }


        /* =================================================
           SAVE FOOD
        ================================================= */

        if ($message === "") {

            if ($food_id > 0) {

                $update_stmt = $conn->prepare(
                    "UPDATE foods
                     SET
                        name = ?,
                        description = ?,
                        price = ?,
                        category_id = ?,
                        image = ?,
                        is_available = ?
                     WHERE id = ?"
                );


                $update_stmt->bind_param(
                    "ssdisii",
                    $name,
                    $description,
                    $price,
                    $category_id,
                    $image_name,
                    $is_available,
                    $food_id
                );


                if ($update_stmt->execute()) {

                    $message =
                        "Food updated successfully.";

                    $message_type =
                        "success";

                } else {

                    $message =
                        "Unable to update food.";

                    $message_type =
                        "error";
                }


                $update_stmt->close();

            } else {

                $insert_stmt = $conn->prepare(
                    "INSERT INTO foods
                    (
                        name,
                        description,
                        price,
                        category_id,
                        image,
                        is_available
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );


                $insert_stmt->bind_param(
                    "ssdisi",
                    $name,
                    $description,
                    $price,
                    $category_id,
                    $image_name,
                    $is_available
                );


                if ($insert_stmt->execute()) {

                    $message =
                        "Food added successfully.";

                    $message_type =
                        "success";

                } else {

                    $message =
                        "Unable to add food.";

                    $message_type =
                        "error";
                }


                $insert_stmt->close();
            }
        }
    }
}


/* =========================================================
   GET EDIT FOOD
========================================================= */

$edit_food = null;


if (isset($_GET["edit"])) {

    $edit_id =
        (int) $_GET["edit"];


    if ($edit_id > 0) {

        $edit_stmt = $conn->prepare(
            "SELECT
                id,
                name,
                description,
                price,
                category_id,
                image,
                is_available
             FROM foods
             WHERE id = ?
             LIMIT 1"
        );

        $edit_stmt->bind_param(
            "i",
            $edit_id
        );

        $edit_stmt->execute();

        $edit_result =
            $edit_stmt->get_result();

        $edit_food =
            $edit_result->fetch_assoc();

        $edit_stmt->close();
    }
}


/* =========================================================
   GET CATEGORIES
========================================================= */

$categories_result =
    $conn->query(
        "SELECT
            id,
            name
         FROM categories
         ORDER BY name ASC"
    );


/* =========================================================
   GET ALL FOODS
========================================================= */

$foods_stmt = $conn->prepare(
    "SELECT
        f.id,
        f.name,
        f.description,
        f.price,
        f.image,
        f.is_available,
        f.category_id,
        c.name AS category_name
     FROM foods f
     LEFT JOIN categories c
        ON f.category_id = c.id
     ORDER BY f.id ASC"
);

$foods_stmt->execute();

$foods_result =
    $foods_stmt->get_result();

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
        Manage Foods - Spicy Zone Admin
    </title>

    <link
        rel="stylesheet"
        href="css/foods.css"
    >

</head>

<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="admin-navbar">

    <div class="admin-brand">

        <a href="dashboard.php">

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

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="orders.php">
            Orders
        </a>

        <a href="bookings.php">
            Bookings
        </a>

        <a
            href="foods.php"
            class="active"
        >
            Foods
        </a>

        <a href="categories.php">
            Categories
        </a>

        <a href="users.php">
            Users
        </a>

        <a href="reviews.php">
            Reviews
        </a>

        <a href="coupons.php">
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
     PAGE
========================================================= -->

<main class="foods-page">


    <!-- =====================================================
         HEADING
    ====================================================== -->

    <section class="page-heading">

        <p class="eyebrow">
            ADMIN PANEL
        </p>

        <h1>
            Manage Foods
        </h1>

        <p>
            Add, edit and manage food items.
        </p>

    </section>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== ""): ?>

        <div
            class="admin-message
            <?php echo htmlspecialchars($message_type); ?>"
        >

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ADD / EDIT FOOD
    ====================================================== -->

    <section class="food-form-panel">

        <div class="panel-heading">

            <div>

                <p class="panel-label">
                    FOOD MANAGEMENT
                </p>

                <h2>

                    <?php
                    echo $edit_food
                        ? "Edit Food"
                        : "Add New Food";
                    ?>

                </h2>

            </div>


            <?php if ($edit_food): ?>

                <a
                    href="foods.php"
                    class="cancel-edit-btn"
                >
                    Cancel Edit
                </a>

            <?php endif; ?>

        </div>


        <form
            method="POST"
            action="foods.php"
            enctype="multipart/form-data"
            class="food-form"
        >

            <input
                type="hidden"
                name="food_id"
                value="<?php
                echo $edit_food
                    ? (int) $edit_food["id"]
                    : 0;
                ?>"
            >


            <div class="form-grid">


                <!-- ================= NAME ================= -->

                <div class="form-group">

                    <label for="name">
                        Food Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter food name"
                        value="<?php
                        echo htmlspecialchars(
                            $edit_food["name"] ?? ""
                        );
                        ?>"
                        required
                    >

                </div>


                <!-- ================= PRICE ================= -->

                <div class="form-group">

                    <label for="price">
                        Price
                    </label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0.01"
                        step="0.01"
                        placeholder="Enter price"
                        value="<?php
                        echo $edit_food
                            ? htmlspecialchars(
                                $edit_food["price"]
                            )
                            : "";
                        ?>"
                        required
                    >

                </div>


                <!-- ================= CATEGORY ================= -->

                <div class="form-group">

                    <label for="category_id">
                        Category
                    </label>

                    <select
                        id="category_id"
                        name="category_id"
                        required
                    >

                        <option value="">
                            Select category
                        </option>


                        <?php
                        while (
                            $category =
                            $categories_result->fetch_assoc()
                        ):
                        ?>

                            <option
                                value="<?php
                                echo (int)
                                    $category["id"];
                                ?>"
                                <?php
                                echo (
                                    $edit_food &&
                                    (int)
                                    $edit_food["category_id"]
                                    ===
                                    (int)
                                    $category["id"]
                                )
                                    ? "selected"
                                    : "";
                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $category["name"]
                                );
                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- ================= DESCRIPTION ================= -->

                <div class="form-group form-group-full">

                    <label for="description">
                        Food Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        maxlength="250"
                        placeholder="Enter a short food description"
                        required
                    ><?php
                    echo htmlspecialchars(
                        $edit_food["description"] ?? ""
                    );
                    ?></textarea>

                </div>


                <!-- ================= IMAGE ================= -->

                <div class="form-group">

                    <label for="image">
                        Food Image
                    </label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept=".png,.jpg,.jpeg,.webp"
                    >

                    <?php
                    if (
                        $edit_food &&
                        !empty(
                            $edit_food["image"]
                        )
                    ):
                    ?>

                        <small class="current-image">

                            Current:
                            <?php
                            echo htmlspecialchars(
                                $edit_food["image"]
                            );
                            ?>

                        </small>

                    <?php endif; ?>

                </div>


            </div>


            <button
                type="submit"
                name="save_food"
                value="1"
                class="save-btn"
            >

                <?php
                echo $edit_food
                    ? "Update Food"
                    : "Add Food";
                ?>

            </button>

        </form>

    </section>


    <!-- =====================================================
         ALL FOODS
    ====================================================== -->

    <section class="foods-panel">

        <div class="panel-heading">

            <div>

                <p class="panel-label">
                    FOOD LIST
                </p>

                <h2>
                    All Foods
                </h2>

            </div>

            <span class="food-count">

                <?php
                echo $foods_result->num_rows;
                ?>

                Foods

            </span>

        </div>


        <?php if ($foods_result->num_rows > 0): ?>

            <div class="foods-grid">


                <?php
                while (
                    $food =
                    $foods_result->fetch_assoc()
                ):
                ?>


                    <article class="food-card">


                        <!-- ================= IMAGE ================= -->

                        <div class="food-image">

                            <?php
                            if (
                                !empty(
                                    $food["image"]
                                )
                            ):
                            ?>

                                <img
                                    src="../images/<?php
                                    echo htmlspecialchars(
                                        basename(
                                            $food["image"]
                                        )
                                    );
                                    ?>"
                                    alt="<?php
                                    echo htmlspecialchars(
                                        $food["name"]
                                    );
                                    ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div class="no-image">
                                    No Image
                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- ================= CONTENT ================= -->

                        <div class="food-content">


                            <span
                                class="availability-badge
                                <?php
                                echo (
                                    (int)
                                    $food["is_available"] === 1
                                )
                                    ? "available"
                                    : "unavailable";
                                ?>"
                            >

                                <?php
                                echo (
                                    (int)
                                    $food["is_available"] === 1
                                )
                                    ? "Available"
                                    : "Unavailable";
                                ?>

                            </span>


                            <h3>

                                <?php
                                echo htmlspecialchars(
                                    $food["name"]
                                );
                                ?>

                            </h3>


                            <p class="food-category">

                                <?php
                                echo htmlspecialchars(
                                    $food["category_name"]
                                    ?? "Uncategorized"
                                );
                                ?>

                            </p>


                            <?php if (
                                !empty(
                                    $food["description"]
                                )
                            ): ?>

                                <p class="food-description">

                                    <?php
                                    echo htmlspecialchars(
                                        $food["description"]
                                    );
                                    ?>

                                </p>

                            <?php endif; ?>


                            <strong class="food-price">

                                ₹<?php
                                echo number_format(
                                    (float)
                                    $food["price"],
                                    2
                                );
                                ?>

                            </strong>


                            <!-- ================= ACTIONS ================= -->

                            <div class="food-actions">


                                <a
                                    href="foods.php?edit=<?php echo (int)$food["id"]; ?>"
                                    class="edit-btn"
                                >
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    action="foods.php"
                                    
                                >

                                    <input
                                        type="hidden"
                                        name="food_id"
                                        value="<?php
                                        echo (int)
                                            $food["id"];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_food"
                                        value="1"
                                        class="delete-btn"
                                    >
                                        Delete
                                    </button>

                                </form>


                            </div>


                        </div>


                    </article>


                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="empty-foods">

                <h2>
                    No Foods Found
                </h2>

                <p>
                    Add your first food item above.
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