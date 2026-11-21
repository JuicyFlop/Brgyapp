<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: announcements.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET ANNOUNCEMENT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM announcements
    WHERE id = :id
");

$stmt->execute([
    ":id" => $id
]);

$announcement = $stmt->fetch();

if (!$announcement) {
    die("Announcement not found.");
}

$error = "";


/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");
    $priority = $_POST["priority"] ?? "Normal";
    $status = $_POST["status"] ?? "Draft";

    $allowedPriorities = [
        "Low",
        "Normal",
        "High",
        "Emergency"
    ];

    $allowedStatuses = [
        "Draft",
        "Published"
    ];

    if ($title === "") {

        $error = "Title is required.";

    } elseif ($message === "") {

        $error = "Message is required.";

    } elseif (!in_array($priority, $allowedPriorities, true)) {

        $error = "Invalid priority.";

    } elseif (!in_array($status, $allowedStatuses, true)) {

        $error = "Invalid status.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE announcements
                SET
                    title = :title,
                    message = :message,
                    priority = :priority,
                    status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ":title" => $title,
                ":message" => $message,
                ":priority" => $priority,
                ":status" => $status,
                ":id" => $id
            ]);

            header("Location: announcements.php");
            exit;

        } catch (PDOException $e) {

            $error = "Unable to update announcement.";

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

    <title>Edit Announcement | Barangay DMS</title>

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

.btn-primary {
    background: #164b3b;
    border-color: #164b3b;
}

.btn-primary:hover {
    background: #245b4b;
    border-color: #245b4b;
}

.btn-secondary {
    border-radius: 8px;
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

        <a href="announcements.php" class="nav-link active">
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
    Edit Announcement
</h2>

                <a
                    href="announcements.php"
                    class="btn btn-secondary"
                >
                    Back
                </a>

            </div>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <form method="POST">


                        <div class="mb-3">

                            <label class="form-label">
                                Title *
                            </label>

                            <input
                                type="text"
                                name="title"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $announcement["title"]
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Message *
                            </label>

                            <textarea
                                name="message"
                                class="form-control"
                                rows="8"
                                required
                            ><?= htmlspecialchars(
                                $announcement["message"]
                            ) ?></textarea>

                        </div>


                        <div class="row">


                            <div class="col-md-6">

                                <div class="mb-4">

                                    <label class="form-label">
                                        Priority
                                    </label>

                                    <select
                                        name="priority"
                                        class="form-select"
                                    >

                                        <option
                                            value="Low"
                                            <?= $announcement["priority"] === "Low"
                                                ? "selected"
                                                : ""
                                            ?>
                                        >
                                            Low
                                        </option>

                                        <option
                                            value="Normal"
                                            <?= $announcement["priority"] === "Normal"
                                                ? "selected"
                                                : ""
                                            ?>
                                        >
                                            Normal
                                        </option>

                                        <option
                                            value="High"
                                            <?= $announcement["priority"] === "High"
                                                ? "selected"
                                                : ""
                                            ?>
                                        >
                                            High
                                        </option>

                                        <option
                                            value="Emergency"
                                            <?= $announcement["priority"] === "Emergency"
                                                ? "selected"
                                                : ""
                                            ?>
                                        >
                                            Emergency
                                        </option>

                                    </select>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="mb-4">

                                    <label class="form-label">
                                        Status
                                    </label>

                                    <select
                                        name="status"
                                        class="form-select"
                                    >

                                        <option
                                            value="Draft"
                                            <?= $announcement["status"] === "Draft"
                                                ? "selected"
                                                : ""
                                            ?>
                                        >
                                            Draft
                                        </option>

                                        <option
                                            value="Published"
                                            <?= $announcement["status"] === "Published"
                                                ? "selected"
                                                : ""
                                            ?>
                                        >
                                            Published
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>


                        <div class="d-flex gap-2">

                            <a
                                href="announcements.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Update Announcement
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
