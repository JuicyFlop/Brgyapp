<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: resources.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET RESOURCE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM resources
    WHERE id = :id
");

$stmt->execute([
    ":id" => $id
]);

$resource = $stmt->fetch();

if (!$resource) {
    die("Resource not found.");
}


$error = "";


/*
|--------------------------------------------------------------------------
| UPDATE RESOURCE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $resourceName = trim($_POST["resource_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $quantity = (int) ($_POST["quantity"] ?? 0);
    $unit = trim($_POST["unit"] ?? "");
    $conditionStatus = $_POST["condition_status"] ?? "Good";

    $allowedConditions = [
        "Good",
        "Damaged",
        "Expired"
    ];

    if ($resourceName === "") {

        $error = "Resource name is required.";

    } elseif ($category === "") {

        $error = "Category is required.";

    } elseif ($quantity < 0) {

        $error = "Quantity cannot be negative.";

    } elseif (!in_array($conditionStatus, $allowedConditions, true)) {

        $error = "Invalid condition.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE resources
                SET
                    resource_name = :resource_name,
                    category = :category,
                    quantity = :quantity,
                    unit = :unit,
                    condition_status = :condition_status
                WHERE id = :id
            ");

            $stmt->execute([
                ":resource_name" => $resourceName,
                ":category" => $category,
                ":quantity" => $quantity,
                ":unit" => $unit,
                ":condition_status" => $conditionStatus,
                ":id" => $id
            ]);

            header("Location: resources.php");
            exit;

        } catch (PDOException $e) {

            $error = "Unable to update resource.";

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

    <title>Edit Resource | Barangay DMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
<style>

body {
    background: #f4f7f5;
}

.topbar {
    background: #123d32 !important;
}

.sidebar {
    min-height: 100vh;
    background: #123d32;
    padding: 22px 14px !important;
}

.sidebar h5 {
    color: #ffffff;
    font-weight: 700;
    letter-spacing: .5px;
    padding: 8px 12px;
}

.sidebar .nav-link {
    color: #d5e5df !important;
    padding: 12px 15px;
    margin: 3px 0;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    transition: all .25s ease;
    border-left: 4px solid transparent;
}

.sidebar .nav-link:hover {
    background: #245b4b;
    color: #ffffff !important;
    padding-left: 21px;
    border-left-color: #a8d5b5;
}

.sidebar .nav-link.active {
    background: #ffffff;
    color: #164b3b !important;
    font-weight: 700;
    border-left-color: #45a879;
    box-shadow: 0 3px 10px rgba(0,0,0,.12);
}

.card {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
}

.form-control,
.form-select {
    border-radius: 8px;
    padding: 10px 12px;
}

.form-control:focus,
.form-select:focus {
    border-color: #45a879;
    box-shadow: 0 0 0 .2rem rgba(69,168,121,.15);
}

.btn-primary {
    background: #164b3b;
    border-color: #164b3b;
}

.btn-primary:hover {
    background: #245b4b;
    border-color: #245b4b;
}

@media(max-width: 767px) {
    .sidebar {
        min-height: auto;
    }
}

</style>
</head>

<body>


<nav class="navbar navbar-dark topbar">

    <div class="container-fluid">

        <a
            href="dashboard.php"
            class="navbar-brand"
        >
            Barangay Disaster Management System
        </a>

        <div class="text-white">

            <?= htmlspecialchars($_SESSION["full_name"]) ?>

            |

            <a
                href="../auth/logout.php"
                class="text-white text-decoration-none"
            >
                Logout
            </a>

        </div>

    </div>

</nav>


<div class="container-fluid">

    <div class="row">


        
<aside class="col-md-2 sidebar">

    <h5 class="mb-4">
        BDMS Menu
    </h5>

    <div class="nav flex-column">

        <a href="dashboard.php" class="nav-link">
            Dashboard
        </a>

        <a href="residents.php" class="nav-link">
            Residents
        </a>

        <a href="households.php" class="nav-link">
            Households
        </a>

        <a href="incidents.php" class="nav-link">
            Disaster Incidents
        </a>

        <a href="evacuees.php" class="nav-link">
            Evacuees
        </a>

        <a href="evacuation_centers.php" class="nav-link">
            Evacuation Centers
        </a>

        <a href="resources.php" class="nav-link active">
            Resources
        </a>

        <a href="announcements.php" class="nav-link">
            Announcements
        </a>

        <a href="reports.php" class="nav-link">
            Reports
        </a>

        <a href="citizen_reports.php" class="nav-link">
            Citizen Reports
        </a>

    </div>

</aside>



        <main class="col-md-10 p-4">


            <div class="d-flex justify-content-between align-items-center mb-4">
<h2 class="fw-bold" style="color: #164b3b;">
    Edit Emergency Resource
</h2>

                <a
                    href="resources.php"
                    class="btn btn-secondary"
                >
                    Back
                </a>

            </div>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <form method="POST">


                        <div class="mb-3">

                            <label class="form-label">
                                Resource Name *
                            </label>

                            <input
                                type="text"
                                name="resource_name"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $resource["resource_name"]
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Category *
                            </label>

                            <select
                                name="category"
                                class="form-select"
                                required
                            >

                                <?php

                                $categories = [
                                    "Food",
                                    "Water",
                                    "Medicine",
                                    "Clothing",
                                    "Shelter",
                                    "Hygiene",
                                    "Equipment",
                                    "Other"
                                ];

                                ?>

                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?= htmlspecialchars($category) ?>"
                                        <?= $resource["category"] === $category
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= htmlspecialchars($category) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="row">

                            <div class="col-md-7">

                                <div class="mb-3">

                                    <label class="form-label">
                                        Quantity *
                                    </label>

                                    <input
                                        type="number"
                                        name="quantity"
                                        class="form-control"
                                        min="0"
                                        value="<?= $resource["quantity"] ?>"
                                        required
                                    >

                                </div>

                            </div>


                            <div class="col-md-5">

                                <div class="mb-3">

                                    <label class="form-label">
                                        Unit
                                    </label>

                                    <input
                                        type="text"
                                        name="unit"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $resource["unit"] ?? ""
                                        ) ?>"
                                    >

                                </div>

                            </div>

                        </div>


                        <div class="mb-4">

                            <label class="form-label">
                                Condition
                            </label>

                            <select
                                name="condition_status"
                                class="form-select"
                            >

                                <option
                                    value="Good"
                                    <?= $resource["condition_status"] === "Good"
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    Good
                                </option>

                                <option
                                    value="Damaged"
                                    <?= $resource["condition_status"] === "Damaged"
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    Damaged
                                </option>

                                <option
                                    value="Expired"
                                    <?= $resource["condition_status"] === "Expired"
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    Expired
                                </option>

                            </select>

                        </div>


                        <div class="d-flex gap-2">

                            <a
                                href="resources.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Update Resource
                            </button>

                        </div>


                    </form>

                </div>

            </div>

        </main>

    </div>

</div>

</body>

</html>
