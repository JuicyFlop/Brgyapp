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
| MARK EVACUEE AS RETURNED
|--------------------------------------------------------------------------
*/

if (isset($_GET["return"])) {

    $id = (int) $_GET["return"];

    if ($id > 0) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT *
                FROM evacuees
                WHERE id = :id
                AND status = 'Evacuated'
                FOR UPDATE
            ");

            $stmt->execute([
                ":id" => $id
            ]);

            $evacuee = $stmt->fetch();

            if (!$evacuee) {

                $pdo->rollBack();

                $error = "Evacuee record not found.";

            } else {

                /*
                 * Mark resident as returned.
                 */
                $stmt = $pdo->prepare("
                    UPDATE evacuees
                    SET
                        status = 'Returned',
                        date_returned = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":id" => $id
                ]);


                /*
                 * Recalculate center occupancy.
                 */
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM evacuees
                    WHERE evacuation_center_id = :center_id
                    AND status = 'Evacuated'
                ");

                $stmt->execute([
                    ":center_id" =>
                        $evacuee["evacuation_center_id"]
                ]);

                $occupants = (int) $stmt->fetchColumn();


                /*
                 * Get center capacity.
                 */
                $stmt = $pdo->prepare("
                    SELECT capacity
                    FROM evacuation_centers
                    WHERE id = :id
                    FOR UPDATE
                ");

                $stmt->execute([
                    ":id" =>
                        $evacuee["evacuation_center_id"]
                ]);

                $center = $stmt->fetch();

                if ($center) {

                    $capacity = (int) $center["capacity"];

                    if ($occupants >= $capacity) {

                        $centerStatus = "Full";

                    } else {

                        $centerStatus = "Available";
                    }


                    $stmt = $pdo->prepare("
                        UPDATE evacuation_centers
                        SET
                            current_occupants = :occupants,
                            status = :status
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ":occupants" => $occupants,
                        ":status" => $centerStatus,
                        ":id" =>
                            $evacuee["evacuation_center_id"]
                    ]);
                }

                $pdo->commit();

                $success = "Evacuee successfully marked as returned.";
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Unable to process return.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| EVACUATE RESIDENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $residentId = (int) ($_POST["resident_id"] ?? 0);
    $centerId = (int) ($_POST["evacuation_center_id"] ?? 0);
    $dateEvacuated = $_POST["date_evacuated"] ?? "";


    if (
        $residentId <= 0 ||
        $centerId <= 0 ||
        $dateEvacuated === ""
    ) {

        $error = "Please complete all required fields.";

    } else {

        try {

            $pdo->beginTransaction();


            /*
             * Check resident.
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM residents
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([
                ":id" => $residentId
            ]);

            $resident = $stmt->fetch();

            if (!$resident) {

                throw new Exception(
                    "Resident not found."
                );
            }


            /*
             * Check if resident is already evacuated.
             */
            $stmt = $pdo->prepare("
                SELECT id
                FROM evacuees
                WHERE resident_id = :resident_id
                AND status = 'Evacuated'
                LIMIT 1
            ");

            $stmt->execute([
                ":resident_id" => $residentId
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    "This resident is already evacuated."
                );
            }


            /*
             * Lock the evacuation center.
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM evacuation_centers
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([
                ":id" => $centerId
            ]);

            $center = $stmt->fetch();

            if (!$center) {

                throw new Exception(
                    "Evacuation center not found."
                );
            }


            /*
             * Check center status.
             */
            if ($center["status"] === "Closed") {

                throw new Exception(
                    "This evacuation center is closed."
                );
            }


            /*
             * Calculate actual occupancy.
             */
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM evacuees
                WHERE evacuation_center_id = :center_id
                AND status = 'Evacuated'
            ");

            $stmt->execute([
                ":center_id" => $centerId
            ]);

            $occupants = (int) $stmt->fetchColumn();

            $capacity = (int) $center["capacity"];


            /*
             * Prevent over-capacity.
             */
            if ($occupants >= $capacity) {

                throw new Exception(
                    "This evacuation center is already full."
                );
            }


            /*
             * Add evacuee.
             */
            $stmt = $pdo->prepare("
                INSERT INTO evacuees (
                    resident_id,
                    evacuation_center_id,
                    date_evacuated,
                    status
                )
                VALUES (
                    :resident_id,
                    :center_id,
                    :date_evacuated,
                    'Evacuated'
                )
            ");

            $stmt->execute([
                ":resident_id" => $residentId,
                ":center_id" => $centerId,
                ":date_evacuated" => $dateEvacuated
            ]);


            /*
             * New occupancy.
             */
            $newOccupants = $occupants + 1;


            /*
             * Automatically update center status.
             */
            if ($newOccupants >= $capacity) {

                $centerStatus = "Full";

            } else {

                $centerStatus = "Available";
            }


            $stmt = $pdo->prepare("
                UPDATE evacuation_centers
                SET
                    current_occupants = :occupants,
                    status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ":occupants" => $newOccupants,
                ":status" => $centerStatus,
                ":id" => $centerId
            ]);


            $pdo->commit();

            $success =
                "Resident successfully recorded as evacuated.";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Unable to record evacuation.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET RESIDENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        first_name,
        middle_name,
        last_name,
        suffix,
        address
    FROM residents
    ORDER BY last_name, first_name
");

$residents = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET AVAILABLE CENTERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        location,
        capacity,
        current_occupants,
        status
    FROM evacuation_centers
    WHERE status != 'Closed'
    ORDER BY name
");

$centers = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET EVACUEES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.id,
        e.date_evacuated,
        e.date_returned,
        e.status,

        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix,
        r.address,

        ec.name AS center_name,
        ec.location AS center_location

    FROM evacuees e

    LEFT JOIN residents r
        ON e.resident_id = r.id

    INNER JOIN evacuation_centers ec
        ON e.evacuation_center_id = ec.id

    ORDER BY e.date_evacuated DESC
");

$evacuees = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalEvacuees = 0;
$activeEvacuees = 0;
$returnedEvacuees = 0;

foreach ($evacuees as $evacuee) {

    $totalEvacuees++;

    if ($evacuee["status"] === "Evacuated") {
        $activeEvacuees++;
    }

    if ($evacuee["status"] === "Returned") {
        $returnedEvacuees++;
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

    <title>Evacuees | Barangay DMS</title>

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


<!-- =========================================================
     NAVBAR
========================================================= -->

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


        <!-- =================================================
             SIDEBAR
        ================================================== -->

        
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

        <a href="evacuees.php" class="nav-link active">
            Evacuees
        </a>

        <a href="evacuation_centers.php" class="nav-link">
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



        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <main class="col-md-10 p-4">


            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Evacuees
                    </h2>

                    <p class="text-muted mb-0">
                        Monitor residents currently evacuated during disasters.
                    </p>

                </div>


             <button
    class="btn btn-danger"
    data-bs-toggle="modal"
    data-bs-target="#evacuateModal"
>
    + Evacuate Resident
</button>

            </div>


            <!-- =================================================
                 ALERTS
            ================================================== -->

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


            <!-- =================================================
                 STATISTICS
            ================================================== -->

            <div class="row g-4 mb-4">

                <div class="col-md-4">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Total Evacuation Records
                            </h6>

                            <h2>
                                <?= $totalEvacuees ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Currently Evacuated
                            </h6>

                            <h2 class="text-danger">
                                <?= $activeEvacuees ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Returned
                            </h6>

                            <h2 class="text-success">
                                <?= $returnedEvacuees ?>
                            </h2>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 EVACUEE TABLE
            ================================================== -->

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead class="table-dark">

                                <tr>

                                    <th>ID</th>

                                    <th>Resident</th>

                                    <th>Evacuation Center</th>

                                    <th>Date Evacuated</th>

                                    <th>Date Returned</th>

                                    <th>Status</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>


                            <?php if (count($evacuees) > 0): ?>

                                <?php foreach ($evacuees as $evacuee): ?>

                                    <tr>

                                        <td>
                                            <?= $evacuee["id"] ?>
                                        </td>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $evacuee["last_name"]
                                                    . ", "
                                                    . $evacuee["first_name"]
                                                    . " "
                                                    . ($evacuee["middle_name"] ?? "")
                                                ) ?>

                                            </strong>

                                            <br>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $evacuee["address"]
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $evacuee["center_name"]
                                                ) ?>

                                            </strong>

                                            <br>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $evacuee["center_location"]
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $evacuee["date_evacuated"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php if (
                                                $evacuee["date_returned"]
                                            ): ?>

                                                <?= htmlspecialchars(
                                                    $evacuee["date_returned"]
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?php if (
                                                $evacuee["status"]
                                                === "Evacuated"
                                            ): ?>

                                                <span class="badge bg-danger">
                                                    Evacuated
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-success">
                                                    Returned
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?php if (
                                                $evacuee["status"]
                                                === "Evacuated"
                                            ): ?>

                                                <a
                                                    href="?return=<?= $evacuee["id"] ?>"
                                                    class="btn btn-sm btn-success"
                                                    onclick="return confirm('Mark this resident as returned?')"
                                                >
                                                    Mark Returned
                                                </a>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    Completed
                                                </span>

                                            <?php endif; ?>

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
                                            No evacuee records.
                                        </h5>

                                        <p class="mb-0">
                                            No residents have been evacuated yet.
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


<!-- =========================================================
     EVACUATE RESIDENT MODAL
========================================================= -->

<div
    class="modal fade"
    id="evacuateModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <form method="POST">


                <div class="modal-header bg-danger text-white">

                    <h5 class="modal-title">
                        Evacuate Resident
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">


                    <!-- Resident -->

                    <div class="mb-3">

                        <label class="form-label">
                            Resident *
                        </label>

                        <select
                            name="resident_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Resident --
                            </option>

                            <?php foreach ($residents as $resident): ?>

                                <option
                                    value="<?= $resident["id"] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $resident["last_name"]
                                        . ", "
                                        . $resident["first_name"]
                                        . " "
                                        . ($resident["middle_name"] ?? "")
                                    ) ?>

                                    -
                                    <?= htmlspecialchars(
                                        $resident["address"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Evacuation Center -->

                    <div class="mb-3">

                        <label class="form-label">
                            Evacuation Center *
                        </label>

                        <select
                            name="evacuation_center_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Evacuation Center --
                            </option>

                            <?php foreach ($centers as $center): ?>

                                <?php

                                $occupants =
                                    (int) $center["current_occupants"];

                                $capacity =
                                    (int) $center["capacity"];

                                if ($occupants >= $capacity) {
                                    continue;
                                }

                                ?>

                                <option
                                    value="<?= $center["id"] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $center["name"]
                                    ) ?>

                                    -

                                    <?= htmlspecialchars(
                                        $center["location"]
                                    ) ?>

                                    (<?= $occupants ?>
                                    /
                                    <?= $capacity ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Date -->

                    <div class="mb-3">

                        <label class="form-label">
                            Date & Time Evacuated *
                        </label>

                        <input
                            type="datetime-local"
                            name="date_evacuated"
                            class="form-control"
                            value="<?= date('Y-m-d\TH:i') ?>"
                            required
                        >

                    </div>


                    <div class="alert alert-warning">

                        <strong>
                            Important:
                        </strong>

                        The system will automatically check the evacuation
                        center capacity before recording the evacuation.

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
                        class="btn btn-danger"
                    >
                        Confirm Evacuation
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
