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
| DELETE HOUSEHOLD
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    try {

        // Remove household association from residents first
        $stmt = $pdo->prepare(
            "UPDATE residents SET household_id = NULL
             WHERE household_id = :id"
        );

        $stmt->execute([
            ":id" => $id
        ]);

        // Delete household
        $stmt = $pdo->prepare(
            "DELETE FROM households WHERE id = :id"
        );

        $stmt->execute([
            ":id" => $id
        ]);

        $success = "Household deleted successfully.";

    } catch (PDOException $e) {

        $error = "Unable to delete household.";

    }
}


/*
|--------------------------------------------------------------------------
| ADD HOUSEHOLD
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $householdHead = trim($_POST["household_head"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $purok = trim($_POST["purok"] ?? "");

    if ($householdHead === "" || $address === "") {

        $error = "Please fill in all required fields.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO households (
                    household_head,
                    address,
                    purok,
                    number_of_members
                )
                VALUES (
                    :household_head,
                    :address,
                    :purok,
                    0
                )
            ");

            $stmt->execute([
                ":household_head" => $householdHead,
                ":address" => $address,
                ":purok" => $purok
            ]);

            $success = "Household added successfully.";

        } catch (PDOException $e) {

            $error = "Unable to save household.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| GET HOUSEHOLDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        h.*,
        COUNT(r.id) AS actual_members
    FROM households h
    LEFT JOIN residents r
        ON r.household_id = h.id
    GROUP BY h.id
    ORDER BY h.id DESC
");

$households = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Households | Barangay DMS</title>

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

        <!-- Sidebar -->

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

        <!-- Main Content -->

        <main class="col-md-10 p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

               <div>
    <h2 class="mb-1">Households</h2>
    <p class="text-muted mb-0">
        Manage registered household information and members.
    </p>
</div>

                <button
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addHouseholdModal"
                >
                    + Add Household
                </button>

            </div>


            <?php if ($success): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead style="background: #164b3b; color: white;">

                                <tr>

                                    <th>ID</th>

                                    <th>Household Head</th>

                                    <th>Address</th>

                                    <th>Purok</th>

                                    <th>Members</th>

                                    <th>Created</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (count($households) > 0): ?>

                                <?php foreach ($households as $household): ?>

                                    <tr>

                                        <td>
                                            <?= $household["id"] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $household["household_head"]
                                                ) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $household["address"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $household["purok"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="badge bg-primary">

                                                <?= $household["actual_members"] ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $household["created_at"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <a
                                                href="?delete=<?= $household["id"] ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete this household? Residents will not be deleted.')"
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
                                        class="text-center text-muted py-4"
                                    >
                                        No households found.

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


<!-- Add Household Modal -->

<div
    class="modal fade"
    id="addHouseholdModal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Add Household
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Household Head *
                        </label>

                        <input
                            type="text"
                            name="household_head"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Address *
                        </label>

                        <textarea
                            name="address"
                            class="form-control"
                            rows="3"
                            required
                        ></textarea>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Purok
                        </label>

                        <input
                            type="text"
                            name="purok"
                            class="form-control"
                            placeholder="Example: Purok 1"
                        >

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
                        Save Household
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
