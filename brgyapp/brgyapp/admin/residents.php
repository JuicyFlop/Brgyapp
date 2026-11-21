<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$householdStmt = $pdo->query("
    SELECT id, household_head, address, purok
    FROM households
    ORDER BY household_head ASC
");

$households = $householdStmt->fetchAll();


$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| DELETE RESIDENT
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    try {

        // 1. Get the household ID before deleting the resident
        $getResident = $pdo->prepare("
            SELECT household_id
            FROM residents
            WHERE id = :id
        ");

        $getResident->execute([
            ":id" => $id
        ]);

        $resident = $getResident->fetch();

        $householdId = $resident["household_id"] ?? null;


        // 2. Delete the resident
        $stmt = $pdo->prepare("
            DELETE FROM residents
            WHERE id = :id
        ");

        $stmt->execute([
            ":id" => $id
        ]);


        // 3. Recalculate the household member count
        if ($householdId !== null) {

            $updateHousehold = $pdo->prepare("
                UPDATE households
                SET number_of_members = (
                    SELECT COUNT(*)
                    FROM residents
                    WHERE household_id = :household_id
                )
                WHERE id = :household_id
            ");

            $updateHousehold->execute([
                ":household_id" => $householdId
            ]);
        }


        $success = "Resident deleted successfully.";

    } catch (PDOException $e) {

        $error = "Unable to delete resident.";

    }
}



/*
|--------------------------------------------------------------------------
| ADD RESIDENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$householdId = !empty($_POST["household_id"])
    ? (int) $_POST["household_id"]
    : null;

    $firstName = trim($_POST["first_name"] ?? "");
    $middleName = trim($_POST["middle_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $suffix = trim($_POST["suffix"] ?? "");
    $sex = $_POST["sex"] ?? "";
    $birthDate = $_POST["birth_date"] ?? null;
    $contactNumber = trim($_POST["contact_number"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $vulnerable = isset($_POST["vulnerable"]) ? 1 : 0;
    $vulnerabilityType = trim($_POST["vulnerability_type"] ?? "");

    if (
        $firstName === "" ||
        $lastName === "" ||
        $sex === "" ||
        $address === ""
    ) {

        $error = "Please fill in all required fields.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO residents (
                    household_id,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    sex,
                    birth_date,
                    contact_number,
                    address,
                    vulnerable,
                    vulnerability_type
                )
                VALUES (
                    :household_id,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :suffix,
                    :sex,
                    :birth_date,
                    :contact_number,
                    :address,
                    :vulnerable,
                    :vulnerability_type
                )
            ");

            $stmt->execute([
                ":household_id" => $householdId,
                ":first_name" => $firstName,
                ":middle_name" => $middleName,
                ":last_name" => $lastName,
                ":suffix" => $suffix,
                ":sex" => $sex,
                ":birth_date" => $birthDate ?: null,
                ":contact_number" => $contactNumber,
                ":address" => $address,
                ":vulnerable" => $vulnerable,
                ":vulnerability_type" => $vulnerabilityType
            ]);

           // Update household member count

if ($householdId !== null) {

    $updateHousehold = $pdo->prepare("
        UPDATE households
        SET number_of_members = (
            SELECT COUNT(*)
            FROM residents
            WHERE household_id = :household_id
        )
        WHERE id = :household_id
    ");

    $updateHousehold->execute([
        ":household_id" => $householdId
    ]);

}

$success = "Resident added successfully.";

        } catch (PDOException $e) {

            $error = "Unable to save resident.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH RESIDENTS
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        r.*,
        h.household_head,
        h.purok AS household_purok
    FROM residents r
    LEFT JOIN households h
        ON r.household_id = h.id
";

if ($search !== "") {

    $sql .= "
        WHERE r.first_name LIKE :search
           OR r.middle_name LIKE :search
           OR r.last_name LIKE :search
           OR r.address LIKE :search
           OR h.household_head LIKE :search
           OR h.purok LIKE :search
    ";

    $stmt = $pdo->prepare($sql . " ORDER BY r.id DESC");

    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $stmt = $pdo->query($sql . " ORDER BY r.id DESC");

}

$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Residents | Barangay DMS</title>

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

        <a href="residents.php" class="nav-link active">
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

            <div class="d-flex justify-content-between align-items-center mb-4">

                <h2>
                    Residents
                </h2>

                <button
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addResidentModal"
                >
                    + Add Resident
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


            <!-- Search -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <form method="GET" class="row g-2">

                        <div class="col-md-10">

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search resident..."
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


            <!-- Residents Table -->

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                           <thead style="background: #164b3b; color: white;">

                                <tr>

                                    <th>#</th>

                                    <th>Name</th>

                                    <th>Sex</th>

                                    <th>Birth Date</th>

                                    <th>Contact</th>

                                    <th>Address</th>

                                    <th>Vulnerable</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (count($residents) > 0): ?>

                                <?php foreach ($residents as $resident): ?>

                                    <tr>

                                        <td>
                                            <?= $resident["id"] ?>
                                        </td>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $resident["last_name"]
                                                ) ?>,

                                                <?= htmlspecialchars(
                                                    $resident["first_name"]
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $resident["middle_name"]
                                                ) ?>

                                            </strong>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $resident["sex"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $resident["birth_date"] ?? ""
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $resident["contact_number"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $resident["address"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <?php if ($resident["vulnerable"]): ?>

                                                <span class="badge bg-danger">
                                                    Yes
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-success">
                                                    No
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                     <td class="text-nowrap">

    <a
        href="edit_resident.php?id=<?= (int) $resident["id"] ?>"
        class="btn btn-sm btn-primary"
    >
        Edit
    </a>

    <a
        href="?delete=<?= (int) $resident["id"] ?>"
        class="btn btn-sm btn-danger"
        onclick="return confirm('Delete this resident?')"
    >
        Delete
    </a>

</td>


                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="8"
                                        class="text-center text-muted py-4"
                                    >
                                        No residents found.

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



<!-- Add Resident Modal -->

<div class="modal fade" id="addResidentModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Add Resident
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <!-- Household -->

                    <div class="mb-3">

                        <label class="form-label">
                            Choose Household
                        </label>

                        <select
                            name="household_id"
                            class="form-select">

                            <option value="">
                                -- No Household Assigned --
                            </option>

                            <?php foreach ($households as $household): ?>

                                <option value="<?= (int)$household["id"] ?>">

                                    <?= htmlspecialchars($household["household_head"]) ?>
                                    -
                                    <?= htmlspecialchars($household["purok"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Name -->

                    <div class="row">

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                First Name *
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                class="form-control"
                                required>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Middle Name
                            </label>

                            <input
                                type="text"
                                name="middle_name"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Last Name *
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                class="form-control"
                                required>

                        </div>

                    </div>

                    <!-- Personal Information -->

                    <div class="row">

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Suffix
                            </label>

                            <input
                                type="text"
                                name="suffix"
                                class="form-control"
                                placeholder="Jr., Sr., III">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Sex *
                            </label>

                            <select
                                name="sex"
                                class="form-select"
                                required>

                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Birth Date
                            </label>

                            <input
                                type="date"
                                name="birth_date"
                                class="form-control">

                        </div>

                    </div>

                    <!-- Contact -->

                    <div class="mb-3">

                        <label class="form-label">
                            Contact Number
                        </label>

                        <input
                            type="text"
                            name="contact_number"
                            class="form-control">

                    </div>

                    <!-- Address -->

                    <div class="mb-3">

                        <label class="form-label">
                            Address *
                        </label>

                        <textarea
                            name="address"
                            class="form-control"
                            rows="2"
                            required></textarea>

                    </div>

                    <!-- Vulnerability -->

                    <div class="form-check mb-3">

                        <input
                            type="checkbox"
                            name="vulnerable"
                            class="form-check-input"
                            id="vulnerable">

                        <label
                            class="form-check-label"
                            for="vulnerable">

                            Vulnerable resident

                        </label>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Vulnerability Type
                        </label>

                        <input
                            type="text"
                            name="vulnerability_type"
                            class="form-control"
                            placeholder="Senior citizen, PWD, pregnant, etc.">

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Save Resident

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
