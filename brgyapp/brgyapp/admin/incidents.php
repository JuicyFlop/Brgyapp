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
| DELETE INCIDENT
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    if ($id > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM incidents
                WHERE id = :id
            ");

            $stmt->execute([
                ":id" => $id
            ]);

            $success = "Incident deleted successfully.";

        } catch (PDOException $e) {

            $error = "Unable to delete incident.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD INCIDENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $incidentType = trim($_POST["incident_type"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $status = $_POST["status"] ?? "Reported";
    $incidentDate = $_POST["incident_date"] ?? "";

    $reportedBy = $_SESSION["full_name"] ?? "Unknown";


    /*
    |----------------------------------------------------------------------
    | VALIDATION
    |----------------------------------------------------------------------
    */

    $allowedStatuses = [
        "Reported",
        "Under Investigation",
        "Active",
        "Resolved"
    ];

    if (
        $incidentType === "" ||
        $location === "" ||
        $incidentDate === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!in_array($status, $allowedStatuses, true)) {

        $error = "Invalid incident status.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO incidents (
                    incident_type,
                    description,
                    location,
                    reported_by,
                    status,
                    incident_date
                )
                VALUES (
                    :incident_type,
                    :description,
                    :location,
                    :reported_by,
                    :status,
                    :incident_date
                )
            ");

            $stmt->execute([
                ":incident_type" => $incidentType,
                ":description" => $description,
                ":location" => $location,
                ":reported_by" => $reportedBy,
                ":status" => $status,
                ":incident_date" => $incidentDate
            ]);

            $success = "Incident reported successfully.";

        } catch (PDOException $e) {

            $error = "Unable to save incident.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH INCIDENTS
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $pdo->prepare("
        SELECT *
        FROM incidents
        WHERE incident_type LIKE :search
           OR location LIKE :search
           OR description LIKE :search
           OR reported_by LIKE :search
        ORDER BY incident_date DESC
    ");

    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $stmt = $pdo->query("
        SELECT *
        FROM incidents
        ORDER BY incident_date DESC
    ");
}

$incidents = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Disaster Incidents | Barangay DMS</title>

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

        <a href="incidents.php" class="nav-link active">
            Disaster Incidents
        </a>

        <a href="evacuees.php" class="nav-link">
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


        <li class="nav-item">
    <a class="nav-link" href="citizen_accounts.php">
        <i class="bi bi-person-check me-2"></i>
        Citizen Accounts
    </a>
</li>
    </div>

</aside>


        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <main class="col-md-10 p-4">


            <!-- Page Header -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Disaster Incidents
                    </h2>

                    <p class="text-muted mb-0">
                        Record and monitor barangay disaster incidents.
                    </p>

                </div>


                <button
                    class="btn btn-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#addIncidentModal"
                >
                    + Report Incident
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
                 SEARCH
            ================================================== -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <form
                        method="GET"
                        class="row g-2"
                    >

                        <div class="col-md-10">

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by disaster type, location, description..."
                                value="<?= htmlspecialchars($search) ?>"
                            >

                        </div>

                        <div class="col-md-2">

                            <button
                                type="submit"
                                class="btn btn-secondary w-100"
                            >
                                Search
                            </button>

                        </div>

                    </form>

                </div>

            </div>


            <!-- =================================================
                 INCIDENT TABLE
            ================================================== -->

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                           <thead style="background: #164b3b; color: white;">

                                <tr>

                                    <th>ID</th>

                                    <th>Type</th>

                                    <th>Location</th>

                                    <th>Date</th>

                                    <th>Reported By</th>

                                    <th>Status</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>


                            <?php if (count($incidents) > 0): ?>

                                <?php foreach ($incidents as $incident): ?>

                                    <tr>

                                        <td>
                                            <?= $incident["id"] ?>
                                        </td>


                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $incident["incident_type"]
                                                ) ?>
                                            </strong>

                                            <?php if (
                                                !empty($incident["description"])
                                            ): ?>

                                                <br>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars(
                                                        $incident["description"]
                                                    ) ?>

                                                </small>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $incident["location"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $incident["incident_date"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $incident["reported_by"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php

                                            $status = $incident["status"];

                                            $badgeClass = "bg-secondary";

                                            if ($status === "Reported") {
                                                $badgeClass = "bg-warning text-dark";
                                            }

                                            if ($status === "Under Investigation") {
                                                $badgeClass = "bg-info text-dark";
                                            }

                                            if ($status === "Active") {
                                                $badgeClass = "bg-danger";
                                            }

                                            if ($status === "Resolved") {
                                                $badgeClass = "bg-success";
                                            }

                                            ?>

                                            <span
                                                class="badge <?= $badgeClass ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $status
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                                href="?delete=<?= $incident["id"] ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete this incident?')"
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
                                            No incidents found
                                        </h5>

                                        <p class="mb-0">
                                            There are currently no disaster incidents recorded.
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
     ADD INCIDENT MODAL
========================================================= -->

<div
    class="modal fade"
    id="addIncidentModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <form method="POST">


                <div class="modal-header bg-danger text-white">

                    <h5 class="modal-title">
                        Report Disaster Incident
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">


                    <!-- Incident Type -->

                    <div class="mb-3">

                        <label class="form-label">
                            Disaster Type *
                        </label>

                        <select
                            name="incident_type"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Disaster Type --
                            </option>

                            <option value="Flood">
                                Flood
                            </option>

                            <option value="Fire">
                                Fire
                            </option>

                            <option value="Earthquake">
                                Earthquake
                            </option>

                            <option value="Landslide">
                                Landslide
                            </option>

                            <option value="Typhoon">
                                Typhoon
                            </option>

                            <option value="Storm Surge">
                                Storm Surge
                            </option>

                            <option value="Volcanic Eruption">
                                Volcanic Eruption
                            </option>

                            <option value="Other">
                                Other
                            </option>

                        </select>

                    </div>


                    <!-- Location -->

                    <div class="mb-3">

                        <label class="form-label">
                            Location *
                        </label>

                        <input
                            type="text"
                            name="location"
                            class="form-control"
                            placeholder="Example: Purok 2, Barangay Hall Area"
                            required
                        >

                    </div>


                    <!-- Incident Date -->

                    <div class="mb-3">

                        <label class="form-label">
                            Incident Date & Time *
                        </label>

                        <input
                            type="datetime-local"
                            name="incident_date"
                            class="form-control"
                            required
                        >

                    </div>


                    <!-- Status -->

                    <div class="mb-3">

                        <label class="form-label">
                            Status *
                        </label>

                        <select
                            name="status"
                            class="form-select"
                            required
                        >

                            <option value="Reported">
                                Reported
                            </option>

                            <option value="Under Investigation">
                                Under Investigation
                            </option>

                            <option value="Active">
                                Active
                            </option>

                            <option value="Resolved">
                                Resolved
                            </option>

                        </select>

                    </div>


                    <!-- Description -->

                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="4"
                            placeholder="Describe what happened, affected areas, damages, etc."
                        ></textarea>

                    </div>


                    <!-- Reported By -->

                    <div class="alert alert-info">

                        <strong>
                            Reported By:
                        </strong>

                        <?= htmlspecialchars(
                            $_SESSION["full_name"]
                        ) ?>

                        <br>

                        <small>
                            This is automatically recorded from the logged-in account.
                        </small>

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
                        Report Incident
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
