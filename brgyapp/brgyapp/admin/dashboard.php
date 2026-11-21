<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";


// Dashboard statistics
$residentCount = $pdo->query(
    "SELECT COUNT(*) FROM residents"
)->fetchColumn();

$evacueeCount = $pdo->query(
    "SELECT COUNT(*) FROM evacuees WHERE status = 'Evacuated'"
)->fetchColumn();

$incidentCount = $pdo->query(
    "SELECT COUNT(*) FROM incidents
     WHERE status IN ('Reported', 'Under Investigation', 'Active')"
)->fetchColumn();

$evacuationCenterCount = $pdo->query(
    "SELECT COUNT(*) FROM evacuation_centers
     WHERE status != 'Closed'"
)->fetchColumn();

$householdCount = $pdo->query(
    "SELECT COUNT(*) FROM households"
)->fetchColumn();
$resourceCount = $pdo->query(
    "SELECT COUNT(*) FROM resources"
)->fetchColumn();


?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | Barangay DMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
<style>

body {
    background: #f4f7f5;
    color: #26352f;
}

/* Top navbar */
.topbar {
    background: #123d32 !important;
    min-height: 65px;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}

.topbar .navbar-brand {
    color: #fff;
    font-weight: 700;
    letter-spacing: .3px;
}

/* Sidebar */
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

/* Dashboard statistics */
.stat-card {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
    transition: transform .2s ease, box-shadow .2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 7px 18px rgba(0,0,0,.10);
}

.welcome-card {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

@media(max-width: 767px) {
    .sidebar {
        min-height: auto;
    }
}

</style>
</head>

<body class="bg-light">

<nav class="navbar navbar-dark topbar">

    <div class="container-fluid">

        <span class="navbar-brand fw-bold">
            Barangay Disaster Management System
        </span>

        <div class="text-white">

            Welcome,
            <strong>
                <?= htmlspecialchars($_SESSION["full_name"]) ?>
            </strong>

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

        <!-- Sidebar -->

        
<aside class="col-md-2 sidebar">

    <h5 class="mb-4">BDMS MENU</h5>

    <div class="nav flex-column">

        <a href="dashboard.php" class="nav-link active">
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



        <!-- Main Content -->

        <main class="col-md-10 p-4">



<div class="row g-4">

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted">Total Residents</h6>
                <h2 class="fw-bold text-success">
                    <?= $residentCount ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted">Households</h6>
                <h2 class="fw-bold text-info">
                    <?= $householdCount ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted">Current Evacuees</h6>
                <h2 class="fw-bold text-warning">
                    <?= $evacueeCount ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted">Active Incidents</h6>
                <h2 class="fw-bold text-danger">
                    <?= $incidentCount ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted">Evacuation Centers</h6>
                <h2 class="fw-bold text-success">
                    <?= $evacuationCenterCount ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted">Resources</h6>
                <h2 class="fw-bold text-primary">
                    <?= $resourceCount ?>
                </h2>
            </div>
        </div>
    </div>

</div>


            <!-- Welcome Card -->

            <div class="card welcome-card mt-4">

                <div class="card-body">

                    <h4>
                        Welcome to the Barangay Disaster Management System
                    </h4>

                    <p class="text-muted mb-0">
                        Use the navigation menu to manage residents,
                        disaster incidents, evacuation centers,
                        emergency resources, announcements, and reports.
                    </p>

                </div>

            </div>

        </main>

    </div>

</div>

</body>

</html>
