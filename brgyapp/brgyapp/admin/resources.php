<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| DELETE RESOURCE
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    if ($id > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM resources
                WHERE id = :id
            ");

            $stmt->execute([
                ":id" => $id
            ]);

            $success = "Resource deleted successfully.";

        } catch (PDOException $e) {

            $error = "Unable to delete resource.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD RESOURCE
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

        $error = "Invalid condition status.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO resources (
                    resource_name,
                    category,
                    quantity,
                    unit,
                    condition_status
                )
                VALUES (
                    :resource_name,
                    :category,
                    :quantity,
                    :unit,
                    :condition_status
                )
            ");

            $stmt->execute([
                ":resource_name" => $resourceName,
                ":category" => $category,
                ":quantity" => $quantity,
                ":unit" => $unit,
                ":condition_status" => $conditionStatus
            ]);

            $success = "Emergency resource added successfully.";

        } catch (PDOException $e) {

            $error = "Unable to save emergency resource.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| GET RESOURCES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM resources
    ORDER BY category ASC, resource_name ASC
");

$resources = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalResources = count($resources);

$goodResources = 0;
$damagedResources = 0;
$expiredResources = 0;
$totalQuantity = 0;

foreach ($resources as $resource) {

    $totalQuantity += (int) $resource["quantity"];

    if ($resource["condition_status"] === "Good") {
        $goodResources++;
    }

    if ($resource["condition_status"] === "Damaged") {
        $damagedResources++;
    }

    if ($resource["condition_status"] === "Expired") {
        $expiredResources++;
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

    <title>Emergency Resources | Barangay DMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
<style>
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
    color: #fff;
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
    color: #fff !important;
    padding-left: 21px;
    border-left-color: #a8d5b5;
}

.sidebar .nav-link.active {
    background: #fff;
    color: #164b3b !important;
    font-weight: 700;
    border-left-color: #45a879;
    box-shadow: 0 3px 10px rgba(0,0,0,.12);
}

.card {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

h2, h4, h5 {
    color: #164b3b;
    font-weight: 700;
}

.btn-primary {
    background: #164b3b;
    border-color: #164b3b;
}

.btn-primary:hover {
    background: #245b4b;
    border-color: #245b4b;
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

.table thead th {
    white-space: nowrap;
}

@media(max-width: 767px) {
    .sidebar {
        min-height: auto;
    }
}
</style>
</style>
</head>

<body>


<!-- NAVBAR -->

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


        <!-- SIDEBAR -->

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

        <!-- MAIN CONTENT -->

        <main class="col-md-10 p-4">


            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Emergency Resources
                    </h2>

                    <p class="text-muted mb-0">
                        Manage emergency supplies and inventory.
                    </p>

                </div>


                <button
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addResourceModal"
                >
                    + Add Resource
                </button>

            </div>


            <!-- ALERTS -->

            <?php if ($success): ?>

                <div class="alert alert-success alert-dismissible fade show">

                    <?= htmlspecialchars($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-danger alert-dismissible fade show">

                    <?= htmlspecialchars($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- SUMMARY CARDS -->

            <div class="row g-4 mb-4">


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Resource Types
                            </h6>

                            <h2>
                                <?= $totalResources ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Good Condition
                            </h6>

                            <h2 class="text-success">
                                <?= $goodResources ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Damaged
                            </h6>

                            <h2 class="text-warning">
                                <?= $damagedResources ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Expired
                            </h6>

                            <h2 class="text-danger">
                                <?= $expiredResources ?>
                            </h2>

                        </div>

                    </div>

                </div>

            </div>


            <!-- RESOURCE TABLE -->

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead style="background: #164b3b; color: white;">

                                <tr>

                                    <th>ID</th>

                                    <th>Resource</th>

                                    <th>Category</th>

                                    <th>Quantity</th>

                                    <th>Unit</th>

                                    <th>Condition</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>


                            <?php if (count($resources) > 0): ?>

                                <?php foreach ($resources as $resource): ?>

                                    <tr>

                                        <td>
                                            <?= $resource["id"] ?>
                                        </td>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $resource["resource_name"]
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <span class="badge bg-secondary">

                                                <?= htmlspecialchars(
                                                    $resource["category"]
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?php
                                            $quantity =
                                                (int) $resource["quantity"];

                                            if ($quantity === 0) {
                                                $quantityClass = "text-danger";
                                            } elseif ($quantity <= 10) {
                                                $quantityClass = "text-warning";
                                            } else {
                                                $quantityClass = "text-success";
                                            }
                                            ?>

                                            <strong
                                                class="<?= $quantityClass ?>"
                                            >

                                                <?= $quantity ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $resource["unit"] ?? ""
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php

                                            if (
                                                $resource["condition_status"]
                                                === "Good"
                                            ) {

                                                $conditionClass =
                                                    "bg-success";

                                            } elseif (
                                                $resource["condition_status"]
                                                === "Damaged"
                                            ) {

                                                $conditionClass =
                                                    "bg-warning text-dark";

                                            } else {

                                                $conditionClass =
                                                    "bg-danger";
                                            }

                                            ?>

                                            <span
                                                class="badge <?= $conditionClass ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $resource["condition_status"]
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                                href="edit_resource.php?id=<?= $resource["id"] ?>"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Edit
                                            </a>

                                            <a
                                                href="?delete=<?= $resource["id"] ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete this resource?')"
                                            >
                                                Delete
                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>


                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        class="text-center text-muted py-5"
                                    >

                                        <h5>
                                            No resources found.
                                        </h5>

                                        <p class="mb-0">
                                            Add your first emergency resource.
                                        </p>

                                    </td>

                                </tr>

                            <?php endif; ?>


                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </main>

    </div>

</div>


<!-- ADD RESOURCE MODAL -->

<div
    class="modal fade"
    id="addResourceModal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">


            <form method="POST">


                <div class="modal-header" style="background: #164b3b; color: white;">

                    <h5 class="modal-title">
                        Add Emergency Resource
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">


                    <div class="mb-3">

                        <label class="form-label">
                            Resource Name *
                        </label>

                        <input
                            type="text"
                            name="resource_name"
                            class="form-control"
                            placeholder="Example: Food Packs"
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

                            <option value="">
                                -- Select Category --
                            </option>

                            <option value="Food">
                                Food
                            </option>

                            <option value="Water">
                                Water
                            </option>

                            <option value="Medicine">
                                Medicine
                            </option>

                            <option value="Clothing">
                                Clothing
                            </option>

                            <option value="Shelter">
                                Shelter
                            </option>

                            <option value="Hygiene">
                                Hygiene
                            </option>

                            <option value="Equipment">
                                Equipment
                            </option>

                            <option value="Other">
                                Other
                            </option>

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
                                    value="0"
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
                                    placeholder="pcs"
                                >

                            </div>

                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Condition
                        </label>

                        <select
                            name="condition_status"
                            class="form-select"
                        >

                            <option value="Good">
                                Good
                            </option>

                            <option value="Damaged">
                                Damaged
                            </option>

                            <option value="Expired">
                                Expired
                            </option>

                        </select>

                    </div>


                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Resource
                    </button>

                </div>


            </form>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
