<?php

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| GET PUBLISHED ANNOUNCEMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM announcements
    WHERE status = 'Published'
    ORDER BY
        CASE priority
            WHEN 'Emergency' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Normal' THEN 3
            WHEN 'Low' THEN 4
        END,
        created_at DESC
    LIMIT 10
");

$announcements = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET EVACUATION CENTERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM evacuation_centers
    WHERE status != 'Closed'
    ORDER BY name ASC
");

$evacuationCenters = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| COUNT ACTIVE INCIDENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM incidents
    WHERE status = 'Active'
");

$activeIncidents = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| COUNT AVAILABLE EVACUATION CENTERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM evacuation_centers
    WHERE status = 'Available'
");

$availableCenters = (int) $stmt->fetchColumn();

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
        Barangay Disaster Management System
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7fb;
        }

        .hero {
            background:
                linear-gradient(
                    rgba(13, 110, 253, .90),
                    rgba(0, 71, 171, .90)
                );
            color: white;
            padding: 80px 20px;
        }

        .emergency-card {
            border-left: 5px solid #dc3545;
        }

        .announcement-card {
            transition: .2s;
        }

        .announcement-card:hover {
            transform: translateY(-3px);
        }

        .stat-card {
            border: 0;
            box-shadow: 0 3px 15px rgba(0,0,0,.08);
        }

        footer {
            margin-top: 80px;
        }

    </style>

</head>


<body>


<!-- NAVBAR -->

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">

    <div class="container">

        <a
            class="navbar-brand fw-bold"
            href="index.php"
        >
            Barangay DMS
        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNav"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <div
            class="collapse navbar-collapse"
            id="mainNav"
        >

            <ul class="navbar-nav ms-auto">

                <li class="nav-item">

                    <a
                        class="nav-link active"
                        href="index.php"
                    >
                        Home
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="#announcements"
                    >
                        Announcements
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="#evacuation"
                    >
                        Evacuation Centers
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="#emergency"
                    >
                        Emergency Info
                    </a>

                </li>

                <li class="nav-item ms-lg-2">

                    <a
                        class="btn btn-light btn-sm mt-1"
                        href="auth/login.php"
                    >
                        Staff Login
                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>


<!-- HERO -->

<section class="hero">

    <div class="container text-center">

        <h1 class="display-4 fw-bold">

            Barangay Disaster
            Management System

        </h1>

        <p class="lead mt-3">

            Stay informed. Stay prepared.
            Stay safe.

        </p>

        <p class="mb-0">

            Official emergency information and
            disaster updates for our community.

        </p>

    </div>

</section>


<!-- STATISTICS -->

<div class="container">

    <div class="row g-4 mt-4">


        <div class="col-md-4">

            <div class="card stat-card">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        Active Incidents
                    </h6>

                    <h2 class="text-danger fw-bold">
                        <?= $activeIncidents ?>
                    </h2>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card stat-card">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        Available Evacuation Centers
                    </h6>

                    <h2 class="text-success fw-bold">
                        <?= $availableCenters ?>
                    </h2>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card stat-card">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        Emergency Hotline
                    </h6>

                    <h2 class="text-primary fw-bold">
                        911
                    </h2>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ANNOUNCEMENTS -->

<section
    id="announcements"
    class="container mt-5"
>

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold">
                Emergency Announcements
            </h2>

            <p class="text-muted">
                Latest official disaster information.
            </p>

        </div>

    </div>


    <?php if (count($announcements) > 0): ?>

        <div class="row g-4">

            <?php foreach ($announcements as $announcement): ?>

                <?php

                switch ($announcement["priority"]) {

                    case "Emergency":
                        $priorityClass = "danger";
                        break;

                    case "High":
                        $priorityClass = "warning";
                        break;

                    case "Low":
                        $priorityClass = "secondary";
                        break;

                    default:
                        $priorityClass = "primary";

                }

                ?>


                <div class="col-md-6">

                    <div
                        class="
                            card
                            announcement-card
                            h-100
                            shadow-sm
                            border-0
                            <?= $announcement["priority"] === "Emergency"
                                ? "emergency-card"
                                : ""
                            ?>
                        "
                    >

                        <div class="card-body">

                            <span
                                class="badge bg-<?= $priorityClass ?>"
                            >

                                <?= htmlspecialchars(
                                    $announcement["priority"]
                                ) ?>

                            </span>


                            <h4 class="mt-3">

                                <?= htmlspecialchars(
                                    $announcement["title"]
                                ) ?>

                            </h4>


                            <p class="text-muted">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $announcement["message"]
                                    )
                                ) ?>

                            </p>


                            <small class="text-muted">

                                Posted:

                                <?= htmlspecialchars(
                                    $announcement["created_at"]
                                ) ?>

                            </small>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="alert alert-info">

            There are currently no published emergency
            announcements.

        </div>

    <?php endif; ?>

</section>


<!-- EVACUATION CENTERS -->

<section
    id="evacuation"
    class="container mt-5"
>

    <div class="mb-4">

        <h2 class="fw-bold">
            Evacuation Centers
        </h2>

        <p class="text-muted">
            Available evacuation facilities in the barangay.
        </p>

    </div>


    <?php if (count($evacuationCenters) > 0): ?>

        <div class="row g-4">

            <?php foreach ($evacuationCenters as $center): ?>

                <?php

                $capacity =
                    (int) $center["capacity"];

                $occupants =
                    (int) $center["current_occupants"];

                $remaining =
                    max(0, $capacity - $occupants);

                if ($center["status"] === "Available") {

                    $statusClass = "success";

                } else {

                    $statusClass = "danger";

                }

                ?>


                <div class="col-md-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">


                            <div class="d-flex justify-content-between">

                                <h5>

                                    <?= htmlspecialchars(
                                        $center["name"]
                                    ) ?>

                                </h5>


                                <span
                                    class="badge bg-<?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        $center["status"]
                                    ) ?>

                                </span>

                            </div>


                            <p class="text-muted mb-2">

                                📍

                                <?= htmlspecialchars(
                                    $center["location"]
                                ) ?>

                            </p>


                            <hr>


                            <div class="row text-center">

                                <div class="col">

                                    <small class="text-muted">
                                        Capacity
                                    </small>

                                    <div class="fw-bold">
                                        <?= $capacity ?>
                                    </div>

                                </div>


                                <div class="col">

                                    <small class="text-muted">
                                        Occupants
                                    </small>

                                    <div class="fw-bold">
                                        <?= $occupants ?>
                                    </div>

                                </div>


                                <div class="col">

                                    <small class="text-muted">
                                        Available
                                    </small>

                                    <div class="fw-bold text-success">
                                        <?= $remaining ?>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="alert alert-warning">

            No evacuation centers are currently available.

        </div>

    <?php endif; ?>

</section>


<!-- EMERGENCY INFORMATION -->

<section
    id="emergency"
    class="container mt-5"
>

    <div class="card border-0 shadow-sm">

        <div class="card-body p-5">


            <h2 class="fw-bold mb-4">
                Emergency Information
            </h2>


            <div class="row g-4">


                <div class="col-md-4">

                    <div class="text-center">

                        <div class="display-5">
                            🚨
                        </div>

                        <h5 class="mt-3">
                            Emergency
                        </h5>

                        <p class="text-muted mb-1">
                            National Emergency Hotline
                        </p>

                        <h3 class="text-danger">
                            911
                        </h3>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="text-center">

                        <div class="display-5">
                            🏥
                        </div>

                        <h5 class="mt-3">
                            Medical Emergency
                        </h5>

                        <p class="text-muted">
                            Proceed to the nearest
                            hospital or medical facility.
                        </p>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="text-center">

                        <div class="display-5">
                            🏠
                        </div>

                        <h5 class="mt-3">
                            Evacuation
                        </h5>

                        <p class="text-muted">
                            Follow official evacuation
                            instructions and proceed to
                            the designated center.
                        </p>

                    </div>

                </div>


            </div>

        </div>

    </div>

</section>


<!-- FOOTER -->

<footer class="bg-dark text-white py-4">

    <div class="container text-center">

        <p class="mb-1">

            <strong>
                Barangay Disaster Management System
            </strong>

        </p>

        <small class="text-secondary">

            Emergency information and community
            disaster preparedness system.

        </small>

    </div>

</footer>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
