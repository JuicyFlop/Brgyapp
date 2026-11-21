<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";


/*
|--------------------------------------------------------------------------
| GENERAL STATISTICS
|--------------------------------------------------------------------------
*/

$totalResidents = (int) $pdo->query("
    SELECT COUNT(*)
    FROM residents
")->fetchColumn();


$totalHouseholds = (int) $pdo->query("
    SELECT COUNT(*)
    FROM households
")->fetchColumn();


$vulnerableResidents = (int) $pdo->query("
    SELECT COUNT(*)
    FROM residents
    WHERE vulnerable = 1
")->fetchColumn();


$totalIncidents = (int) $pdo->query("
    SELECT COUNT(*)
    FROM incidents
")->fetchColumn();


$activeIncidents = (int) $pdo->query("
    SELECT COUNT(*)
    FROM incidents
    WHERE status = 'Active'
")->fetchColumn();


$totalEvacuees = (int) $pdo->query("
    SELECT COUNT(*)
    FROM evacuees
")->fetchColumn();


$currentEvacuees = (int) $pdo->query("
    SELECT COUNT(*)
    FROM evacuees
    WHERE status = 'Evacuated'
")->fetchColumn();


$totalCenters = (int) $pdo->query("
    SELECT COUNT(*)
    FROM evacuation_centers
")->fetchColumn();


$availableCenters = (int) $pdo->query("
    SELECT COUNT(*)
    FROM evacuation_centers
    WHERE status = 'Available'
")->fetchColumn();


$totalResources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM resources
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| INCIDENT REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        incident_type,
        COUNT(*) AS total
    FROM incidents
    GROUP BY incident_type
    ORDER BY total DESC
");

$incidentTypes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| INCIDENT STATUS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        status,
        COUNT(*) AS total
    FROM incidents
    GROUP BY status
    ORDER BY total DESC
");

$incidentStatuses = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| EVACUATION CENTER REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        name,
        location,
        capacity,
        current_occupants,
        status
    FROM evacuation_centers
    ORDER BY name ASC
");

$centers = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| VULNERABILITY REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        vulnerability_type,
        COUNT(*) AS total
    FROM residents
    WHERE vulnerable = 1
    GROUP BY vulnerability_type
    ORDER BY total DESC
");

$vulnerabilityTypes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RESOURCE REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        category,
        COUNT(*) AS total_items,
        SUM(quantity) AS total_quantity
    FROM resources
    GROUP BY category
    ORDER BY category ASC
");

$resourceCategories = $stmt->fetchAll();

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
        Reports | Barangay DMS
    </title>

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

.card-header {
    border-bottom: 1px solid #e8eeea;
    padding: 16px 20px;
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

.table thead th {
    white-space: nowrap;
}

.stat {
    border: 0;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.main-content {
    min-width: 0;
}

@media(max-width: 767px) {
    .sidebar {
        min-height: auto;
    }
}

/* PRINT SETTINGS */

@media print {

    body {
        background: #fff !important;
        font-size: 11px;
    }

    .no-print,
    .sidebar,
    .topbar {
        display: none !important;
    }

    .container-fluid,
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }

    .card-header {
        background: #f1f5f2 !important;
        color: #164b3b !important;
    }

    h2, h3, h4, h5 {
        color: #164b3b !important;
    }

    .table {
        font-size: 10px;
    }

    .table th,
    .table td {
        padding: 5px !important;
    }

    .text-muted {
        color: #555 !important;
    }

    @page {
        size: A4 landscape;
        margin: 12mm;
    }
}
    </style>

</head>


<body>


<!-- NAVBAR -->

<nav class="navbar navbar-dark topbar no-print">

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

        <aside class="col-md-2 sidebar no-print">

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

        <a href="resources.php" class="nav-link">
            Resources
        </a>

        <a href="announcements.php" class="nav-link">
            Announcements
        </a>

        <a href="reports.php" class="nav-link active">
            Reports
        </a>

        <a href="citizen_reports.php" class="nav-link">
            Citizen Reports
        </a>

    </div>

</aside>


        <!-- MAIN CONTENT -->

        <main class="col-md-10 p-4 main-content">


            <!-- REPORT HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-4">


                <div>

                    <h2 class="fw-bold mb-1">
                        Disaster Management Reports
                    </h2>

                    <p class="text-muted mb-0">

                        Barangay Disaster Management
                        System

                    </p>

                </div>


                <div class="no-print">

                    <button
                        onclick="window.print()"
                        class="btn btn-primary"
                    >
                        🖨 Print Report
                    </button>

                </div>


            </div>


            <!-- PRINT HEADER -->

            <div class="text-center mb-4">

                <h3 class="fw-bold">
                    BARANGAY DISASTER MANAGEMENT SYSTEM
                </h3>

                <p class="mb-0">
                    Disaster Management Statistical Report
                </p>

                <small class="text-muted">

                    Generated:

                    <?= date("F d, Y h:i A") ?>

                </small>

            </div>


            <!-- SUMMARY -->

            <div class="row g-4 mb-4">


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Residents
                            </small>

                            <h2 class="text-primary">
                                <?= $totalResidents ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Households
                            </small>

                            <h2 class="text-primary">
                                <?= $totalHouseholds ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Vulnerable Residents
                            </small>

                            <h2 class="text-danger">
                                <?= $vulnerableResidents ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Active Incidents
                            </small>

                            <h2 class="text-danger">
                                <?= $activeIncidents ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Total Incidents
                            </small>

                            <h2>
                                <?= $totalIncidents ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Current Evacuees
                            </small>

                            <h2 class="text-warning">
                                <?= $currentEvacuees ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Evacuation Centers
                            </small>

                            <h2 class="text-success">
                                <?= $totalCenters ?>
                            </h2>

                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <small class="text-muted">
                                Resources
                            </small>

                            <h2 class="text-success">
                                <?= $totalResources ?>
                            </h2>

                        </div>

                    </div>

                </div>

            </div>


            <!-- INCIDENT REPORT -->

            <div class="row g-4 mb-4">


                <div class="col-md-6">

                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">
                                Incidents by Type
                            </h5>

                        </div>


                        <div class="card-body">

                            <?php if (count($incidentTypes) > 0): ?>

                                <div class="table-responsive">

                                    <table class="table">

                                        <thead>

                                            <tr>

                                                <th>
                                                    Incident Type
                                                </th>

                                                <th class="text-end">
                                                    Total
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>

                                            <?php foreach (
                                                $incidentTypes
                                                as $incident
                                            ): ?>

                                                <tr>

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            $incident["incident_type"]
                                                        ) ?>
                                                    </td>

                                                    <td class="text-end">

                                                        <strong>
                                                            <?= $incident["total"] ?>
                                                        </strong>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>


                            <?php else: ?>

                                <p class="text-muted mb-0">
                                    No incident records found.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- INCIDENT STATUS -->

                <div class="col-md-6">

                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">
                                Incident Status
                            </h5>

                        </div>


                        <div class="card-body">

                            <?php if (count($incidentStatuses) > 0): ?>

                                <div class="table-responsive">

                                    <table class="table">

                                       <thead style="background: #164b3b; color: white;">

                                            <tr>

                                                <th>
                                                    Status
                                                </th>

                                                <th class="text-end">
                                                    Total
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>

                                            <?php foreach (
                                                $incidentStatuses
                                                as $incident
                                            ): ?>

                                                <tr>

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            $incident["status"]
                                                        ) ?>
                                                    </td>

                                                    <td class="text-end">

                                                        <strong>
                                                            <?= $incident["total"] ?>
                                                        </strong>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>


                            <?php else: ?>

                                <p class="text-muted mb-0">
                                    No incident records found.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- EVACUATION CENTERS -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">
                        Evacuation Center Occupancy
                    </h5>

                </div>


                <div class="card-body">

                    <?php if (count($centers) > 0): ?>

                        <div class="table-responsive">

                            <table class="table table-bordered">

                                <thead style="background: #164b3b; color: white;">
                                    <tr>

                                        <th>
                                            Center
                                        </th>

                                        <th>
                                            Location
                                        </th>

                                        <th>
                                            Capacity
                                        </th>

                                        <th>
                                            Occupants
                                        </th>

                                        <th>
                                            Remaining
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($centers as $center): ?>

                                        <?php

                                        $capacity =
                                            (int) $center["capacity"];

                                        $occupants =
                                            (int) $center["current_occupants"];

                                        $remaining =
                                            max(
                                                0,
                                                $capacity - $occupants
                                            );

                                        ?>

                                        <tr>

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
                                                <?= $occupants ?>
                                            </td>


                                            <td>

                                                <strong
                                                    class="<?= $remaining > 0
                                                        ? "text-success"
                                                        : "text-danger"
                                                    ?>"
                                                >

                                                    <?= $remaining ?>

                                                </strong>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $center["status"]
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>


                    <?php else: ?>

                        <p class="text-muted mb-0">
                            No evacuation centers found.
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <!-- VULNERABILITY REPORT -->

            <div class="row g-4 mb-4">


                <div class="col-md-6">

                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">
                                Vulnerable Residents
                            </h5>

                        </div>


                        <div class="card-body">

                            <?php if (count($vulnerabilityTypes) > 0): ?>

                                <table class="table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Vulnerability Type
                                            </th>

                                            <th class="text-end">
                                                Residents
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach (
                                            $vulnerabilityTypes
                                            as $vulnerability
                                        ): ?>

                                            <tr>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $vulnerability[
                                                            "vulnerability_type"
                                                        ]
                                                        ?: "Not specified"
                                                    ) ?>

                                                </td>


                                                <td class="text-end">

                                                    <strong>

                                                        <?= $vulnerability[
                                                            "total"
                                                        ] ?>

                                                    </strong>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>


                            <?php else: ?>

                                <p class="text-muted mb-0">

                                    No vulnerable residents
                                    recorded.

                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- RESOURCES -->

                <div class="col-md-6">

                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">
                                Emergency Resources
                            </h5>

                        </div>


                        <div class="card-body">

                            <?php if (count($resourceCategories) > 0): ?>

                                <table class="table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Category
                                            </th>

                                            <th>
                                                Items
                                            </th>

                                            <th class="text-end">
                                                Quantity
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach (
                                            $resourceCategories
                                            as $resource
                                        ): ?>

                                            <tr>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $resource["category"]
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= $resource[
                                                        "total_items"
                                                    ] ?>

                                                </td>


                                                <td class="text-end">

                                                    <strong>

                                                        <?= $resource[
                                                            "total_quantity"
                                                        ] ?? 0 ?>

                                                    </strong>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>


                            <?php else: ?>

                                <p class="text-muted mb-0">

                                    No emergency resources
                                    recorded.

                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- EVACUEE SUMMARY -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <h5>
                        Evacuation Summary
                    </h5>

                    <div class="row text-center mt-4">


                        <div class="col-md-4">

                            <h3 class="text-primary">
                                <?= $totalEvacuees ?>
                            </h3>

                            <p class="text-muted">
                                Total Evacuees Recorded
                            </p>

                        </div>


                        <div class="col-md-4">

                            <h3 class="text-warning">
                                <?= $currentEvacuees ?>
                            </h3>

                            <p class="text-muted">
                                Currently Evacuated
                            </p>

                        </div>


                        <div class="col-md-4">

                            <h3 class="text-success">

                                <?= max(
                                    0,
                                    $totalEvacuees - $currentEvacuees
                                ) ?>

                            </h3>

                            <p class="text-muted">
                                Returned Residents
                            </p>

                        </div>


                    </div>

                </div>

            </div>


            <!-- FOOTER -->

            <div class="text-center text-muted py-4">

                <small>

                    Barangay Disaster Management System

                    &bull;

                    Generated
                    <?= date("F d, Y h:i A") ?>

                </small>

            </div>


        </main>

    </div>

</div>

</body>

</html>
