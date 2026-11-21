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
| DELETE EVACUATION CENTER
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    if ($id > 0) {

        try {

            // Don't allow deletion if there are evacuees
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM evacuees
                WHERE evacuation_center_id = :id
                AND status = 'Evacuated'
            ");

            $stmt->execute([
                ":id" => $id
            ]);

            $occupants = (int) $stmt->fetchColumn();

            if ($occupants > 0) {

                $error = "This evacuation center cannot be deleted because it currently has evacuees.";

            } else {

                $stmt = $pdo->prepare("
                    DELETE FROM evacuation_centers
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":id" => $id
                ]);

                $success = "Evacuation center deleted successfully.";
            }

        } catch (PDOException $e) {

            $error = "Unable to delete evacuation center.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD EVACUATION CENTER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $capacity = (int) ($_POST["capacity"] ?? 0);
    $status = $_POST["status"] ?? "Available";

    $allowedStatuses = [
        "Available",
        "Full",
        "Closed"
    ];

    if ($name === "" || $location === "") {

        $error = "Please enter the center name and location.";

    } elseif ($capacity < 1) {

        $error = "Capacity must be at least 1.";

    } elseif (!in_array($status, $allowedStatuses, true)) {

        $error = "Invalid evacuation center status.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO evacuation_centers (
                    name,
                    location,
                    capacity,
                    current_occupants,
                    status
                )
                VALUES (
                    :name,
                    :location,
                    :capacity,
                    0,
                    :status
                )
            ");

            $stmt->execute([
                ":name" => $name,
                ":location" => $location,
                ":capacity" => $capacity,
                ":status" => $status
            ]);

            $success = "Evacuation center added successfully.";

        } catch (PDOException $e) {

            $error = "Unable to save evacuation center.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| GET EVACUATION CENTERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        ec.*,
        (
            SELECT COUNT(*)
            FROM evacuees e
            WHERE e.evacuation_center_id = ec.id
            AND e.status = 'Evacuated'
        ) AS actual_occupants
    FROM evacuation_centers ec
    ORDER BY ec.name ASC
");

$centers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Evacuation Centers | Barangay DMS</title>

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
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.stat {
    border: 0;
    box-shadow: 0 .125rem .5rem rgba(0,0,0,.08);
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

        <a href="evacuation_centers.php" class="nav-link active">
            Evacuation Centers
        </a>

        <a href="resources.php" class="nav-link">
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



        <!-- MAIN -->

        <main class="col-md-10 p-4">


            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                   <h2 class="mb-1">
    Evacuation Centers
</h2>
                    <p class="text-muted mb-0">
                        Manage barangay evacuation facilities and capacity.
                    </p>

                </div>

                <button
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addCenterModal"
                >
                    + Add Evacuation Center
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

            <?php

            $totalCenters = count($centers);

            $availableCenters = 0;
            $fullCenters = 0;
            $totalCapacity = 0;
            $totalOccupants = 0;

            foreach ($centers as $center) {

                $occupants = (int) $center["actual_occupants"];

                $totalCapacity += (int) $center["capacity"];

                $totalOccupants += $occupants;

                if ($center["status"] === "Available") {
                    $availableCenters++;
                }

                if ($center["status"] === "Full") {
                    $fullCenters++;
                }
            }

            ?>

            <div class="row g-4 mb-4">

                <div class="col-md-3">

                    <div class="card shadow-sm border-0">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Total Centers
                            </h6>

                            <h2>
                                <?= $totalCenters ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card shadow-sm border-0">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Available
                            </h6>

                            <h2 class="text-success">
                                <?= $availableCenters ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card shadow-sm border-0">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Full
                            </h6>

                            <h2 class="text-danger">
                                <?= $fullCenters ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card shadow-sm border-0">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Occupancy
                            </h6>

                            <h2>
                                <?= $totalOccupants ?>
                                /
                                <?= $totalCapacity ?>
                            </h2>

                        </div>

                    </div>

                </div>

            </div>


            <!-- TABLE -->

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead class="table-dark">

                                <tr>

                                    <th>ID</th>

                                    <th>Name</th>

                                    <th>Location</th>

                                    <th>Capacity</th>

                                    <th>Occupants</th>

                                    <th>Status</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>


                            <?php if (count($centers) > 0): ?>

                                <?php foreach ($centers as $center): ?>

                                    <?php

                                    $occupants =
                                        (int) $center["actual_occupants"];

                                    $capacity =
                                        (int) $center["capacity"];

                                    $percentage =
                                        $capacity > 0
                                            ? round(
                                                ($occupants / $capacity) * 100
                                            )
                                            : 0;

                                    if ($occupants >= $capacity) {

                                        $occupancyClass = "bg-danger";

                                    } elseif ($percentage >= 80) {

                                        $occupancyClass = "bg-warning text-dark";

                                    } else {

                                        $occupancyClass = "bg-success";
                                    }

                                    ?>


                                    <tr>

                                        <td>
                                            <?= $center["id"] ?>
                                        </td>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $center["name"]
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $center["location"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= $capacity ?>

                                        </td>


                                        <td>

                                            <span
                                                class="badge <?= $occupancyClass ?>"
                                            >

                                                <?= $occupants ?>

                                                /

                                                <?= $capacity ?>

                                            </span>

                                            <small class="text-muted">
                                                (<?= $percentage ?>%)
                                            </small>

                                        </td>


                                        <td>

                                            <?php

                                            $statusClass =
                                                "bg-secondary";

                                            if (
                                                $center["status"]
                                                === "Available"
                                            ) {
                                                $statusClass =
                                                    "bg-success";
                                            }

                                            if (
                                                $center["status"]
                                                === "Full"
                                            ) {
                                                $statusClass =
                                                    "bg-danger";
                                            }

                                            if (
                                                $center["status"]
                                                === "Closed"
                                            ) {
                                                $statusClass =
                                                    "bg-dark";
                                            }

                                            ?>

                                            <span
                                                class="badge <?= $statusClass ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $center["status"]
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                               href="edit_evacuation_center.php?id=<?= $center["id"] ?>"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Edit
                                            </a>

                                            <a
                                                href="?delete=<?= $center["id"] ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete this evacuation center?')"
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
                                            No evacuation centers found.
                                        </h5>

                                        <p class="mb-0">
                                            Add your first evacuation center.
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


<!-- ADD CENTER MODAL -->

<div
    class="modal fade"
    id="addCenterModal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <form method="POST">


                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title">
                        Add Evacuation Center
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
                            Center Name *
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            placeholder="Example: Barangay Covered Court"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Location *
                        </label>

                        <input
                            type="text"
                            name="location"
                            class="form-control"
                            placeholder="Example: Purok 1"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Capacity *
                        </label>

                        <input
                            type="number"
                            name="capacity"
                            class="form-control"
                            min="1"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option value="Available">
                                Available
                            </option>

                            <option value="Full">
                                Full
                            </option>

                            <option value="Closed">
                                Closed
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
                        Save Center
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
