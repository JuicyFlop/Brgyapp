
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";
$success = "";

if (empty($_SESSION["announcements_csrf"])) {
    $_SESSION["announcements_csrf"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Create, update, publish, or archive announcements.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["announcements_csrf"],
        $_POST["csrf_token"] ?? ""
    )) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $action = $_POST["action"] ?? "";

        try {
            if ($action === "save") {

                $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: null;
                $title = trim($_POST["title"] ?? "");
                $content = trim($_POST["content"] ?? "");
                $category = trim($_POST["category"] ?? "");
                $priority = $_POST["priority"] ?? "Normal";
                $status = $_POST["status"] ?? "Draft";

                $allowedPriorities = ["Low", "Normal", "High", "Critical"];
                $allowedStatuses = ["Draft", "Published"];

                if ($title === "" || $content === "" || $category === "") {
                    throw new RuntimeException("Please complete all required fields.");
                }

                if (
                    mb_strlen($title) > 200 ||
                    mb_strlen($category) > 100 ||
                    mb_strlen($content) > 20000
                ) {
                    throw new RuntimeException("One or more fields exceed their allowed length.");
                }

                if (!in_array($priority, $allowedPriorities, true)) {
                    throw new RuntimeException("Invalid priority.");
                }

                if (!in_array($status, $allowedStatuses, true)) {
                    throw new RuntimeException("Invalid announcement status.");
                }

                if ($id) {
                    $stmt = $pdo->prepare("
                        UPDATE announcements
                        SET title = :title,
                            content = :content,
                            category = :category,
                            priority = :priority,
                            status = :status,
                            published_at = CASE
                                WHEN :published_status = 'Published'
                                THEN COALESCE(published_at, NOW())
                                ELSE NULL
                            END
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ":title" => $title,
                        ":content" => $content,
                        ":category" => $category,
                        ":priority" => $priority,
                        ":status" => $status,
                        ":published_status" => $status,
                        ":id" => $id
                    ]);

                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO announcements (
                            title,
                            content,
                            category,
                            priority,
                            status,
                            created_by,
                            published_at
                        ) VALUES (
                            :title,
                            :content,
                            :category,
                            :priority,
                            :status,
                            :created_by,
                            CASE WHEN :published_status = 'Published'
                                 THEN NOW()
                                 ELSE NULL
                            END
                        )
                    ");

                    $stmt->execute([
                        ":title" => $title,
                        ":content" => $content,
                        ":category" => $category,
                        ":priority" => $priority,
                        ":status" => $status,
                        ":created_by" => $_SESSION["user_id"],
                        ":published_status" => $status
                    ]);
                }

                $_SESSION["announcements_csrf"] = bin2hex(random_bytes(32));
                header("Location: announcements.php?saved=1");
                exit;

            } elseif ($action === "archive") {

                $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

                if (!$id) {
                    throw new RuntimeException("Invalid announcement.");
                }

                $stmt = $pdo->prepare("
                    UPDATE announcements
                    SET status = 'Archived'
                    WHERE id = ?
                ");
                $stmt->execute([$id]);

                $_SESSION["announcements_csrf"] = bin2hex(random_bytes(32));
                header("Location: announcements.php?archived=1");
                exit;

            } else {
                throw new RuntimeException("Invalid action.");
            }

        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log("Announcement error: " . $e->getMessage());
            $error = "Unable to save the announcement. Please try again.";
        }
    }
}

if (isset($_GET["saved"])) {
    $success = "Announcement saved successfully.";
} elseif (isset($_GET["archived"])) {
    $success = "Announcement archived successfully.";
}

$stmt = $pdo->query("
    SELECT a.*, u.full_name AS author_name
    FROM announcements a
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.created_at DESC
");

$announcements = $stmt->fetchAll();

$editAnnouncement = null;

if (isset($_GET["edit"])) {
    $editId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT);

    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->execute([$editId]);
        $editAnnouncement = $stmt->fetch() ?: null;
    }
}

$form = $editAnnouncement ?: [
    "id" => "",
    "title" => "",
    "content" => "",
    "category" => "General",
    "priority" => "Normal",
    "status" => "Draft"
];

function priorityClass($priority) {
    return match ($priority) {
        "Critical" => "danger",
        "High" => "warning",
        "Normal" => "primary",
        "Low" => "secondary",
        default => "secondary"
    };
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Announcements Management | Admin</title>

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

        h3, h5 {
            color: #164b3b;
            font-weight: 700;
        }

        .main-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        .btn-primary {
            background: #164b3b;
            border-color: #164b3b;
        }

        .btn-primary:hover {
            background: #245b4b;
            border-color: #245b4b;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 12px;
        }

        .table thead th {
            background: #edf3ef;
            color: #164b3b;
            white-space: nowrap;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark topbar">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            Barangay Disaster Management — Administration
        </a>

        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            Dashboard
        </a>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <div class="mb-4">
        <h3>Announcements Management</h3>
        <p class="text-muted">
            Create and publish barangay disaster advisories and announcements.
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card main-card mb-4">
        <div class="card-body p-4">

            <h5 class="mb-3">
                <?= $editAnnouncement ? "Edit Announcement" : "Create Announcement" ?>
            </h5>

            <form method="POST">

                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION["announcements_csrf"]) ?>">

                <input type="hidden" name="action" value="save">

                <input type="hidden" name="id"
                       value="<?= e($form["id"]) ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Announcement Title</label>
                    <input type="text" name="title"
                           class="form-control" maxlength="200"
                           value="<?= e($form["title"]) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Announcement Content</label>
                    <textarea name="content" class="form-control"
                              rows="5" maxlength="20000" required><?= e($form["content"]) ?></textarea>
                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Category</label>

                        <select name="category" class="form-select" required>
                            <?php
                            $categories = [
                                "General",
                                "Weather Advisory",
                                "Flood Warning",
                                "Typhoon Warning",
                                "Earthquake Advisory",
                                "Fire Safety",
                                "Emergency Notice",
                                "Community Announcement"
                            ];
                            ?>

                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category) ?>"
                                    <?= $form["category"] === $category ? "selected" : "" ?>>
                                    <?= e($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Priority</label>

                        <select name="priority" class="form-select">
                            <?php foreach (["Low", "Normal", "High", "Critical"] as $priority): ?>
                                <option value="<?= e($priority) ?>"
                                    <?= $form["priority"] === $priority ? "selected" : "" ?>>
                                    <?= e($priority) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Publication Status</label>

                        <select name="status" class="form-select">
                            <option value="Draft"
                                <?= $form["status"] === "Draft" ? "selected" : "" ?>>
                                Draft
                            </option>

                            <option value="Published"
                                <?= $form["status"] === "Published" ? "selected" : "" ?>>
                                Published
                            </option>
                        </select>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary">
                    <?= $editAnnouncement ? "Save Changes" : "Save Announcement" ?>
                </button>

                <?php if ($editAnnouncement): ?>
                    <a href="announcements.php" class="btn btn-outline-secondary">
                        Cancel Edit
                    </a>
                <?php endif; ?>

            </form>

        </div>
    </div>

    <div class="card main-card">
        <div class="card-body p-4">

            <h5 class="mb-3">All Announcements</h5>

            <?php if (empty($announcements)): ?>

                <div class="alert alert-info">
                    No announcements have been created yet.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($announcements as $announcement): ?>

                            <tr>
                                <td class="fw-semibold">
                                    <?= e($announcement["title"]) ?>
                                </td>

                                <td><?= e($announcement["category"]) ?></td>

                                <td>
                                    <span class="badge bg-<?= e(priorityClass($announcement["priority"])) ?>">
                                        <?= e($announcement["priority"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-secondary">
                                        <?= e($announcement["status"]) ?>
                                    </span>
                                </td>

                                <td><?= e($announcement["author_name"] ?? "Administrator") ?></td>

                                <td><?= e(date("M d, Y", strtotime($announcement["created_at"]))) ?></td>

                                <td>
                                    <a href="announcements.php?edit=<?= (int)$announcement["id"] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>

                                    <?php if ($announcement["status"] !== "Archived"): ?>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Archive this announcement?');">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION["announcements_csrf"]) ?>">

                                            <input type="hidden" name="action" value="archive">

                                            <input type="hidden" name="id"
                                                   value="<?= (int)$announcement["id"] ?>">

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                Archive
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>
