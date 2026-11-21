<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: residents.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET RESIDENT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM residents
    WHERE id = :id
");

$stmt->execute([
    ":id" => $id
]);

$resident = $stmt->fetch();

if (!$resident) {
    die("Resident not found.");
}


/*
|--------------------------------------------------------------------------
| GET HOUSEHOLDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, household_head, address, purok
    FROM households
    ORDER BY household_head ASC
");

$households = $stmt->fetchAll();

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| UPDATE RESIDENT
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
                UPDATE residents
                SET
                    household_id = :household_id,
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    suffix = :suffix,
                    sex = :sex,
                    birth_date = :birth_date,
                    contact_number = :contact_number,
                    address = :address,
                    vulnerable = :vulnerable,
                    vulnerability_type = :vulnerability_type
                WHERE id = :id
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
                ":vulnerability_type" => $vulnerabilityType,
                ":id" => $id
            ]);

            header("Location: residents.php");
            exit;

        } catch (PDOException $e) {

            $error = "Unable to update resident.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Resident | Barangay DMS</title>

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
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
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

.form-check-input:checked {
    background-color: #164b3b;
    border-color: #164b3b;
}

.btn-primary {
    background: #164b3b;
    border-color: #164b3b;
}

.btn-primary:hover {
    background: #245b4b;
    border-color: #245b4b;
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

    </div>

</aside>



        <main class="col-md-10 p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <h2 class="fw-bold" style="color: #164b3b;">
    Edit Resident
</h2>
                <a
                    href="residents.php"
                    class="btn btn-secondary"
                >
                    Back to Residents
                </a>

            </div>


            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <form method="POST">

                        <!-- Household -->

                        <div class="mb-3">

                            <label class="form-label">
                                Household
                            </label>

                            <select
                                name="household_id"
                                class="form-select"
                            >

                                <option value="">
                                    -- No Household --
                                </option>

                                <?php foreach ($households as $household): ?>

                                    <option
                                        value="<?= $household["id"] ?>"
                                        <?= (
                                            $resident["household_id"]
                                            == $household["id"]
                                        )
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $household["household_head"]
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $household["purok"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    First Name *
                                </label>

                                <input
                                    type="text"
                                    name="first_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $resident["first_name"]
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Middle Name
                                </label>

                                <input
                                    type="text"
                                    name="middle_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $resident["middle_name"]
                                    ) ?>"
                                >

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Last Name *
                                </label>

                                <input
                                    type="text"
                                    name="last_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $resident["last_name"]
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Suffix
                                </label>

                                <input
                                    type="text"
                                    name="suffix"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $resident["suffix"]
                                    ) ?>"
                                >

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Sex *
                                </label>

                                <select
                                    name="sex"
                                    class="form-select"
                                    required
                                >

                                    <option value="Male"
                                        <?= $resident["sex"] === "Male"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >
                                        Male
                                    </option>

                                    <option value="Female"
                                        <?= $resident["sex"] === "Female"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >
                                        Female
                                    </option>

                                    <option value="Other"
                                        <?= $resident["sex"] === "Other"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >
                                        Other
                                    </option>

                                </select>

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Birth Date
                                </label>

                                <input
                                    type="date"
                                    name="birth_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $resident["birth_date"] ?? ""
                                    ) ?>"
                                >

                            </div>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Contact Number
                            </label>

                            <input
                                type="text"
                                name="contact_number"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $resident["contact_number"]
                                ) ?>"
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
                            ><?= htmlspecialchars(
                                $resident["address"]
                            ) ?></textarea>

                        </div>


                        <div class="form-check mb-3">

                            <input
                                type="checkbox"
                                name="vulnerable"
                                class="form-check-input"
                                id="vulnerable"
                                <?= $resident["vulnerable"]
                                    ? "checked"
                                    : ""
                                ?>
                            >

                            <label
                                class="form-check-label"
                                for="vulnerable"
                            >
                                Vulnerable resident
                            </label>

                        </div>


                        <div class="mb-4">

                            <label class="form-label">
                                Vulnerability Type
                            </label>

                            <input
                                type="text"
                                name="vulnerability_type"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $resident["vulnerability_type"]
                                ) ?>"
                                placeholder="Senior citizen, PWD, pregnant, etc."
                            >

                        </div>


                        <div class="d-flex gap-2">

                            <a
                                href="residents.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Update Resident
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </main>

    </div>

</div>

</body>

</html>
