<?php
declare(strict_types=1);

require_once __DIR__ . "/config/database.php";

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reporterName = trim($_POST["reporter_name"] ?? "");
    $reporterContact = trim($_POST["reporter_contact"] ?? "");
    $incidentType = trim($_POST["incident_type"] ?? "Other");
    $description = trim($_POST["description"] ?? "");
    $latitude = filter_var($_POST["latitude"] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST["longitude"] ?? null, FILTER_VALIDATE_FLOAT);
    $accuracy = filter_var($_POST["location_accuracy"] ?? null, FILTER_VALIDATE_FLOAT);

    $allowedTypes = [
        "Flood", "Fire", "Landslide", "Earthquake", "Road Obstruction",
        "Fallen Tree", "Medical Emergency", "Rescue", "Other"
    ];

    if (!in_array($incidentType, $allowedTypes, true)) {
        $incidentType = "Other";
    }

    if ($description === "") {
        $error = "Please describe the emergency or incident.";
    } elseif ($latitude === false || $longitude === false) {
        $error = "Your current location is required. Please enable GPS/location permission.";
    } elseif (!isset($_FILES["incident_image"]) ||
              $_FILES["incident_image"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Please capture an incident photo before sending the report.";
    } else {
        $file = $_FILES["incident_image"];

        if ($file["size"] > 8 * 1024 * 1024) {
            $error = "The photo must be 8 MB or smaller.";
        } else {
            $imageInfo = @getimagesize($file["tmp_name"]);
            $allowedMime = [
                "image/jpeg" => "jpg",
                "image/png"  => "png",
                "image/webp" => "webp"
            ];

            $mime = $imageInfo["mime"] ?? "";

            if (!$imageInfo || !isset($allowedMime[$mime])) {
                $error = "Only JPG, PNG, or WebP images are allowed.";
            } else {
                $uploadDir = __DIR__ . "/uploads/reports";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $reportCode = "RPT-" . date("Ymd-His") . "-" . strtoupper(bin2hex(random_bytes(2)));
                $filename = $reportCode . "." . $allowedMime[$mime];
                $destination = $uploadDir . "/" . $filename;

                if (!move_uploaded_file($file["tmp_name"], $destination)) {
                    $error = "The server could not save the photo.";
                } else {
                    $relativePath = "uploads/reports/" . $filename;

                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO citizen_reports (
                                report_code,
                                reporter_name,
                                reporter_contact,
                                incident_type,
                                description,
                                image_path,
                                latitude,
                                longitude,
                                location_accuracy,
                                ip_address,
                                user_agent
                            ) VALUES (
                                :report_code,
                                :reporter_name,
                                :reporter_contact,
                                :incident_type,
                                :description,
                                :image_path,
                                :latitude,
                                :longitude,
                                :location_accuracy,
                                :ip_address,
                                :user_agent
                            )
                        ");

                        $stmt->execute([
                            ":report_code" => $reportCode,
                            ":reporter_name" => $reporterName !== "" ? $reporterName : null,
                            ":reporter_contact" => $reporterContact !== "" ? $reporterContact : null,
                            ":incident_type" => $incidentType,
                            ":description" => $description,
                            ":image_path" => $relativePath,
                            ":latitude" => $latitude,
                            ":longitude" => $longitude,
                            ":location_accuracy" => $accuracy !== false ? $accuracy : null,
                            ":ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
                            ":user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? null
                        ]);

                        $success = "Report sent successfully. Your report number is " . $reportCode . ".";
                    } catch (PDOException $e) {
                        @unlink($destination);
                        $error = "Unable to save the report. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report an Emergency | Barangay DMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        crossorigin=""
    >

    <style>
        body { background: #f4f7fb; }
        .hero { background: linear-gradient(135deg, #0d6efd, #1558b0); color: white; }
        #map { height: 320px; border-radius: 12px; }
        #camera, #preview {
            width: 100%;
            min-height: 280px;
            max-height: 420px;
            object-fit: cover;
            background: #111;
            border-radius: 12px;
        }
        #preview { display: none; }
        .location-box { background: #f8f9fa; border-radius: 10px; }
    </style>
</head>
<body>

<header class="hero py-4 mb-4">
    <div class="container">
        <h1 class="fw-bold mb-1">🚨 Report an Emergency</h1>
        <p class="mb-0">Send a photo, description, and your current location to the barangay.</p>
    </div>
</header>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <?php if ($success): ?>
                <div class="alert alert-success shadow-sm">
                    <strong>Report received!</strong><br>
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-3">
                        <a href="report.php" class="btn btn-success">Submit Another Report</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger shadow-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="post" enctype="multipart/form-data" id="reportForm">

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h4 class="fw-bold">1. Capture the incident</h4>
                        <p class="text-muted">
                            Allow camera access, then take a photo of the emergency.
                        </p>

                        <video id="camera" autoplay playsinline></video>
                        <img id="preview" alt="Captured incident">

                        <canvas id="canvas" class="d-none"></canvas>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-primary" id="startCamera">
                                📷 Start Camera
                            </button>
                            <button type="button" class="btn btn-danger" id="capturePhoto" disabled>
                                📸 Capture Photo
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="retakePhoto" disabled>
                                🔄 Retake
                            </button>
                        </div>

                        <input
                            type="file"
                            name="incident_image"
                            id="incidentImage"
                            class="d-none"
                            accept="image/jpeg,image/png,image/webp"
                            capture="environment"
                            required
                        >
                        <div id="photoStatus" class="small text-muted mt-2">
                            No photo captured yet.
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h4 class="fw-bold">2. Describe the emergency</h4>

                        <div class="mb-3">
                            <label class="form-label">Incident Type</label>
                            <select name="incident_type" class="form-select">
                                <option>Flood</option>
                                <option>Fire</option>
                                <option>Landslide</option>
                                <option>Earthquake</option>
                                <option>Road Obstruction</option>
                                <option>Fallen Tree</option>
                                <option>Medical Emergency</option>
                                <option>Rescue</option>
                                <option selected>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea
                                name="description"
                                class="form-control"
                                rows="5"
                                maxlength="3000"
                                required
                                placeholder="Describe what happened, what you can see, and whether anyone needs immediate assistance."
                            ></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Your Name <span class="text-muted">(optional)</span></label>
                                <input type="text" name="reporter_name" class="form-control" maxlength="150">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number <span class="text-muted">(optional)</span></label>
                                <input type="text" name="reporter_contact" class="form-control" maxlength="50">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h4 class="fw-bold">3. Current location</h4>
                        <p class="text-muted">
                            Your browser will continuously update your location while this page is open.
                        </p>

                        <div class="location-box p-3 mb-3">
                            <div id="locationStatus" class="fw-semibold text-warning">
                                📍 Waiting for location permission...
                            </div>
                            <div class="small text-muted mt-2">
                                Latitude: <span id="latText">—</span><br>
                                Longitude: <span id="lngText">—</span><br>
                                Accuracy: <span id="accuracyText">—</span>
                            </div>
                        </div>

                        <div id="map"></div>

                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                        <input type="hidden" name="location_accuracy" id="location_accuracy">
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="alert alert-warning">
                            <strong>Before sending:</strong>
                            Make sure the photo and description are accurate. Do not submit false emergency reports.
                        </div>

                        <button type="submit" class="btn btn-danger btn-lg w-100" id="submitBtn" disabled>
                            🚨 SEND EMERGENCY REPORT
                        </button>
                    </div>
                </div>

            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    crossorigin=""
></script>

<script>
const video = document.getElementById("camera");
const preview = document.getElementById("preview");
const canvas = document.getElementById("canvas");
const startCamera = document.getElementById("startCamera");
const capturePhoto = document.getElementById("capturePhoto");
const retakePhoto = document.getElementById("retakePhoto");
const incidentImage = document.getElementById("incidentImage");
const photoStatus = document.getElementById("photoStatus");
const submitBtn = document.getElementById("submitBtn");

let stream = null;
let photoReady = false;
let locationReady = false;
let marker = null;

const map = L.map("map").setView([14.5995, 120.9842], 13);

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "&copy; OpenStreetMap contributors"
}).addTo(map);

function updateSubmitState() {
    submitBtn.disabled = !(photoReady && locationReady);
}

async function openCamera() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert("Camera access is not supported by this browser.");
            return;
        }

        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: "environment" },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        });

        video.srcObject = stream;
        video.style.display = "block";
        preview.style.display = "none";
        capturePhoto.disabled = false;
        retakePhoto.disabled = true;
        photoStatus.textContent = "Camera is ready.";
    } catch (error) {
        alert("Camera permission was denied or the camera is unavailable.");
        console.error(error);
    }
}

function capture() {
    if (!stream) return;

    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 720;

    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext("2d");
    context.drawImage(video, 0, 0, width, height);

    canvas.toBlob((blob) => {
        if (!blob) return;

        const file = new File(
            [blob],
            "incident-" + Date.now() + ".jpg",
            { type: "image/jpeg" }
        );

        const transfer = new DataTransfer();
        transfer.items.add(file);
        incidentImage.files = transfer.files;

        preview.src = URL.createObjectURL(blob);
        preview.style.display = "block";
        video.style.display = "none";

        photoReady = true;
        retakePhoto.disabled = false;
        photoStatus.textContent = "Photo captured successfully.";

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        updateSubmitState();
    }, "image/jpeg", 0.88);
}

startCamera.addEventListener("click", openCamera);
capturePhoto.addEventListener("click", capture);
retakePhoto.addEventListener("click", openCamera);

if (navigator.geolocation) {
    navigator.geolocation.watchPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lng;
            document.getElementById("location_accuracy").value = accuracy;

            document.getElementById("latText").textContent = lat.toFixed(6);
            document.getElementById("lngText").textContent = lng.toFixed(6);
            document.getElementById("accuracyText").textContent = "±" + Math.round(accuracy) + " meters";

            document.getElementById("locationStatus").textContent =
                "✅ Current location detected";

            document.getElementById("locationStatus").className =
                "fw-semibold text-success";

            const newPoint = [lat, lng];

            if (!marker) {
                marker = L.marker(newPoint)
                    .addTo(map)
                    .bindPopup("Your current location")
                    .openPopup();

                map.setView(newPoint, 17);
            } else {
                marker.setLatLng(newPoint);
            }

            locationReady = true;
            updateSubmitState();
        },
        (error) => {
            document.getElementById("locationStatus").textContent =
                "❌ Location unavailable. Please enable GPS/location permission.";

            document.getElementById("locationStatus").className =
                "fw-semibold text-danger";

            console.error(error);
        },
        {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 15000
        }
    );
} else {
    document.getElementById("locationStatus").textContent =
        "❌ Geolocation is not supported by this browser.";
}

document.getElementById("reportForm")?.addEventListener("submit", () => {
    submitBtn.disabled = true;
    submitBtn.textContent = "Sending report...";
});

window.addEventListener("beforeunload", () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
});
</script>

</body>
</html>
