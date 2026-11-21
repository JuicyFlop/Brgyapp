
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

// Only approved citizen accounts can access this page.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "citizen"
) {
    header("Location: ../auth/login.php");
    exit;
}

// Recheck account status on every dashboard visit.
$stmt = $pdo->prepare(
    "SELECT id, full_name, account_status
     FROM users
     WHERE id = :id
     AND role = 'citizen'
     LIMIT 1"
);

$stmt->execute([
    ":id" => $_SESSION["user_id"]
]);

$user = $stmt->fetch();

if (!$user || $user["account_status"] !== "approved") {

    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit;
}

$_SESSION["full_name"] = $user["full_name"];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Citizen Dashboard | Barangay DMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f7f5;
        }

        .topbar {
            background: #123d32;
        }

        .welcome-section {
            background: linear-gradient(135deg, #123d32, #245b4b);
            color: white;
            border-radius: 15px;
            padding: 35px;
        }

        .welcome-section h2 {
            font-weight: 700;
        }

        .card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
            transition: transform .2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .action-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            background: #e6f2ec;
            color: #164b3b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .btn-primary {
            background: #164b3b;
            border-color: #164b3b;
        }

        .btn-primary:hover {
            background: #245b4b;
            border-color: #245b4b;
        }

        .info-card {
            border-left: 5px solid #45a879;
        }

    </style>

</head>

<body>

<nav class="navbar navbar-dark topbar">

    <div class="container-fluid">

        <span class="navbar-brand fw-bold">
            Barangay Disaster Management System
        </span>

        <div class="text-white">

            <span class="me-2">
                <?= htmlspecialchars($user["full_name"]) ?>
            </span>

            <a href="../auth/logout.php"
               class="btn btn-sm btn-light">
                Logout
            </a>

        </div>

    </div>

</nav>

<div class="container py-4">

    <!-- WELCOME SECTION -->

    <div class="welcome-section mb-4">

        <h2>
            Welcome, <?= htmlspecialchars($user["full_name"]) ?>!
        </h2>

        <p class="mb-0">
            Stay informed and help keep your community safe.
            Access disaster reporting and emergency information here.
        </p>

    </div>

    <!-- DASHBOARD ACTIONS -->

    <h4 class="mb-3">
        Citizen Services
    </h4>

    <div class="row g-4">

        <!-- REPORT INCIDENT -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100">

                <div class="card-body p-4">

                    <div class="action-icon mb-3">
                        &#9888;
                    </div>

                    <h5 class="fw-bold">
                        Report an Incident
                    </h5>

                    <p class="text-muted">
                        Submit a disaster or emergency report
                        to the Barangay Disaster Management Office.
                    </p>

                   <a href="reports.php" class="btn btn-primary">
    Report an Incident
</a>

                </div>

            </div>

        </div>

        <!-- MY REPORTS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100">

                <div class="card-body p-4">

                    <div class="action-icon mb-3">
                        &#128196;
                    </div>

                    <h5 class="fw-bold">
                        My Reports
                    </h5>

                    <p class="text-muted">
                        Review the reports you have submitted
                        and check their current status.
                    </p>

                   <a href="my_reports.php" class="btn btn-outline-success">
    My Reports
</a>

                    </a>

                </div>

            </div>

        </div>

        <!-- ANNOUNCEMENTS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100">

                <div class="card-body p-4">

                    <div class="action-icon mb-3">
                        &#128226;
                    </div>

                    <h5 class="fw-bold">
                        Announcements
                    </h5>

                    <p class="text-muted">
                        Stay updated with important barangay
                        announcements and disaster advisories.
                    </p>

                    <a href="announcements.php"
                   <a href="announcements.php" class="btn btn-outline-success">
    View Announcements
</a>

                </div>

            </div>

        </div>

    </div>

    <!-- ACCOUNT INFORMATION -->

    <div class="card info-card mt-4">

        <div class="card-body p-4">

            <h5 class="fw-bold">
                Account Information
            </h5>

            <p class="mb-1">
                <strong>Full Name:</strong>
                <?= htmlspecialchars($user["full_name"]) ?>
            </p>

            <p class="mb-0">
                <strong>Account Status:</strong>
                <span class="badge bg-success">Approved</span>
            </p>

        </div>

    </div>

    <div class="text-center text-muted mt-4">

        <small>
            Barangay Disaster Management System
        </small>

    </div>

</div>

</body>
</html>
