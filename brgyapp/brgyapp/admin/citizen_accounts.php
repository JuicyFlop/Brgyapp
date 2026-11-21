
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

// Only administrators can manage citizen accounts.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";
$success = "";

// CSRF protection
if (empty($_SESSION["citizen_accounts_csrf"])) {
    $_SESSION["citizen_accounts_csrf"] = bin2hex(random_bytes(32));
}

// Handle account actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["citizen_accounts_csrf"],
            $token
        )
    ) {
        $error = "Invalid request. Please refresh the page.";

    } else {

        $account_id = filter_input(
            INPUT_POST,
            "account_id",
            FILTER_VALIDATE_INT
        );

        $action = $_POST["action"] ?? "";

        $allowed_actions = [
            "approve",
            "reject",
            "suspend"
        ];

        if (
            !$account_id ||
            !in_array($action, $allowed_actions, true)
        ) {
            $error = "Invalid account action.";

        } else {

            try {

                // Only citizen accounts can be managed here.
                $check = $pdo->prepare(
                    "SELECT id, account_status
                     FROM users
                     WHERE id = :id
                     AND role = 'citizen'
                     LIMIT 1"
                );

                $check->execute([
                    ":id" => $account_id
                ]);

                $account = $check->fetch();

                if (!$account) {

                    $error = "Citizen account not found.";

                } else {

                    if ($action === "approve") {

                        $stmt = $pdo->prepare(
                            "UPDATE users
                             SET account_status = 'approved',
                                 approved_by = :admin_id,
                                 approved_at = NOW()
                             WHERE id = :id
                             AND role = 'citizen'"
                        );

                        $stmt->execute([
                            ":admin_id" => $_SESSION["user_id"],
                            ":id" => $account_id
                        ]);

                        $success = "Citizen account approved successfully.";

                    } elseif ($action === "reject") {

                        $stmt = $pdo->prepare(
                            "UPDATE users
                             SET account_status = 'rejected',
                                 approved_by = NULL,
                                 approved_at = NULL
                             WHERE id = :id
                             AND role = 'citizen'"
                        );

                        $stmt->execute([
                            ":id" => $account_id
                        ]);

                        $success = "Citizen registration rejected.";

                    } elseif ($action === "suspend") {

                        $stmt = $pdo->prepare(
                            "UPDATE users
                             SET account_status = 'suspended'
                             WHERE id = :id
                             AND role = 'citizen'"
                        );

                        $stmt->execute([
                            ":id" => $account_id
                        ]);

                        $success = "Citizen account suspended.";
                    }

                    $_SESSION["citizen_accounts_csrf"] =
                        bin2hex(random_bytes(32));
                }

            } catch (PDOException $e) {

                error_log(
                    "Citizen account management error: " .
                    $e->getMessage()
                );

                $error = "Unable to process the account action.";
            }
        }
    }
}

// Account statistics
$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(account_status = 'pending') AS pending,
        SUM(account_status = 'approved') AS approved,
        SUM(account_status = 'rejected') AS rejected,
        SUM(account_status = 'suspended') AS suspended
     FROM users
     WHERE role = 'citizen'"
)->fetch();

// Get citizen accounts
$stmt = $pdo->query(
    "SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.contact_number,
        u.address,
        u.account_status,
        u.created_at,
        approver.full_name AS approved_by_name
     FROM users u
     LEFT JOIN users approver
        ON u.approved_by = approver.id
     WHERE u.role = 'citizen'
     ORDER BY
        CASE u.account_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
            WHEN 'suspended' THEN 4
        END,
        u.created_at DESC"
);

$citizens = $stmt->fetchAll();

function statusBadge($status) {

    $classes = [
        "pending" => "bg-warning text-dark",
        "approved" => "bg-success",
        "rejected" => "bg-danger",
        "suspended" => "bg-secondary"
    ];

    $class = $classes[$status] ?? "bg-secondary";

    return '<span class="badge ' . $class . '">' .
        htmlspecialchars(ucfirst($status)) .
        '</span>';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Citizen Accounts | Barangay DMS</title>

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

<nav class="navbar navbar-dark topbar">

    <div class="container-fluid">

        <span class="navbar-brand fw-bold">
            Barangay Disaster Management System
        </span>

        <div class="text-white">

            <?= htmlspecialchars($_SESSION["full_name"]) ?>

            <a href="../auth/logout.php" class="btn btn-sm btn-light ms-2">
                Logout
            </a>

        </div>

    </div>

</nav>

<div class="container-fluid">

    <div class="row">

        <!-- SIDEBAR -->

        <div class="col-md-3 col-lg-2 sidebar">

            <h5>ADMIN PANEL</h5>

            <ul class="nav flex-column">

                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a href="residents.php" class="nav-link">
                        Residents
                    </a>
                </li>

                <li class="nav-item">
                    <a href="households.php" class="nav-link">
                        Households
                    </a>
                </li>

                <li class="nav-item">
                    <a href="incidents.php" class="nav-link">
                        Disaster Incidents
                    </a>
                </li>

                <li class="nav-item">
                    <a href="evacuees.php" class="nav-link">
                        Evacuees
                    </a>
                </li>

                <li class="nav-item">
                    <a href="evacuation_centers.php" class="nav-link">
                        Evacuation Centers
                    </a>
                </li>

                <li class="nav-item">
                    <a href="resources.php" class="nav-link">
                        Resources
                    </a>
                </li>

                <li class="nav-item">
                    <a href="announcements.php" class="nav-link">
                        Announcements
                    </a>
                </li>

                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        Reports
                    </a>
                </li>

                <li class="nav-item">
                    <a href="citizen_reports.php" class="nav-link">
                        Citizen Reports
                    </a>
                </li>

                <li class="nav-item">
                    <a href="citizen_accounts.php" class="nav-link active">
                        Citizen Accounts
                    </a>
                </li>

            </ul>

        </div>

        <!-- MAIN CONTENT -->

        <div class="col-md-9 col-lg-10 p-4">

            <h2>Citizen Account Management</h2>

            <p class="text-muted">
                Review, approve, reject, and manage citizen registrations.
            </p>

            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>

            <!-- STATISTICS -->

            <div class="row g-3 mb-4">

                <div class="col-md-4 col-lg">
                    <div class="card p-3">
                        <small class="text-muted">Total Citizens</small>
                        <h3><?= (int) $stats["total"] ?></h3>
                    </div>
                </div>

                <div class="col-md-4 col-lg">
                    <div class="card p-3">
                        <small class="text-muted">Pending</small>
                        <h3><?= (int) $stats["pending"] ?></h3>
                    </div>
                </div>

                <div class="col-md-4 col-lg">
                    <div class="card p-3">
                        <small class="text-muted">Approved</small>
                        <h3><?= (int) $stats["approved"] ?></h3>
                    </div>
                </div>

                <div class="col-md-4 col-lg">
                    <div class="card p-3">
                        <small class="text-muted">Rejected</small>
                        <h3><?= (int) $stats["rejected"] ?></h3>
                    </div>
                </div>

                <div class="col-md-4 col-lg">
                    <div class="card p-3">
                        <small class="text-muted">Suspended</small>
                        <h3><?= (int) $stats["suspended"] ?></h3>
                    </div>
                </div>

            </div>

            <!-- CITIZEN TABLE -->

            <div class="card">

                <div class="card-body">

                    <h5 class="mb-3">
                        Registered Citizen Accounts
                    </h5>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead style="background: #164b3b; color: white;">

                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Date Registered</th>
                                    <th>Approved By</th>
                                    <th>Action</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($citizens)): ?>

                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            No citizen accounts registered yet.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($citizens as $citizen): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars($citizen["full_name"]) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($citizen["username"]) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($citizen["email"] ?? "") ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($citizen["contact_number"] ?? "") ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($citizen["address"] ?? "") ?>
                                            </td>

                                            <td>
                                                <?= statusBadge($citizen["account_status"]) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($citizen["created_at"]) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($citizen["approved_by_name"] ?? "-") ?>
                                            </td>

                                            <td class="text-nowrap">

                                                <?php if ($citizen["account_status"] === "pending"): ?>

                                                    <form method="POST" class="d-inline">

                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= htmlspecialchars($_SESSION["citizen_accounts_csrf"]) ?>">

                                                        <input type="hidden" name="account_id"
                                                            value="<?= (int) $citizen["id"] ?>">

                                                        <input type="hidden" name="action"
                                                            value="approve">

                                                        <button type="submit"
                                                            class="btn btn-success btn-sm"
                                                            onclick="return confirm('Approve this citizen account?')">

                                                            Approve

                                                        </button>

                                                    </form>

                                                    <form method="POST" class="d-inline">

                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= htmlspecialchars($_SESSION["citizen_accounts_csrf"]) ?>">

                                                        <input type="hidden" name="account_id"
                                                            value="<?= (int) $citizen["id"] ?>">

                                                        <input type="hidden" name="action"
                                                            value="reject">

                                                        <button type="submit"
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Reject this registration?')">

                                                            Reject

                                                        </button>

                                                    </form>

                                                <?php elseif ($citizen["account_status"] === "approved"): ?>

                                                    <form method="POST" class="d-inline">

                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= htmlspecialchars($_SESSION["citizen_accounts_csrf"]) ?>">

                                                        <input type="hidden" name="account_id"
                                                            value="<?= (int) $citizen["id"] ?>">

                                                        <input type="hidden" name="action"
                                                            value="suspend">

                                                        <button type="submit"
                                                            class="btn btn-warning btn-sm"
                                                            onclick="return confirm('Suspend this citizen account?')">

                                                            Suspend

                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        No available action
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
