<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Only approved citizens may submit reports.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "citizen"
) {
    header("Location: ../auth/login.php");
    exit;
}

// Get citizen information.
$stmt = $pdo->prepare("
    SELECT id, full_name, contact_number, account_status
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

$error = "";
$success = "";

if (empty($_SESSION["report_csrf"])) {
    $_SESSION["report_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["report_csrf"],
        $_POST["csrf_token"] ?? ""
    )) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $incidentType = trim($_POST["incident_type"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $latitude = $_POST["latitude"] ?? "";
        $longitude = $_POST["longitude"] ?? "";
        $accuracy = $_POST["location_accuracy"] ?? "";

        $allowedTypes = [
            "Fire",
            "Flood",
            "Earthquake",
            "Landslide",
            "Typhoon",
            "Medical Emergency",
            "Accident",
            "Other"
        ];

        if (!in_array($incidentType, $allowedTypes, true)) {
            $error = "Please select a valid incident type.";
        } elseif ($description === "") {
            $error = "Please provide an incident description.";
        } elseif (strlen($description) > 10000) {
            $error = "Description is too long.";
        } elseif (
            ($latitude !== "" && !is_numeric($latitude)) ||
            ($longitude !== "" && !is_numeric($longitude)) ||
            ($accuracy !== "" && !is_numeric($accuracy))
        ) {
            $error = "Invalid GPS coordinates.";
        } elseif (
            ($latitude !== "" && ((float)$latitude < -90 || (float)$latitude > 90)) ||
            ($longitude !== "" && ((float)$longitude < -180 || (float)$longitude > 180))
        ) {
            $error = "GPS coordinates are outside the valid range.";
        } else {

            $imagePath = null;
            $uploadDir = __DIR__ . "/../uploads/reports/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Optional incident photo (live camera capture or gallery upload).
            if (
                isset($_FILES["incident_image"]) &&
                $_FILES["incident_image"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {
                $file = $_FILES["incident_image"];

                if ($file["error"] !== UPLOAD_ERR_OK) {
                    $error = "The image upload failed.";
                } elseif ($file["size"] > 5 * 1024 * 1024) {
                    $error = "Image must not exceed 5 MB.";
                } else {

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file["tmp_name"]);

                    $allowedImages = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp"
                    ];

                    if (!isset($allowedImages[$mime])) {
                        $error = "Only JPG, PNG, and WEBP images are allowed.";
                    } elseif (getimagesize($file["tmp_name"]) === false) {
                        $error = "Invalid image file.";
                    } else {

                        $filename = bin2hex(random_bytes(16))
                            . "." . $allowedImages[$mime];

                        if (move_uploaded_file(
                            $file["tmp_name"],
                            $uploadDir . $filename
                        )) {
                            $imagePath = "uploads/reports/" . $filename;
                        } else {
                            $error = "Unable to save the uploaded image.";
                        }
                    }
                }
            }

            if ($error === "") {
                try {
                    $reportCode = "BDR-" . date("Ymd") . "-"
                        . strtoupper(bin2hex(random_bytes(3)));

                    $stmt = $pdo->prepare("
                        INSERT INTO citizen_reports (
                            user_id,
                            report_code,
                            reporter_name,
                            reporter_contact,
                            incident_type,
                            description,
                            image_path,
                            latitude,
                            longitude,
                            location_accuracy
                        ) VALUES (
                            :user_id,
                            :report_code,
                            :reporter_name,
                            :reporter_contact,
                            :incident_type,
                            :description,
                            :image_path,
                            :latitude,
                            :longitude,
                            :accuracy
                        )
                    ");

                    $stmt->execute([
                        ":user_id" => $citizen["id"],
                        ":report_code" => $reportCode,
                        ":reporter_name" => $citizen["full_name"],
                        ":reporter_contact" => $citizen["contact_number"],
                        ":incident_type" => $incidentType,
                        ":description" => $description,
                        ":image_path" => $imagePath,
                        ":latitude" => $latitude === "" ? null : $latitude,
                        ":longitude" => $longitude === "" ? null : $longitude,
                        ":accuracy" => $accuracy === "" ? null : $accuracy
                    ]);

                    $success = "Your incident report was submitted successfully. Reference number: " . $reportCode;

                    $_SESSION["report_csrf"] = bin2hex(random_bytes(32));

                } catch (PDOException $e) {
                    error_log("Citizen report error: " . $e->getMessage());
                    $error = "Unable to submit your report. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report an Incident | Barangay Disaster Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7f5;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #123d32;
        }

        .main-card {
            background: #fff;
            border: 0;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,.07);
        }

        h3, h5 {
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

        .form-control, .form-select {
            padding: 11px;
            border-radius: 8px;
        }

        #map {
            width: 100%;
            height: 280px;
            border: 0;
            border-radius: 10px;
            background: #eee;
        }

        .gps-status {
            font-size: 14px;
        }

        /* ---------- Camera ---------- */

        .camera-box {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            max-height: 70vh;
            background: #0b1f1a;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .camera-box video,
        .camera-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .camera-placeholder {
            color: #9fc2b6;
            text-align: center;
            padding: 20px;
        }

        .camera-placeholder svg {
            margin-bottom: 8px;
            stroke: #45a879;
        }

        .btn-capture {
            background: #fff;
            color: #164b3b;
            border: 3px solid #45a879;
            font-weight: 700;
        }

        .btn-capture:hover {
            background: #e9f6ef;
            color: #164b3b;
            border-color: #45a879;
        }

        .gallery-link {
            font-size: 14px;
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
        <h3>Report an Incident</h3>
        <p class="text-muted">
            Submit an emergency or disaster incident to your Barangay Disaster Management Team.
        </p>
    </div>

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

    <div class="card main-card">
        <div class="card-body p-4">

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION["report_csrf"]) ?>">

                <h5 class="mb-3">Citizen Information</h5>

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control"
                           value="<?= htmlspecialchars($citizen["full_name"]) ?>"
                           readonly>
                </div>

                <div class="mb-4">
                    <label class="form-label">Contact Number</label>
                    <input type="text" class="form-control"
                           value="<?= htmlspecialchars($citizen["contact_number"] ?? "") ?>"
                           readonly>
                </div>

                <hr>

                <h5 class="mb-3">Incident Details</h5>

                <div class="mb-3">
                    <label class="form-label">Incident Type</label>
                    <select name="incident_type" class="form-select" required>
                        <option value="">Select incident type</option>
                        <option>Fire</option>
                        <option>Flood</option>
                        <option>Earthquake</option>
                        <option>Landslide</option>
                        <option>Typhoon</option>
                        <option>Medical Emergency</option>
                        <option>Accident</option>
                        <option>Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Incident Description</label>
                    <textarea name="description" class="form-control"
                              rows="5" maxlength="10000"
                              placeholder="Describe what happened and provide the exact location or nearby landmarks..."
                              required></textarea>
                </div>

                <hr>

                <h5 class="mb-3">Incident Photo</h5>

                <!-- The one real upload field. Both the camera and the gallery fill it. -->
                <input type="file" name="incident_image" id="incidentImage"
                       class="d-none"
                       accept="image/jpeg,image/png,image/webp">

                <div class="camera-box mb-2">

                    <div class="camera-placeholder" data-show="idle">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/>
                            <circle cx="12" cy="13.5" r="3.5"/>
                        </svg>
                        <div>Tap <strong>Open Camera</strong> to take a photo of the incident</div>
                    </div>

                    <video id="cameraVideo" data-show="live"
                           class="d-none" autoplay playsinline muted></video>

                    <img id="photoPreview" data-show="captured"
                         class="d-none" alt="Incident photo preview">

                </div>

                <canvas id="cameraCanvas" class="d-none"></canvas>

                <p id="cameraStatus" class="small text-muted mb-3" role="status" aria-live="polite">
                    Photo is optional but helps responders assess the situation.
                </p>

                <div class="d-grid gap-2 mb-2">

                    <button type="button" id="btnOpen" data-show="idle"
                            class="btn btn-primary btn-lg">
                        Open Camera
                    </button>

                    <button type="button" id="btnCapture" data-show="live"
                            class="btn btn-capture btn-lg d-none">
                        Capture Photo
                    </button>

                    <div class="d-flex gap-2 d-none" data-show="live">
                        <button type="button" id="btnSwitch"
                                class="btn btn-outline-secondary flex-fill">
                            Switch Camera
                        </button>
                        <button type="button" id="btnCancel"
                                class="btn btn-outline-secondary flex-fill">
                            Cancel
                        </button>
                    </div>

                    <div class="d-flex gap-2 d-none" data-show="captured">
                        <button type="button" id="btnRetake"
                                class="btn btn-outline-success flex-fill">
                            Retake
                        </button>
                        <button type="button" id="btnRemove"
                                class="btn btn-outline-danger flex-fill">
                            Remove Photo
                        </button>
                    </div>

                </div>

                <div class="text-center mb-3" data-show="idle">
                    <button type="button" id="btnGallery"
                            class="btn btn-link gallery-link text-secondary">
                        or choose an existing photo from your gallery
                    </button>
                </div>

                <small class="text-muted d-block mb-3">JPG, PNG, or WEBP. Maximum 5 MB.</small>

                <hr>

                <h5 class="mb-3">Incident Location</h5>

                <p class="text-muted small">
                    Allow location access to attach your current GPS coordinates.
                </p>

                <button type="button" class="btn btn-outline-success mb-3"
                        onclick="getLocation()">
                    Get My Current Location
                </button>

                <p id="gpsStatus" class="gps-status text-muted">
                    Location has not been captured.
                </p>

                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                <input type="hidden" name="location_accuracy" id="accuracy">

                <div class="mb-4">
                    <iframe id="map"
                            src="about:blank"
                            loading="lazy"
                            title="Incident location map">
                    </iframe>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    Submit Incident Report
                </button>

            </form>

        </div>
    </div>
</div>

<script>
/* ============================================================
   Live camera capture (primary) + gallery upload (secondary)
   Both paths fill the same <input type="file" name="incident_image">
   so the PHP upload code works unchanged.
   ============================================================ */
(function () {

    const MAX_BYTES = 5 * 1024 * 1024;
    const MAX_WIDTH = 1600;          // downscale big camera frames to stay under 5 MB
    const ALLOWED = ["image/jpeg", "image/png", "image/webp"];

    const input   = document.getElementById("incidentImage");
    const video   = document.getElementById("cameraVideo");
    const canvas  = document.getElementById("cameraCanvas");
    const preview = document.getElementById("photoPreview");
    const status  = document.getElementById("cameraStatus");

    let stream = null;
    let facing = "environment";      // rear camera first
    let previewUrl = null;

    // Show only the elements that belong to the current state: idle | live | captured
    function setState(state) {
        document.querySelectorAll("[data-show]").forEach(function (el) {
            el.classList.toggle("d-none", !el.dataset.show.split(" ").includes(state));
        });
    }

    function setStatus(msg) {
        status.textContent = msg;
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        video.srcObject = null;
    }

    function clearPreview() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        preview.removeAttribute("src");
    }

    function showPreview(fileOrBlob) {
        clearPreview();
        previewUrl = URL.createObjectURL(fileOrBlob);
        preview.src = previewUrl;
    }

    async function startCamera() {

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus(window.isSecureContext
                ? "This browser cannot open the camera. Please choose a photo from your gallery."
                : "The camera needs a secure (HTTPS) connection. Please choose a photo from your gallery.");
            return;
        }

        stopCamera();
        setStatus("Starting camera...");

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: facing },
                    width:  { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            });

            video.srcObject = stream;
            await video.play().catch(function () {});

            setState("live");
            setStatus("Point the camera at the incident, then tap Capture Photo.");

        } catch (err) {

            stopCamera();
            setState("idle");

            if (err.name === "NotAllowedError" || err.name === "SecurityError") {
                setStatus("Camera access was blocked. Allow camera permission in your browser, or choose a photo from your gallery.");
            } else if (err.name === "NotFoundError" || err.name === "OverconstrainedError") {
                setStatus("No camera was found on this device. Please choose a photo from your gallery.");
            } else if (err.name === "NotReadableError") {
                setStatus("The camera is being used by another app. Close it and try again.");
            } else {
                setStatus("Unable to open the camera. Please choose a photo from your gallery.");
            }
        }
    }

    function capturePhoto() {

        if (!video.videoWidth) {
            setStatus("Camera is not ready yet. Please wait a moment.");
            return;
        }

        const scale = Math.min(1, MAX_WIDTH / video.videoWidth);
        canvas.width  = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);

        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {

            if (!blob) {
                setStatus("Could not capture the photo. Please try again.");
                return;
            }

            const file = new File([blob], "incident-" + Date.now() + ".jpg", {
                type: "image/jpeg"
            });

            // Put the captured photo into the real file input so it uploads with the form.
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;

            showPreview(blob);
            stopCamera();
            setState("captured");
            setStatus("Photo captured and ready to submit.");

        }, "image/jpeg", 0.85);
    }

    function removePhoto() {
        input.value = "";
        clearPreview();
        setState("idle");
        setStatus("Photo is optional but helps responders assess the situation.");
    }

    // Gallery (second option)
    input.addEventListener("change", function () {

        const file = input.files && input.files[0];

        if (!file) {
            return;
        }

        if (ALLOWED.indexOf(file.type) === -1) {
            input.value = "";
            setStatus("Only JPG, PNG, and WEBP images are allowed.");
            return;
        }

        if (file.size > MAX_BYTES) {
            input.value = "";
            setStatus("Image must not exceed 5 MB.");
            return;
        }

        stopCamera();
        showPreview(file);
        setState("captured");
        setStatus("Photo selected from gallery: " + file.name);
    });

    document.getElementById("btnOpen").addEventListener("click", startCamera);
    document.getElementById("btnCapture").addEventListener("click", capturePhoto);

    document.getElementById("btnSwitch").addEventListener("click", function () {
        facing = facing === "environment" ? "user" : "environment";
        startCamera();
    });

    document.getElementById("btnCancel").addEventListener("click", function () {
        stopCamera();
        setState("idle");
        setStatus("Photo is optional but helps responders assess the situation.");
    });

    document.getElementById("btnRetake").addEventListener("click", function () {
        removePhoto();
        startCamera();
    });

    document.getElementById("btnRemove").addEventListener("click", removePhoto);

    document.getElementById("btnGallery").addEventListener("click", function () {
        input.click();
    });

    // Always release the camera when leaving the page or switching tabs.
    document.addEventListener("visibilitychange", function () {
        if (document.hidden && stream) {
            stopCamera();
            setState("idle");
            setStatus("Camera was closed. Tap Open Camera to continue.");
        }
    });

    window.addEventListener("pagehide", stopCamera);

    setState("idle");

})();

/* ============================================================
   GPS location
   ============================================================ */
function getLocation() {

    const status = document.getElementById("gpsStatus");

    if (!navigator.geolocation) {
        status.textContent = "Your browser does not support GPS location.";
        return;
    }

    status.textContent = "Getting your current location...";

    navigator.geolocation.getCurrentPosition(
        function(position) {

            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lng;
            document.getElementById("accuracy").value = accuracy;

            status.textContent =
                "Location captured successfully. Accuracy: "
                + Math.round(accuracy) + " meters.";

            document.getElementById("map").src =
                "https://www.openstreetmap.org/export/embed.html?bbox="
                + (lng - 0.005) + "%2C" + (lat - 0.005) + "%2C"
                + (lng + 0.005) + "%2C" + (lat + 0.005)
                + "&layer=mapnik&marker=" + lat + "%2C" + lng;
        },

        function(error) {
            status.textContent = "Unable to get location. Please allow location access and try again.";
        },

        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}
</script>

</body>
</html>