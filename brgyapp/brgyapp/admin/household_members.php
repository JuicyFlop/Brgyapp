<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$householdId = (int) ($_GET["id"] ?? 0);

if ($householdId <= 0) {
    header("Location: households.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| HOUSEHOLD
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM households
    WHERE id = :id
");

$stmt->execute([
    ":id" => $householdId
]);

$household = $stmt->fetch();

if (!$household) {
    die("Household not found.");
}


/*
|--------------------------------------------------------------------------
| MEMBERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM residents
    WHERE household_id = :household_id
    ORDER BY last_name, first_name
");

$stmt->execute([
    ":household_id" => $householdId
]);

$members = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Household Members | Barangay DMS</title>

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

        <a href="households.php" class="nav-link active">
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

    </div>

</aside>


        <main class="col-md-10 p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                  <h2 class="mb-1">
    Household Members
</h2>

                    <p class="text-muted mb-0">

                        <strong>
                            <?= htmlspecialchars(
                                $household["household_head"]
                            ) ?>
                        </strong>

                        -

                        <?= htmlspecialchars(
                            $household["purok"]
                        ) ?>

                    </p>

                </div>

                <a
                    href="households.php"
                    class="btn btn-secondary"
                >
                    Back to Households
                </a>

            </div>


            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover">

                           <thead style="background: #164b3b; color: white;">

                                <tr>

                                    <th>#</th>

                                    <th>Name</th>

                                    <th>Sex</th>

                                    <th>Birth Date</th>

                                    <th>Contact</th>

                                    <th>Vulnerable</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (count($members) > 0): ?>

                                <?php foreach ($members as $member): ?>

                                    <tr>

                                        <td>
                                            <?= $member["id"] ?>
                                        </td>

                                        <td>

                                            <?= htmlspecialchars(
                                                $member["last_name"]
                                            ) ?>,

                                            <?= htmlspecialchars(
                                                $member["first_name"]
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $member["middle_name"]
                                            ) ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $member["sex"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $member["birth_date"] ?? ""
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $member["contact_number"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <?php if ($member["vulnerable"]): ?>

                                                <span class="badge bg-danger">
                                                    <?= htmlspecialchars(
                                                        $member["vulnerability_type"]
                                                    ) ?>
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-success">
                                                    No
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="6"
                                        class="text-center text-muted py-4"
                                    >
                                        No members assigned to this household.

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

</body>

</html>
