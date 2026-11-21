
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Only approved citizens can access announcements.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "citizen"
) {
    header("Location: ../auth/login.php");
    exit;
}

// Verify citizen account.
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

// Escape output safely.
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Priority styling.
function priorityClass($priority) {
    return match ($priority) {
        "Critical" => "danger",
        "High" => "warning",
        "Normal" => "primary",
        "Low" => "secondary",
        default => "secondary"
    };
}

// Retrieve published announcements only.
$stmt = $pdo->query("
    SELECT
        id,
        title,
        content,
        category,
        priority,
        published_at,
        created_at
    FROM announcements
    WHERE status = 'Published'
    ORDER BY
        CASE priority
            WHEN 'Critical' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Normal' THEN 3
            WHEN 'Low' THEN 4
            ELSE 5
        END,
        COALESCE(published_at, created_at) DESC
");

$announcements = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Barangay Announcements</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        body {
            background: #f4f7f5;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #123d32;
        }

        .main-title {
            color: #164b3b;
            font-weight: 700;
        }

        .announcement-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
            transition: transform .2s ease;
        }

        .announcement-card:hover {
            transform: translateY(-3px);
        }

        .announcement-content {
            white-space: pre-line;
            line-height: 1.7;
            color: #444;
        }

        .priority-critical {
            border-left: 6px solid #dc3545;
        }

        .priority-high {
            border-left: 6px solid #ffc107;
        }

        .priority-normal {
            border-left: 6px solid #0d6efd;
        }

        .priority-low {
            border-left: 6px solid #6c757d;
        }

        .empty-state {
            background: #fff;
            border-radius: 14px;
            padding: 45px 20px;
            text-align: center;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .btn-primary {
            background: #164b3b;
            border-color: #164b3b;
        }

        .btn-primary:hover {
            background: #245b4b;
            border-color: #245b4b;
        }

        .category-label {
            color: #164b3b;
            font-weight: 600;
        }

        @media(max-width: 576px) {

            .page-heading {
                font-size: 23px;
            }

            .announcement-card .card-body {
                padding: 18px !important;
            }

        }

    </style>

</head>

<body>

<!-- NAVIGATION -->

<nav class="navbar navbar-dark topbar">

    <div class="container">

        <a class="navbar-brand fw-bold"
           href="dashboard.php">

            <i class="bi bi-shield-check me-2"></i>
            Barangay Disaster Management

        </a>

        <a href="dashboard.php"
           class="btn btn-outline-light btn-sm">

            <i class="bi bi-house me-1"></i>
            Dashboard

        </a>

    </div>

</nav>


<!-- MAIN CONTENT -->

<div class="container py-4">

    <div class="mb-4">

        <h3 class="main-title page-heading">

            <i class="bi bi-megaphone-fill me-2"></i>
            Barangay Announcements

        </h3>

        <p class="text-muted">

            Stay informed about barangay advisories,
            emergency warnings, and community updates.

        </p>

    </div>


    <!-- CITIZEN WELCOME -->

    <div class="alert alert-success">

        <i class="bi bi-person-circle me-2"></i>

        Welcome,
        <strong><?= e($citizen["full_name"]) ?></strong>!

        Please check this page regularly for important
        barangay announcements.

    </div>


    <!-- ANNOUNCEMENTS -->

    <?php if (empty($announcements)): ?>

        <div class="empty-state">

            <i class="bi bi-megaphone display-3 text-muted"></i>

            <h5 class="mt-3 fw-bold">
                No Announcements Yet
            </h5>

            <p class="text-muted mb-0">

                There are currently no published announcements.
                Please check again later.

            </p>

        </div>

    <?php else: ?>

        <div class="d-flex justify-content-between
                    align-items-center mb-3">

            <h5 class="main-title mb-0">
                Latest Advisories
            </h5>

            <span class="badge bg-success">

                <?= count($announcements) ?>
                Published

            </span>

        </div>


        <?php foreach ($announcements as $announcement): ?>

            <?php

                $priority = $announcement["priority"];

                $priorityStyle = strtolower($priority);

                $publishedDate = $announcement["published_at"]
                    ?: $announcement["created_at"];

            ?>

            <div class="card announcement-card priority-<?= e($priorityStyle) ?> mb-4">

                <div class="card-body p-4">

                    <!-- CATEGORY AND PRIORITY -->

                    <div class="d-flex flex-wrap
                                justify-content-between
                                align-items-center gap-2 mb-3">

                        <span class="category-label">

                            <i class="bi bi-tag-fill me-1"></i>

                            <?= e($announcement["category"]) ?>

                        </span>

                        <span class="badge bg-<?= e(priorityClass($priority)) ?>">

                            <?= e($priority) ?> Priority

                        </span>

                    </div>


                    <!-- TITLE -->

                    <h5 class="fw-bold main-title mb-3">

                        <?= e($announcement["title"]) ?>

                    </h5>


                    <!-- CONTENT -->

                    <div class="announcement-content mb-3">

                        <?= e($announcement["content"]) ?>

                    </div>


                    <hr>


                    <!-- PUBLICATION DATE -->

                    <div class="d-flex flex-wrap
                                justify-content-between
                                align-items-center">

                        <small class="text-muted">

                            <i class="bi bi-calendar-event me-1"></i>

                            Published:

                            <?= e(date(
                                "F d, Y h:i A",
                                strtotime($publishedDate)
                            )) ?>

                        </small>

                        <small class="text-success fw-semibold">

                            <i class="bi bi-check-circle-fill me-1"></i>

                            Official Announcement

                        </small>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


    <!-- BACK BUTTON -->

    <div class="mt-4">

        <a href="dashboard.php"
           class="btn btn-outline-success">

            <i class="bi bi-arrow-left me-2"></i>
            Back to Dashboard

        </a>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>
