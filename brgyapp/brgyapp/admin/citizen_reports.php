<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Administrator access only.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";
$success = "";

$statuses = [
    "Pending",
    "Under Review",
    "Verified",
    "Responding",
    "Resolved",
    "Rejected"
];

// Incident type => [text color, background color, icon path]
$typeMeta = [
    "Fire"              => ["#c2410c", "#ffedd5", "M12 3c1 3 4 4.5 4 8a4 4 0 0 1-8 0c0-1.5.7-2.5 1.5-3.5C9.5 9 10 10 11 10c0-3 0-5 1-7z M6 20h12"],
    "Flood"             => ["#0369a1", "#e0f2fe", "M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"],
    "Earthquake"        => ["#6d28d9", "#ede9fe", "M2 12h4l2-6 4 12 3-9 2 3h5"],
    "Landslide"         => ["#92400e", "#fef3c7", "M3 20l6-10 4 6 3-4 5 8z"],
    "Typhoon"           => ["#0f766e", "#ccfbf1", "M3 9h12a3 3 0 1 0-3-3 M3 13h16a3 3 0 1 1-3 3 M3 17h8"],
    "Medical Emergency" => ["#be123c", "#ffe4e6", "M12 5v14 M5 12h14"],
    "Accident"          => ["#a16207", "#fef9c3", "M12 3l10 18H2z M12 10v5 M12 18v.01"],
    "Other"             => ["#475569", "#f1f5f9", "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 8v5 M12 16.5v.01"]
];

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function statusSlug($status) {
    return strtolower(str_replace(" ", "-", (string)$status));
}

function typeInfo($type, $typeMeta) {
    return $typeMeta[$type] ?? $typeMeta["Other"];
}

function timeAgo($timestamp) {
    if (!$timestamp) {
        return "";
    }

    $diff = time() - $timestamp;

    if ($diff < 60)     return "just now";
    if ($diff < 3600)   return floor($diff / 60) . " min ago";
    if ($diff < 86400)  return floor($diff / 3600) . " hr ago";
    if ($diff < 604800) return floor($diff / 86400) . " days ago";

    return date("M d, Y", $timestamp);
}

function pageUrl(array $filters, array $extra = []) {
    $query = array_filter(
        array_merge($filters, $extra),
        fn($v) => $v !== "" && $v !== null
    );

    return "citizen_reports.php" . ($query ? "?" . http_build_query($query) : "");
}

if (empty($_SESSION["admin_reports_csrf"])) {
    $_SESSION["admin_reports_csrf"] = bin2hex(random_bytes(32));
}

// Update report status and administrator notes.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["admin_reports_csrf"], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $reportId = filter_input(INPUT_POST, "report_id", FILTER_VALIDATE_INT);
        $newStatus = $_POST["status"] ?? "";
        $adminNotes = trim($_POST["admin_notes"] ?? "");

        if (!$reportId || !in_array($newStatus, $statuses, true)) {
            $error = "Invalid report or status.";
        } elseif (strlen($adminNotes) > 10000) {
            $error = "Administrator notes are too long.";
        } else {

            try {
                $stmt = $pdo->prepare("
                    UPDATE citizen_reports
                    SET status = :status,
                        admin_notes = :admin_notes
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":status" => $newStatus,
                    ":admin_notes" => $adminNotes === "" ? null : $adminNotes,
                    ":id" => $reportId
                ]);

                $_SESSION["admin_reports_csrf"] = bin2hex(random_bytes(32));

                // Return to the same filtered view.
                parse_str($_POST["return_query"] ?? "", $returnQuery);
                $keep = [];

                foreach (["search", "status", "type", "page"] as $key) {
                    if (isset($returnQuery[$key]) && is_string($returnQuery[$key]) && $returnQuery[$key] !== "") {
                        $keep[$key] = $returnQuery[$key];
                    }
                }

                $keep["updated"] = 1;

                header("Location: citizen_reports.php?" . http_build_query($keep));
                exit;

            } catch (PDOException $e) {
                error_log("Admin report update error: " . $e->getMessage());
                $error = "Unable to update the report. Please try again.";
            }
        }
    }
}

if (isset($_GET["updated"])) {
    $success = "Report updated successfully.";
}

// Filters.
$search = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$typeFilter = $_GET["type"] ?? "";

if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = "";
}

if (!isset($typeMeta[$typeFilter])) {
    $typeFilter = "";
}

$filters = [
    "search" => $search,
    "status" => $statusFilter,
    "type"   => $typeFilter
];

// Status counts (for the summary cards and tabs, ignoring filters).
$counts = array_fill_keys($statuses, 0);

foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM citizen_reports GROUP BY status") as $row) {
    if (isset($counts[$row["status"]])) {
        $counts[$row["status"]] = (int)$row["total"];
    }
}

$totalAll = array_sum($counts);
$inProgress = $counts["Under Review"] + $counts["Verified"] + $counts["Responding"];

// Build the filtered query.
$where = [];
$params = [];

if ($search !== "") {
    $like = "%" . addcslashes($search, "%_\\") . "%";

    $where[] = "(cr.report_code LIKE :s1 OR cr.reporter_name LIKE :s2 OR cr.incident_type LIKE :s3)";
    $params[":s1"] = $like;
    $params[":s2"] = $like;
    $params[":s3"] = $like;
}

if ($statusFilter !== "") {
    $where[] = "cr.status = :status";
    $params[":status"] = $statusFilter;
}

if ($typeFilter !== "") {
    $where[] = "cr.incident_type = :type";
    $params[":type"] = $typeFilter;
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// Pagination.
$perPage = 15;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM citizen_reports cr" . $whereSql);
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($totalPages, max(1, (int)($_GET["page"] ?? 1)));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT cr.*, u.username AS citizen_username
    FROM citizen_reports cr
    LEFT JOIN users u ON u.id = cr.user_id
    $whereSql
    ORDER BY cr.created_at DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value);
}

$stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll();

// Data for the review modal.
$reportData = [];

foreach ($reports as $r) {
    $reportData[(int)$r["id"]] = [
        "id"      => (int)$r["id"],
        "code"    => $r["report_code"],
        "name"    => $r["reporter_name"],
        "contact" => $r["reporter_contact"],
        "type"    => $r["incident_type"],
        "desc"    => $r["description"],
        "created" => $r["created_at"] ? date("M d, Y h:i A", strtotime($r["created_at"])) : "",
        "status"  => $r["status"],
        "notes"   => $r["admin_notes"],
        "img"     => $r["image_path"],
        "lat"     => $r["latitude"] !== null ? (float)$r["latitude"] : null,
        "lng"     => $r["longitude"] !== null ? (float)$r["longitude"] : null,
        "acc"     => $r["location_accuracy"] !== null ? round((float)$r["location_accuracy"]) : null
    ];
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
           | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

$hasFilters = $search !== "" || $statusFilter !== "" || $typeFilter !== "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Citizen Reports | Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --night: #05151a;
            --forest: #123d32;
            --green: #164b3b;
            --green-hover: #245b4b;
            --mint: #45a879;
            --alert: #f2b33d;
            --ink: #12231e;
            --muted: #62756e;
            --line: #e1eae6;
            --surface: #ffffff;
            --page: #f2f6f4;
        }

        body {
            background: var(--page);
            color: var(--ink);
            font-family: "Sora", system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: .92rem;
        }

        h3, h5, h6 {
            color: var(--green);
            font-weight: 700;
        }

        /* ---------- Top bar ---------- */

        .topbar {
            background: linear-gradient(135deg, var(--night), var(--forest));
            border-bottom: 3px solid var(--alert);
        }

        .topbar .navbar-brand {
            font-weight: 600;
            font-size: 1rem;
        }

        .topbar .admin-name {
            color: #b9d3ca;
            font-size: .85rem;
        }

        /* ---------- Summary cards ---------- */

        .stat-card {
            background: var(--surface);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: 14px;
            height: 100%;
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .stat-icon svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-label {
            color: var(--muted);
            font-size: .8rem;
            margin-top: 4px;
        }

        .stat-total   .stat-icon { background: #e2f1ea; color: var(--green); }
        .stat-pending .stat-icon { background: #fff3d6; color: #b7791f; }
        .stat-active  .stat-icon { background: #e0efff; color: #1d6fb8; }
        .stat-done    .stat-icon { background: #dcf5e6; color: #1f8a4c; }

        /* ---------- Panels ---------- */

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
        }

        .status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .status-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            font-size: .83rem;
            font-weight: 500;
            transition: background .15s ease, border-color .15s ease;
        }

        .status-tab:hover {
            background: #f1f8f5;
            border-color: var(--mint);
            color: var(--ink);
        }

        .status-tab.active {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .status-tab .count {
            font-size: .75rem;
            padding: 1px 8px;
            border-radius: 999px;
            background: #eaf1ee;
            color: var(--green);
            font-weight: 600;
        }

        .status-tab.active .count {
            background: rgba(255,255,255,.2);
            color: #fff;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border-color: #d5e1dc;
            padding: 10px 14px;
            font-size: .9rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--mint);
            box-shadow: 0 0 0 .2rem rgba(69,168,121,.18);
        }

        .btn-primary {
            background: var(--green);
            border-color: var(--green);
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-primary:hover,
        .btn-primary:focus-visible {
            background: var(--green-hover);
            border-color: var(--green-hover);
        }

        .btn-outline-secondary {
            border-radius: 10px;
        }

        /* ---------- Table ---------- */

        .report-table {
            margin: 0;
        }

        .report-table thead th {
            background: #f3f8f5;
            color: var(--muted);
            font-weight: 600;
            font-size: .78rem;
            white-space: nowrap;
            border-bottom: 1px solid var(--line);
            padding: 12px 16px;
        }

        .report-table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-color: var(--line);
        }

        .report-table tbody tr {
            cursor: pointer;
            transition: background .12s ease;
        }

        .report-table tbody tr:hover {
            background: #f7fbf9;
        }

        .report-table tbody tr.row-pending td:first-child {
            box-shadow: inset 4px 0 0 var(--alert);
        }

        .report-code {
            font-weight: 700;
            color: var(--green);
            white-space: nowrap;
        }

        .sub {
            color: var(--muted);
            font-size: .78rem;
        }

        .type-chip {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-grid;
            place-items: center;
            flex-shrink: 0;
        }

        .type-chip svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .meta-icons {
            display: flex;
            gap: 8px;
            color: var(--mint);
        }

        .meta-icons svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .meta-icons .off {
            color: #cbd7d2;
        }

        /* ---------- Status colors ---------- */

        .s-pending      { --c: #64748b; --bg: #eef2f6; }
        .s-under-review { --c: #1d6fb8; --bg: #e0efff; }
        .s-verified     { --c: #0f766e; --bg: #d5f5ef; }
        .s-responding   { --c: #b45309; --bg: #fff0d1; }
        .s-resolved     { --c: #15803d; --bg: #dcf5e6; }
        .s-rejected     { --c: #b91c1c; --bg: #fde4e4; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 600;
            color: var(--c);
            background: var(--bg);
            white-space: nowrap;
        }

        .status-badge::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--c);
        }

        .btn-review {
            border: 1.5px solid var(--mint);
            color: var(--green);
            background: #f1f8f5;
            border-radius: 9px;
            font-weight: 600;
            font-size: .82rem;
            padding: 6px 16px;
        }

        .btn-review:hover {
            background: var(--mint);
            color: #fff;
        }

        /* ---------- Pagination ---------- */

        .pagination .page-link {
            color: var(--green);
            border-color: var(--line);
            border-radius: 8px !important;
            margin: 0 2px;
        }

        .pagination .page-item.active .page-link {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        /* ---------- Empty state ---------- */

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            stroke: var(--mint);
            fill: none;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            margin-bottom: 12px;
        }

        /* ---------- Review modal ---------- */

        .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-head {
            background: linear-gradient(135deg, var(--forest), var(--green));
            color: #fff;
            padding: 20px 26px;
            border-bottom: 4px solid var(--alert);
        }

        .modal-head .code {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .modal-head .btn-close {
            filter: invert(1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 24px;
        }

        .info-label {
            color: var(--muted);
            font-size: .75rem;
            margin-bottom: 3px;
        }

        .info-value {
            font-weight: 600;
        }

        .info-value a {
            color: var(--green);
            text-decoration: none;
        }

        .block-title {
            font-size: .85rem;
            color: var(--green);
            font-weight: 700;
            margin: 24px 0 10px;
        }

        .desc-box {
            background: #f6faf8;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px 16px;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.65;
        }

        .photo-frame {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--line);
            background: #0b1f1a;
        }

        .photo-frame img {
            display: block;
            width: 100%;
            max-height: 360px;
            object-fit: contain;
        }

        #mMap {
            width: 100%;
            height: 240px;
            border: 0;
            border-radius: 12px;
            background: #e8eeeb;
        }

        .action-panel {
            background: #f6faf8;
            border-left: 1px solid var(--line);
        }

        .status-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .status-opt {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 10px 8px;
            border-radius: 10px;
            border: 1.5px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: border-color .15s ease, background .15s ease;
        }

        .status-opt:hover {
            border-color: var(--c);
            background: var(--bg);
        }

        .btn-check:checked + .status-opt {
            background: var(--c);
            border-color: var(--c);
            color: #fff;
        }

        .btn-check:focus-visible + .status-opt {
            outline: 3px solid rgba(69,168,121,.45);
            outline-offset: 2px;
        }

        .alert {
            border-radius: 12px;
        }

        @media (max-width: 991.98px) {
            .action-panel {
                border-left: 0;
                border-top: 1px solid var(--line);
            }
        }

        @media (max-width: 575.98px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark topbar">
    <div class="container-fluid px-4">

        <a class="navbar-brand" href="dashboard.php">
            Barangay DMS <span class="text-white-50 fw-normal">| Administration</span>
        </a>

        <div class="d-flex align-items-center gap-3">
            <span class="admin-name d-none d-md-inline">
                <?= e($_SESSION["full_name"] ?? "Administrator") ?>
            </span>

            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                Dashboard
            </a>
        </div>

    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h3 class="mb-1">Citizen Incident Reports</h3>
            <p class="text-muted mb-0">
                Review incoming reports, verify incidents, and track the response.
            </p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3">
            <div class="stat-card stat-total">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 13h7M9 17h7"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $totalAll ?></div>
                    <div class="stat-label">Total reports</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card stat-pending">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $counts["Pending"] ?></div>
                    <div class="stat-label">Waiting for review</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card stat-active">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24"><path d="M2 12h4l2-6 4 12 3-9 2 3h5"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $inProgress ?></div>
                    <div class="stat-label">In progress</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card stat-done">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $counts["Resolved"] ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters -->
    <div class="panel p-3 mb-4">

        <div class="status-tabs mb-3">

            <a href="<?= e(pageUrl(["search" => $search, "type" => $typeFilter])) ?>"
               class="status-tab <?= $statusFilter === "" ? "active" : "" ?>">
                All <span class="count"><?= $totalAll ?></span>
            </a>

            <?php foreach ($statuses as $s): ?>
                <a href="<?= e(pageUrl(["search" => $search, "type" => $typeFilter, "status" => $s])) ?>"
                   class="status-tab <?= $statusFilter === $s ? "active" : "" ?>">
                    <?= e($s) ?> <span class="count"><?= $counts[$s] ?></span>
                </a>
            <?php endforeach; ?>

        </div>

        <form method="GET" class="row g-2">

            <?php if ($statusFilter !== ""): ?>
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <?php endif; ?>

            <div class="col-lg-6">
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="Search reference number, citizen name, or incident type..."
                    value="<?= e($search) ?>">
            </div>

            <div class="col-lg-3">
                <select name="type" class="form-select">
                    <option value="">All incident types</option>
                    <?php foreach (array_keys($typeMeta) as $t): ?>
                        <option value="<?= e($t) ?>" <?= $typeFilter === $t ? "selected" : "" ?>>
                            <?= e($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill">Search</button>

                <?php if ($hasFilters): ?>
                    <a href="citizen_reports.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>

        </form>

    </div>

    <!-- Reports table -->
    <div class="panel">

        <div class="d-flex justify-content-between align-items-center p-3 pb-2">
            <h5 class="mb-0">Submitted Reports</h5>
            <span class="sub">
                <?php if ($totalFiltered > 0): ?>
                    Showing <?= $offset + 1 ?>–<?= $offset + count($reports) ?> of <?= $totalFiltered ?>
                <?php else: ?>
                    0 reports
                <?php endif; ?>
            </span>
        </div>

        <?php if (empty($reports)): ?>

            <div class="empty-state">
                <svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 14h7"/></svg>
                <h6 class="mb-1">No incident reports found</h6>
                <p class="mb-0">
                    <?= $hasFilters
                        ? "Try a different search or clear the filters."
                        : "New citizen reports will appear here as soon as they are submitted." ?>
                </p>
            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table report-table align-middle">

                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Incident</th>
                            <th>Citizen</th>
                            <th class="d-none d-lg-table-cell">Submitted</th>
                            <th class="d-none d-md-table-cell">Attachments</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($reports as $report):

                        $timestamp = $report["created_at"] ? strtotime($report["created_at"]) : 0;
                        [$typeColor, $typeBg, $typeIcon] = typeInfo($report["incident_type"], $typeMeta);
                        $hasGps = $report["latitude"] !== null && $report["longitude"] !== null;
                        $hasPhoto = !empty($report["image_path"]);
                    ?>

                        <tr data-report="<?= (int)$report["id"] ?>"
                            class="<?= $report["status"] === "Pending" ? "row-pending" : "" ?>">

                            <td class="report-code">
                                <?= e($report["report_code"]) ?>
                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="type-chip"
                                          style="color:<?= $typeColor ?>;background:<?= $typeBg ?>">
                                        <svg viewBox="0 0 24 24"><path d="<?= $typeIcon ?>"/></svg>
                                    </span>
                                    <span class="fw-semibold"><?= e($report["incident_type"]) ?></span>
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold"><?= e($report["reporter_name"]) ?></div>
                                <div class="sub"><?= e($report["reporter_contact"]) ?></div>
                            </td>

                            <td class="d-none d-lg-table-cell">
                                <div><?= e(timeAgo($timestamp)) ?></div>
                                <div class="sub">
                                    <?= $timestamp ? e(date("M d, Y h:i A", $timestamp)) : "" ?>
                                </div>
                            </td>

                            <td class="d-none d-md-table-cell">
                                <div class="meta-icons">

                                    <span class="<?= $hasPhoto ? "" : "off" ?>"
                                          title="<?= $hasPhoto ? "Photo attached" : "No photo" ?>">
                                        <svg viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
                                    </span>

                                    <span class="<?= $hasGps ? "" : "off" ?>"
                                          title="<?= $hasGps ? "GPS location attached" : "No GPS location" ?>">
                                        <svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                    </span>

                                </div>
                            </td>

                            <td>
                                <span class="status-badge s-<?= e(statusSlug($report["status"])) ?>">
                                    <?= e($report["status"]) ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <button type="button" class="btn btn-review">
                                    Review
                                </button>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

            <?php if ($totalPages > 1): ?>

                <nav class="p-3 d-flex justify-content-center" aria-label="Reports pages">
                    <ul class="pagination mb-0">

                        <li class="page-item <?= $page <= 1 ? "disabled" : "" ?>">
                            <a class="page-link"
                               href="<?= e(pageUrl($filters, ["page" => $page - 1])) ?>">Previous</a>
                        </li>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);

                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? "active" : "" ?>">
                                <a class="page-link"
                                   href="<?= e(pageUrl($filters, ["page" => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? "disabled" : "" ?>">
                            <a class="page-link"
                               href="<?= e(pageUrl($filters, ["page" => $page + 1])) ?>">Next</a>
                        </li>

                    </ul>
                </nav>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

<!-- Review modal (one modal, filled with the selected report) -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="mCode" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <form method="POST">

                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["admin_reports_csrf"]) ?>">
                <input type="hidden" name="report_id" id="mId">
                <input type="hidden" name="return_query" value="<?= e($_SERVER["QUERY_STRING"] ?? "") ?>">

                <div class="modal-head d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="text-white-50 small mb-1">Incident report</div>
                        <div class="code" id="mCode"></div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <span class="status-badge" id="mBadge"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>

                <div class="row g-0">

                    <!-- Report details -->
                    <div class="col-lg-7 p-4">

                        <div class="info-grid">

                            <div>
                                <div class="info-label">Citizen</div>
                                <div class="info-value" id="mName"></div>
                            </div>

                            <div>
                                <div class="info-label">Contact number</div>
                                <div class="info-value"><a id="mContact"></a></div>
                            </div>

                            <div>
                                <div class="info-label">Incident type</div>
                                <div class="info-value d-flex align-items-center gap-2">
                                    <span class="type-chip" id="mTypeChip" style="width:28px;height:28px">
                                        <svg viewBox="0 0 24 24"><path id="mTypeIcon" d=""/></svg>
                                    </span>
                                    <span id="mType"></span>
                                </div>
                            </div>

                            <div>
                                <div class="info-label">Date submitted</div>
                                <div class="info-value" id="mCreated"></div>
                            </div>

                        </div>

                        <div class="block-title">Description</div>
                        <div class="desc-box" id="mDesc"></div>

                        <div id="mPhotoWrap">
                            <div class="block-title d-flex justify-content-between align-items-center">
                                <span>Incident photo</span>
                                <a id="mPhotoLink" target="_blank" rel="noopener noreferrer"
                                   class="small text-decoration-none" style="color:var(--green)">
                                    Open full size
                                </a>
                            </div>
                            <div class="photo-frame">
                                <img id="mPhoto" alt="Incident photo">
                            </div>
                        </div>

                        <div id="mLocWrap">
                            <div class="block-title d-flex justify-content-between align-items-center">
                                <span>Reported location</span>
                                <a id="mMapLink" target="_blank" rel="noopener noreferrer"
                                   class="small text-decoration-none" style="color:var(--green)">
                                    Open in OpenStreetMap
                                </a>
                            </div>
                            <iframe id="mMap" src="about:blank" title="Incident location map"></iframe>
                            <div class="sub mt-2" id="mCoords"></div>
                        </div>

                    </div>

                    <!-- Admin actions -->
                    <div class="col-lg-5 p-4 action-panel">

                        <div class="block-title mt-0">Update status</div>

                        <div class="status-options mb-4">
                            <?php foreach ($statuses as $s): $slug = statusSlug($s); ?>
                                <div class="s-<?= e($slug) ?>">
                                    <input type="radio" class="btn-check" name="status"
                                           id="st-<?= e($slug) ?>" value="<?= e($s) ?>" required>
                                    <label class="status-opt w-100 h-100" for="st-<?= e($slug) ?>">
                                        <?= e($s) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="block-title">Administrator notes</div>

                        <textarea name="admin_notes" id="mNotes" class="form-control"
                                  rows="7" maxlength="10000"
                                  placeholder="Enter investigation findings or response notes..."></textarea>

                        <div class="sub mt-2">
                            Notes are saved with the report and visible to administrators only.
                        </div>

                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="submit" class="btn btn-primary px-4">
                        Save changes
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const REPORTS = <?= json_encode($reportData, $jsonFlags) ?>;
const TYPE_META = <?= json_encode($typeMeta, $jsonFlags) ?>;

(function () {

    const $ = function (id) { return document.getElementById(id); };
    const modalEl = $("reportModal");
    const modal = new bootstrap.Modal(modalEl);

    function slug(status) {
        return String(status).toLowerCase().replace(/ /g, "-");
    }

    function openReport(id) {

        const r = REPORTS[id];

        if (!r) {
            return;
        }

        $("mId").value = r.id;
        $("mCode").textContent = r.code;

        const badge = $("mBadge");
        badge.textContent = r.status;
        badge.className = "status-badge s-" + slug(r.status);

        $("mName").textContent = r.name || "—";

        const contact = $("mContact");
        contact.textContent = r.contact || "—";

        if (r.contact) {
            contact.href = "tel:" + String(r.contact).replace(/[^0-9+]/g, "");
        } else {
            contact.removeAttribute("href");
        }

        const meta = TYPE_META[r.type] || TYPE_META["Other"];
        $("mType").textContent = r.type;
        $("mTypeIcon").setAttribute("d", meta[2]);
        $("mTypeChip").style.color = meta[0];
        $("mTypeChip").style.background = meta[1];

        $("mCreated").textContent = r.created || "—";
        $("mDesc").textContent = r.desc || "";

        // Photo
        if (r.img) {
            const src = "../" + r.img;
            $("mPhoto").src = src;
            $("mPhotoLink").href = src;
            $("mPhotoWrap").classList.remove("d-none");
        } else {
            $("mPhoto").removeAttribute("src");
            $("mPhotoWrap").classList.add("d-none");
        }

        // Location
        if (r.lat !== null && r.lng !== null) {
            const d = 0.004;

            $("mMap").src =
                "https://www.openstreetmap.org/export/embed.html?bbox="
                + (r.lng - d) + "%2C" + (r.lat - d) + "%2C"
                + (r.lng + d) + "%2C" + (r.lat + d)
                + "&layer=mapnik&marker=" + r.lat + "%2C" + r.lng;

            $("mMapLink").href =
                "https://www.openstreetmap.org/?mlat=" + r.lat + "&mlon=" + r.lng
                + "#map=17/" + r.lat + "/" + r.lng;

            $("mCoords").textContent =
                "Latitude " + r.lat + ", Longitude " + r.lng
                + (r.acc !== null ? " (accuracy about " + r.acc + " m)" : "");

            $("mLocWrap").classList.remove("d-none");
        } else {
            $("mMap").src = "about:blank";
            $("mLocWrap").classList.add("d-none");
        }

        // Status + notes
        document.querySelectorAll('input[name="status"]').forEach(function (radio) {
            radio.checked = radio.value === r.status;
        });

        $("mNotes").value = r.notes || "";

        modal.show();
    }

    // Free the map and photo when the modal closes.
    modalEl.addEventListener("hidden.bs.modal", function () {
        $("mMap").src = "about:blank";
        $("mPhoto").removeAttribute("src");
    });

    // Click anywhere on a row (or its Review button) to open it.
    document.querySelectorAll("tr[data-report]").forEach(function (row) {
        row.addEventListener("click", function (event) {
            if (event.target.closest("a")) {
                return;
            }
            openReport(row.dataset.report);
        });
    });

})();
</script>

</body>
</html>