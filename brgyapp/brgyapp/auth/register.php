
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

$error = "";
$success = "";

if (empty($_SESSION["register_csrf"])) {
    $_SESSION["register_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["register_csrf"], $csrf)) {
        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $full_name = trim($_POST["full_name"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $contact_number = trim($_POST["contact_number"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if (
            $full_name === "" ||
            $username === "" ||
            $email === "" ||
            $contact_number === "" ||
            $address === "" ||
            $password === "" ||
            $confirm_password === ""
        ) {
            $error = "Please complete all required fields.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";

        } elseif (strlen($username) < 4) {
            $error = "Username must be at least 4 characters.";

        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";

        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";

        } else {

            try {

                $check = $pdo->prepare(
                    "SELECT id FROM users
                     WHERE username = :username OR email = :email
                     LIMIT 1"
                );

                $check->execute([
                    ":username" => $username,
                    ":email" => $email
                ]);

                if ($check->fetch()) {

                    $error = "Username or email is already registered.";

                } else {

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare(
                        "INSERT INTO users
                        (
                            full_name,
                            username,
                            email,
                            contact_number,
                            address,
                            password,
                            role,
                            account_status
                        )
                        VALUES
                        (
                            :full_name,
                            :username,
                            :email,
                            :contact_number,
                            :address,
                            :password,
                            'citizen',
                            'pending'
                        )"
                    );

                    $stmt->execute([
                        ":full_name" => $full_name,
                        ":username" => $username,
                        ":email" => $email,
                        ":contact_number" => $contact_number,
                        ":address" => $address,
                        ":password" => $hashed_password
                    ]);

                    $success = "Registration submitted successfully! Your account is now pending administrator approval.";

                    $_SESSION["register_csrf"] = bin2hex(random_bytes(32));
                }

            } catch (PDOException $e) {

                error_log("Citizen registration error: " . $e->getMessage());

                $error = "Registration could not be completed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Citizen Registration | Barangay DMS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f7f5;
        }

        .register-card {
            border: 0;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        .register-header {
            background: #123d32;
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }

        .btn-register {
            background: #164b3b;
            border-color: #164b3b;
            color: white;
        }

        .btn-register:hover {
            background: #245b4b;
            border-color: #245b4b;
            color: white;
        }

        .form-control {
            border-radius: 8px;
            padding: 11px;
        }

        .form-control:focus {
            border-color: #45a879;
            box-shadow: 0 0 0 .2rem rgba(69,168,121,.15);
        }

    </style>

</head>

<body>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-8 col-lg-6">

            <div class="card register-card">

                <div class="register-header">

                    <h3 class="fw-bold mb-1">
                        Barangay DMS
                    </h3>

                    <p class="mb-0">
                        Citizen Account Registration
                    </p>

                </div>

                <div class="card-body p-4">

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

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION["register_csrf"]) ?>"
                        >

                        <div class="mb-3">

                            <label class="form-label">
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                maxlength="150"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Username
                            </label>

                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                minlength="4"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="150"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Contact Number
                            </label>

                            <input
                                type="tel"
                                name="contact_number"
                                class="form-control"
                                maxlength="30"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Complete Address
                            </label>

                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                                required
                            ></textarea>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="8"
                                required
                            >

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                class="form-control"
                                minlength="8"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-register w-100 py-2"
                        >
                            Create Account
                        </button>

                    </form>

                    <hr>

                    <div class="text-center">

                        <p class="mb-1">
                            Already have an account?
                        </p>

                        <a href="login.php" class="text-success fw-bold">
                            Login here
                        </a>

                    </div>

                    <div class="alert alert-info mt-3 mb-0">

                        <small>
                            Your account must be approved by the Barangay Administrator before you can access citizen reporting.
                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
