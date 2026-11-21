
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Citizen access only.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "citizen"
) {
    header("Location: ../auth/login.php");
    exit;
}

// Verify that the citizen is still approved.
$stmt = $pdo->prepare("
    SELECT id, full_name, account_status
    FROM users
    WHERE id = ? AND role = 'citizen'
    LIMIT 1
");
$stmt->execute([$_SESSION["user_id"]]);
$citizen = $stmt->fetch();

if (!$citizen || $citizen["account_status"] !== "approved") {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Retrieve only this citizen's reports.
$stmt = $pdo->prepare("
    SELECT *
    FROM citizen_reports
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$citizen["id"]]);
$reports = $stmt->fetchAll();

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function statusClass($status) {
    return match ($status) {
        "Pending" => "secondary",
        "Under Review" => "info",
        "Verified" => "primary",
        "Responding" => "warning",
        "Resolved" => "success",
        "Rejected" => "danger",
        default => "secondary"
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Reports | Barangay Disaster Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f4f7f5;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #123d32;
        }

        .main-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        h3, h5 {
            color: #164b3b;
            font-weight: 700;
        }

        .report-code {
            color: #164b3b;
            font-weight: 700;
        }

        .table thead th {
            white-space: nowrap;
            background: #edf3ef;
            color: #164b3b;
        }

        .btn-primary {
            background: #164b3b;
            border-color: #164b3b;
        }

        .btn-primary:hover {
            background: #245b4b;
        }

        @media(max-width: 767px) {
            .table-responsive {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark topbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            Barangay Disaster Management
        </a>

        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            Dashboard
        </a>
    </div>
</nav>

<div class="container py-4">

    <div class="mb-4">
        <h3>My Incident Reports</h3>
        <p class="text-muted">
            View your submitted reports and monitor their progress.
        </p>
    </div>

    <div class="card main-card mb-4">
        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Welcome, <?= e($citizen["full_name"]) ?></h5>
                    <p class="text-muted mb-0">
                        Total submitted reports: <?= count($reports) ?>
                    </p>
                </div>

                <a href="reports.php" class="btn btn-primary">
                    + Report an Incident
                </a>
            </div>

        </div>
    </div>

    <div class="card main-card">
        <div class="card-body p-4">

            <h5 class="mb-3">Report History</h5>

            <?php if (empty($reports)): ?>

                <div class="alert alert-info mb-0">
                    You have not submitted any incident reports yet.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Reference Number</th>
                                <th>Incident Type</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($reports as $report): ?>

                            <tr>
                                <td class="report-code">
                                    <?= e($report["report_code"]) ?>
                                </td>

                                <td>
                                    <?= e($report["incident_type"]) ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        "M d, Y h:i A",
                                        strtotime($report["created_at"])
                                    )) ?>
                                </td>

                                <td>
                                    <span class="badge bg-<?= e(statusClass($report["status"])) ?>">
                                        <?= e($report["status"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#report<?= (int)$report["id"] ?>">
                                        View Details
                                    </button>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

                <?php foreach ($reports as $report): ?>

                    <div class="modal fade"
                         id="report<?= (int)$report["id"] ?>"
                         tabindex="-1"
                         aria-hidden="true">

                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        Incident Report Details
                                    </h5>

                                    <button type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal">
                                    </button>
                                </div>

                                <div class="modal-body">

                                    <p>
                                        <strong>Reference Number:</strong>
                                        <?= e($report["report_code"]) ?>
                                    </p>

                                    <p>
                                        <strong>Incident Type:</strong>
                                        <?= e($report["incident_type"]) ?>
                                    </p>

                                    <p>
                                        <strong>Date Submitted:</strong>
                                        <?= e($report["created_at"]) ?>
                                    </p>

                                    <p>
                                        <strong>Status:</strong>
                                        <span class="badge bg-<?= e(statusClass($report["status"])) ?>">
                                            <?= e($report["status"]) ?>
                                        </span>
                                    </p>

                                    <hr>

                                    <strong>Description:</strong>

                                    <p class="mt-2">
                                        <?= nl2br(e($report["description"])) ?>
                                    </p>

                                    <?php if (!empty($report["image_path"])): ?>
                                        <hr>
                                        <strong>Incident Photo:</strong>

                                        <div class="mt-2">
                                            <img
                                                src="../<?= e($report["image_path"]) ?>"
                                                alt="Incident photo"
                                                class="img-fluid rounded"
                                                style="max-height:350px;">
                                        </div>
                                    <?php endif; ?>

                                    <?php if (
                                        $report["latitude"] !== null &&
                                        $report["longitude"] !== null
                                    ): ?>
                                        <hr>
                                        <strong>Reported Location:</strong>

                                        <p class="small text-muted mt-2">
                                            Latitude: <?= e($report["latitude"]) ?><br>
                                            Longitude: <?= e($report["longitude"]) ?><br>
                                            Accuracy: <?= e($report["location_accuracy"]) ?> meters
                                        </p>

                                        <a
                                            href="https://www.openstreetmap.org/?mlat=<?= urlencode($report["latitude"]) ?>&mlon=<?= urlencode($report["longitude"]) ?>#map=17/<?= urlencode($report["latitude"]) ?>/<?= urlencode($report["longitude"]) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-sm btn-outline-success">
                                            View Location on Map
                                        </a>
                                    <?php endif; ?>

                                </div>

                                <div class="modal-footer">
                                    <button type="button"
                                            class="btn btn-secondary"
                                            data-bs-dismiss="modal">
                                        Close
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
