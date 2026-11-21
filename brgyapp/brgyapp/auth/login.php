<?php

session_start();

require_once __DIR__ . "/../config/database.php";

$error = "";

if (empty($_SESSION["login_csrf"])) {
    $_SESSION["login_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["login_csrf"], $csrf)) {

        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        $stmt = $pdo->prepare(
            "SELECT *
             FROM users
             WHERE username = :username
             LIMIT 1"
        );

        $stmt->execute([
            ":username" => $username
        ]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {

            $role = $user["role"];
            $status = strtolower($user["account_status"] ?? "");

            // Only approved accounts can log in.
            if ($status !== "approved") {

                if ($status === "pending") {

                    $error = "Your account is still waiting for administrator approval.";

                } elseif ($status === "rejected") {

                    $error = "Your registration was not approved. Please contact the Barangay Administrator.";

                } elseif ($status === "suspended") {

                    $error = "Your account has been suspended. Please contact the Barangay Administrator.";

                } else {

                    $error = "Your account is not active. Please contact the Barangay Administrator.";

                }

            } elseif (!in_array($role, ["admin", "staff", "citizen"], true)) {

                $error = "Your account does not have permission to access the system.";

            } else {

                // Regenerate session ID after successful authentication.
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $role;

                $_SESSION["login_csrf"] = bin2hex(random_bytes(32));

                // Redirect according to account type.
                if ($role === "citizen") {

                    header("Location: ../citizen/dashboard.php");
                    exit;

                } else {

                    header("Location: ../admin/dashboard.php");
                    exit;

                }
            }

        } else {

            $error = "Invalid username or password.";

        }

        // Refresh CSRF token after processing login.
        $_SESSION["login_csrf"] = bin2hex(random_bytes(32));
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Barangay Disaster Management System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --night: #05151a;
            --deep: #0b2f2a;
            --forest: #123d32;
            --green: #164b3b;
            --green-hover: #245b4b;
            --mint: #45a879;
            --alert: #f2b33d;
            --ink: #12231e;
            --muted: #5d6f69;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: "Sora", system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--ink);
            overflow-x: hidden;
            background:
                radial-gradient(ellipse at 20% 0%, #1b5446 0%, transparent 55%),
                linear-gradient(180deg, var(--night) 0%, var(--deep) 55%, var(--forest) 100%);
            background-attachment: fixed;
        }

        /* ---------- Disaster scene (background) ---------- */

        .scene {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        #rain {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .clouds {
            position: absolute;
            top: -12%;
            left: -10%;
            width: 120%;
            height: 45%;
            background:
                radial-gradient(ellipse 30% 55% at 15% 40%, rgba(12, 40, 38, .95), transparent 70%),
                radial-gradient(ellipse 35% 60% at 45% 30%, rgba(8, 28, 30, .95), transparent 70%),
                radial-gradient(ellipse 30% 55% at 80% 40%, rgba(12, 40, 38, .95), transparent 70%);
            filter: blur(14px);
        }

        .lightning {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 70% 0%, rgba(210, 240, 255, .55), transparent 60%);
            opacity: 0;
            animation: flash 11s infinite;
        }

        @keyframes flash {
            0%, 91%, 100% { opacity: 0; }
            92%           { opacity: .9; }
            93%           { opacity: .1; }
            94%           { opacity: .6; }
            97%           { opacity: 0; }
        }

        .quake {
            position: absolute;
            left: 0;
            right: 0;
            top: 34%;
            height: 90px;
            opacity: .35;
        }

        .quake path {
            fill: none;
            stroke: var(--mint);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 1400;
            stroke-dashoffset: 1400;
            animation: trace 9s linear infinite;
        }

        @keyframes trace {
            0%   { stroke-dashoffset: 1400; }
            70%  { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: 0; opacity: 0; }
        }

        .waves {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 34%;
        }

        .wave {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 200%;
            height: 100%;
        }

        .wave-1 { animation: drift 22s linear infinite; opacity: .55; }
        .wave-2 { animation: drift 15s linear infinite reverse; opacity: .75; height: 80%; }
        .wave-3 { animation: drift 10s linear infinite; height: 58%; }

        @keyframes drift {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ---------- Layout ---------- */

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 32px 0;
        }

        .brand-panel {
            color: #e8f3ef;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .brand-mark .badge-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(69, 168, 121, .18);
            border: 1px solid rgba(69, 168, 121, .45);
        }

        .brand-mark span {
            font-weight: 600;
            letter-spacing: .01em;
        }

        .brand-panel h1 {
            font-size: clamp(2rem, 4vw, 3.1rem);
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -.02em;
            margin-bottom: 18px;
        }

        .brand-panel p.lead {
            max-width: 30rem;
            color: #b9d3ca;
            font-size: 1rem;
            line-height: 1.7;
            font-weight: 400;
        }

        .hazards {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
            padding: 0;
            list-style: none;
        }

        .hazards li {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: .85rem;
            color: #d6e8e1;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            backdrop-filter: blur(4px);
        }

        .hazards svg {
            width: 16px;
            height: 16px;
            stroke: var(--alert);
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* ---------- Login card ---------- */

        .login-card {
            border: 0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow:
                0 30px 60px rgba(0, 0, 0, .45),
                0 0 0 1px rgba(255, 255, 255, .08);
            overflow: hidden;
        }

        .login-head {
            padding: 28px 32px 22px;
            background: linear-gradient(135deg, var(--forest), var(--green));
            color: #fff;
            border-bottom: 4px solid var(--alert);
        }

        .login-head h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .login-head p {
            margin: 0;
            color: #bfdcd1;
            font-size: .9rem;
        }

        .login-body {
            padding: 30px 32px 28px;
        }

        .form-label {
            font-size: .85rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .input-group-text {
            background: #f1f6f4;
            border: 1px solid #d5e1dc;
            border-right: 0;
            border-radius: 10px 0 0 10px;
            color: var(--muted);
        }

        .form-control {
            border: 1px solid #d5e1dc;
            border-radius: 0 10px 10px 0;
            padding: 12px 14px;
            font-size: .95rem;
        }

        .form-control:focus {
            border-color: var(--mint);
            box-shadow: 0 0 0 .2rem rgba(69, 168, 121, .18);
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--mint);
        }

        .toggle-pass {
            border: 1px solid #d5e1dc;
            border-left: 0;
            background: #fff;
            color: var(--muted);
            border-radius: 0 10px 10px 0;
            padding: 0 14px;
        }

        .toggle-pass:hover {
            color: var(--green);
        }

        .input-group .form-control.has-toggle {
            border-radius: 0;
            border-right: 0;
        }

        .input-group:focus-within .toggle-pass {
            border-color: var(--mint);
        }

        .btn-login {
            background: var(--green);
            border: 1px solid var(--green);
            color: #fff;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            transition: background .2s ease;
        }

        .btn-login:hover,
        .btn-login:focus-visible {
            background: var(--green-hover);
            border-color: var(--green-hover);
            color: #fff;
        }

        .btn-login:focus-visible,
        .btn-register-outline:focus-visible,
        .toggle-pass:focus-visible {
            outline: 3px solid rgba(69, 168, 121, .45);
            outline-offset: 2px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 24px 0 18px;
            color: var(--muted);
            font-size: .82rem;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #dbe5e1;
        }

        .btn-register-outline {
            display: block;
            width: 100%;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            color: var(--green);
            background: #f1f8f5;
            border: 1.5px solid var(--mint);
            text-decoration: none;
            transition: background .2s ease, color .2s ease;
        }

        .btn-register-outline:hover {
            background: var(--mint);
            color: #fff;
        }

        .approval-note {
            margin-top: 16px;
            font-size: .8rem;
            line-height: 1.5;
            color: var(--muted);
            text-align: center;
        }

        .alert {
            border-radius: 10px;
            font-size: .9rem;
        }

        @media (max-width: 991.98px) {
            .login-head,
            .login-body {
                padding-left: 24px;
                padding-right: 24px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .lightning,
            .wave,
            .quake path {
                animation: none;
            }

            .quake path {
                stroke-dashoffset: 0;
            }
        }

    </style>

</head>

<body>

<!-- Animated disaster scene -->
<div class="scene" aria-hidden="true">

    <div class="clouds"></div>

    <canvas id="rain"></canvas>

    <div class="lightning"></div>

    <svg class="quake" viewBox="0 0 1400 90" preserveAspectRatio="none">
        <path d="M0 45 L120 45 L150 44 L170 48 L190 20 L215 72 L240 8 L268 80 L292 30 L318 58 L345 42 L380 47
                 L520 45 L560 44 L590 50 L610 25 L635 66 L660 14 L690 76 L715 34 L742 52 L770 44 L810 46
                 L1000 45 L1040 44 L1065 49 L1085 28 L1110 62 L1135 18 L1160 70 L1188 36 L1215 50 L1250 45 L1400 45"/>
    </svg>

    <div class="waves">

        <svg class="wave wave-1" viewBox="0 0 2880 300" preserveAspectRatio="none">
            <path fill="#1d6a57"
                  d="M0 140 C 240 60, 480 220, 720 140 S 1200 60, 1440 140 S 1920 220, 2160 140 S 2640 60, 2880 140 L2880 300 L0 300 Z"/>
        </svg>

        <svg class="wave wave-2" viewBox="0 0 2880 300" preserveAspectRatio="none">
            <path fill="#154d41"
                  d="M0 120 C 200 200, 520 40, 720 120 S 1240 200, 1440 120 S 1960 40, 2160 120 S 2680 200, 2880 120 L2880 300 L0 300 Z"/>
        </svg>

        <svg class="wave wave-3" viewBox="0 0 2880 300" preserveAspectRatio="none">
            <path fill="#0b2f2a"
                  d="M0 100 C 260 30, 460 170, 720 100 S 1180 30, 1440 100 S 1900 170, 2160 100 S 2620 30, 2880 100 L2880 300 L0 300 Z"/>
        </svg>

    </div>

</div>

<main class="page">

    <div class="container">

        <div class="row align-items-center justify-content-center g-5">

            <!-- Brand / message -->
            <div class="col-lg-6 d-none d-lg-block">

                <div class="brand-panel">

                    <div class="brand-mark">

                        <div class="badge-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#45a879"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6l8-3z"/>
                                <path d="M12 8v4"/>
                                <circle cx="12" cy="15" r=".6" fill="#45a879"/>
                            </svg>
                        </div>

                        <span>Barangay DMS</span>

                    </div>

                    <h1>Stay ahead of every Hazard</h1>

                    <p class="lead">
                        Coordinate disaster reports, monitor affected residents,
                        and respond faster from one secure system for your barangay.
                    </p>

                    <ul class="hazards">

                        <li>
                            <svg viewBox="0 0 24 24"><path d="M3 9h12a3 3 0 1 0-3-3"/><path d="M3 13h16a3 3 0 1 1-3 3"/><path d="M3 17h8"/></svg>
                            Typhoon
                        </li>

                        <li>
                            <svg viewBox="0 0 24 24"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/></svg>
                            Flood
                        </li>

                        <li>
                            <svg viewBox="0 0 24 24"><path d="M2 12h4l2-6 4 12 3-9 2 3h5"/></svg>
                            Earthquake
                        </li>

                        <li>
                            <svg viewBox="0 0 24 24"><path d="M12 3c1 3 4 4.5 4 8a4 4 0 0 1-8 0c0-1.5.7-2.5 1.5-3.5C9.5 9 10 10 11 10c0-3 0-5 1-7z"/><path d="M6 20h12"/></svg>
                            Fire
                        </li>

                    </ul>

                </div>

            </div>

            <!-- Login card -->
            <div class="col-md-8 col-lg-5 col-xl-4">

                <div class="login-card">

                    <div class="login-head">

                        <h2>Welcome back</h2>

                        <p>Sign in to the Disaster Management System</p>

                    </div>

                    <div class="login-body">

                        <?php if ($error): ?>

                            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-1 flex-shrink-0">
                                    <circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16.5" r=".5"/>
                                </svg>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>

                        <?php endif; ?>

                        <form method="POST" autocomplete="on">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION["login_csrf"]) ?>"
                            >

                            <div class="mb-3">

                                <label class="form-label" for="username">
                                    Username
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>
                                        </svg>
                                    </span>

                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        class="form-control"
                                        autocomplete="username"
                                        required
                                        autofocus
                                    >

                                </div>

                            </div>

                            <div class="mb-4">

                                <label class="form-label" for="password">
                                    Password
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                        </svg>
                                    </span>

                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control has-toggle"
                                        autocomplete="current-password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="toggle-pass"
                                        id="togglePass"
                                        aria-label="Show password"
                                    >
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>

                                </div>

                            </div>

                            <button type="submit" class="btn btn-login w-100">
                                Sign in
                            </button>

                        </form>

                        <div class="divider">
                            New to the system?
                        </div>

                        <a href="register.php" class="btn-register-outline">
                            Create a citizen account
                        </a>

                        <p class="approval-note">
                            New accounts are reviewed and approved by the Barangay Administrator before you can sign in.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>

<script>

    // Show / hide password
    (function () {
        var btn = document.getElementById("togglePass");
        var input = document.getElementById("password");

        btn.addEventListener("click", function () {
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
        });
    })();

    // Rain
    (function () {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        var canvas = document.getElementById("rain");
        var ctx = canvas.getContext("2d");
        var drops = [];
        var w, h;

        function resize() {
            var dpr = window.devicePixelRatio || 1;
            w = window.innerWidth;
            h = window.innerHeight;
            canvas.width = w * dpr;
            canvas.height = h * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            var count = Math.min(220, Math.floor(w / 6));
            drops = [];

            for (var i = 0; i < count; i++) {
                drops.push({
                    x: Math.random() * (w + 200) - 100,
                    y: Math.random() * h,
                    len: 10 + Math.random() * 18,
                    speed: 9 + Math.random() * 9,
                    alpha: 0.12 + Math.random() * 0.25
                });
            }
        }

        function draw() {
            ctx.clearRect(0, 0, w, h);
            ctx.lineWidth = 1.2;
            ctx.lineCap = "round";

            for (var i = 0; i < drops.length; i++) {
                var d = drops[i];

                ctx.strokeStyle = "rgba(190, 225, 235, " + d.alpha + ")";
                ctx.beginPath();
                ctx.moveTo(d.x, d.y);
                ctx.lineTo(d.x - d.len * 0.28, d.y + d.len);
                ctx.stroke();

                d.y += d.speed;
                d.x -= d.speed * 0.28;

                if (d.y > h || d.x < -50) {
                    d.y = -d.len;
                    d.x = Math.random() * (w + 200);
                }
            }

            requestAnimationFrame(draw);
        }

        window.addEventListener("resize", resize);
        resize();
        draw();
    })();

</script>

</body>
</html>