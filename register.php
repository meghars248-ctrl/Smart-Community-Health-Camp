
<?php
require_once "database.php";

$message = "";
$success = false;
$token = null;
$selectedCampId = filter_input(INPUT_GET, "camp_id", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedCampId = filter_input(INPUT_POST, "camp_id", FILTER_VALIDATE_INT);
    $name = trim($_POST["full_name"] ?? "");
    $age = filter_input(INPUT_POST, "age", FILTER_VALIDATE_INT);
    $gender = $_POST["gender"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $type = $_POST["registration_type"] ?? "Online";

    if (
        !$selectedCampId ||
        $name === "" ||
        strlen($name) > 100 ||
        $age === false || $age === null || $age < 0 || $age > 120 ||
        !in_array($gender, ["Female", "Male", "Other"], true) ||
        !preg_match('/^[0-9+\-\s]{7,20}$/', $phone) ||
        !in_array($type, ["Online", "On-Spot"], true)
    ) {
        $message = "Please complete all fields with valid details.";
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the camp record while checking availability.
            $stmt = $pdo->prepare(
                "SELECT camp_id
                 FROM health_camps
                 WHERE camp_id = ?
                   AND status IN ('Planned', 'Active')
                   AND camp_date >= CURDATE()
                 FOR UPDATE"
            );
            $stmt->execute([$selectedCampId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException(
                    "This camp is not currently available for registration."
                );
            }

            // Save patient information.
            $stmt = $pdo->prepare(
                "INSERT INTO patients (full_name, age, gender, phone)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $age, $gender, $phone]);

            $patientId = (int) $pdo->lastInsertId();

            // Generate the next token for this camp.
            $stmt = $pdo->prepare(
                "SELECT COALESCE(MAX(token_number), 0) + 1
                 FROM registrations
                 WHERE camp_id = ?"
            );
            $stmt->execute([$selectedCampId]);
            $token = (int) $stmt->fetchColumn();

            // Store the registration and token.
            $stmt = $pdo->prepare(
                "INSERT INTO registrations
                 (patient_id, camp_id, token_number, registration_type)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $patientId,
                $selectedCampId,
                $token,
                $type
            ]);

            $pdo->commit();
            $success = true;
            $message = "Your registration was successful!";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : "Registration could not be completed. Please try again.";
        }
    }
}

// Load camps that are open for registration.
$stmt = $pdo->query(
    "SELECT camp_id, camp_name, location, camp_date, status
     FROM health_camps
     WHERE status IN ('Planned', 'Active')
       AND camp_date >= CURDATE()
     ORDER BY camp_date ASC"
);
$camps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$validCampIds = array_map(
    static fn($camp) => (int) $camp["camp_id"],
    $camps
);

if (
    !$success &&
    $selectedCampId &&
    !in_array($selectedCampId, $validCampIds, true)
) {
    $selectedCampId = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Registration | Smart Community Health Camp</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body.registration-page {
            margin: 0;
            min-height: 100vh;
            background: #f0f7ff;
            color: #183153;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .registration-hero {
            padding: 38px 20px;
            text-align: center;
            color: #fff;
            background: linear-gradient(120deg, #075bb5, #0878e8, #12a6cb);
        }

        .registration-hero h1 {
            margin: 0 0 10px;
            font-size: clamp(28px, 4vw, 40px);
        }

        .registration-hero p {
            margin: 0;
            line-height: 1.6;
        }

        .registration-container {
            width: 100%;
            max-width: 850px;
            margin: 32px auto;
            padding: 0 20px;
        }

        .registration-card {
            width: 100%;
            padding: 32px;
            background: #fff;
            border: 1px solid #dce8f5;
            border-top: 5px solid #0878e8;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(15, 65, 120, 0.09);
        }

        .registration-card h2 {
            margin: 0 0 12px;
            color: #075bb5;
            font-size: 28px;
        }

        .registration-description {
            margin: 0 0 26px;
            color: #52657c;
            line-height: 1.7;
        }

        .registration-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 20px;
            width: 100%;
        }

        .form-group {
            width: 100%;
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #183153;
            font-size: 15px;
            font-weight: 700;
        }

        .form-group input,
        .form-group select {
            display: block;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            min-height: 49px;
            padding: 12px 14px;
            border: 1px solid #a9bfd6;
            border-radius: 10px;
            background: #fff;
            color: #183153;
            font-family: inherit;
            font-size: 16px;
            opacity: 1;
        }

        .form-group input::placeholder {
            color: #78899e;
            opacity: 1;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: 2px solid #b8dcff;
            border-color: #0878e8;
        }

        .registration-submit {
            display: block;
            width: 100%;
            min-height: 52px;
            padding: 14px 18px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(120deg, #0878e8, #075bb5);
            color: #fff;
            font-family: inherit;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .registration-submit:hover {
            background: #064d96;
        }

        .registration-message {
            margin-bottom: 22px;
            padding: 15px 18px;
            border: 1px solid #f1d49a;
            border-radius: 10px;
            background: #fff4df;
            color: #805600;
            line-height: 1.6;
        }

        .registration-message.success {
            border-color: #b8e9cc;
            background: #e5f7ed;
            color: #087443;
        }

        .token-display {
            margin-top: 22px;
            padding: 28px 20px;
            border: 2px dashed #0878e8;
            border-radius: 16px;
            background: linear-gradient(135deg, #eaf4ff, #f2fbff);
            text-align: center;
        }

        .token-display h3 {
            margin: 0 0 10px;
            color: #183153;
        }

        .token-number {
            display: block;
            margin: 12px 0;
            color: #075bb5;
            font-size: 54px;
            font-weight: 800;
        }

        .token-display p {
            color: #52657c;
            line-height: 1.6;
        }

        .registration-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 24px;
        }

        .registration-links a {
            display: inline-block;
            padding: 12px 16px;
            border-radius: 9px;
            background: #e8f3ff;
            color: #075bb5;
            font-weight: 700;
            text-decoration: none;
        }

        .registration-links a:hover {
            background: #d4e9ff;
        }

        .no-camps {
            padding: 20px;
            border-radius: 12px;
            background: #f4f8fc;
            color: #52657c;
            line-height: 1.7;
        }

        .no-camps a {
            color: #075bb5;
        }

        .registration-footer {
            padding: 20px;
            color: #52657c;
            text-align: center;
        }

        @media (max-width: 600px) {
            .registration-hero {
                padding: 30px 16px;
            }

            .registration-container {
                margin: 20px auto;
                padding: 0 12px;
            }

            .registration-card {
                padding: 23px 17px;
                border-radius: 14px;
            }

            .registration-card h2 {
                font-size: 24px;
            }

            .registration-form {
                grid-template-columns: minmax(0, 1fr);
                gap: 18px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .token-number {
                font-size: 42px;
            }

            .registration-links {
                flex-direction: column;
            }

            .registration-links a {
                text-align: center;
            }
        }
    </style>
</head>

<body class="registration-page">

<header>
    <h2>♥ Smart Community Health Camp</h2>
    <nav>
        <a href="index.php">Home</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="register.php">Register</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<section class="registration-hero">
    <h1>Patient Registration</h1>
    <p>Register for a community health camp and receive your queue token.</p>
</section>

<main class="registration-container">
    <section class="registration-card">

        <?php if ($success): ?>

            <div class="registration-message success" role="status">
                <strong>✓ <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></strong>
            </div>

            <div class="token-display">
                <h3>Your Registration Token</h3>
                <p>Please keep this number for your health camp visit.</p>

                <span class="token-number">
                    <?= (int) $token ?>
                </span>

                <p>Show this token to the camp staff when your turn arrives.</p>
            </div>

            <div class="registration-links">
                <a href="queue-status.php">Check Queue Status</a>
                <a href="camp-details.php">View Health Camps</a>
                <a href="index.php">Back to Home</a>
            </div>

        <?php else: ?>

            <h2>Register for a Health Camp</h2>

            <p class="registration-description">
                Enter the patient's details below. Fields marked with *
                are required.
            </p>

            <?php if ($message !== ""): ?>
                <div class="registration-message" role="alert">
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <?php if (count($camps) > 0): ?>

                <form
                    class="registration-form"
                    method="post"
                    action="register.php"
                >

                    <div class="form-group full-width">
                        <label for="full_name">Patient Full Name *</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            maxlength="100"
                            autocomplete="name"
                            placeholder="Enter patient's full name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="age">Age *</label>
                        <input
                            type="number"
                            id="age"
                            name="age"
                            min="0"
                            max="120"
                            placeholder="Enter age"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="phone">Phone Number *</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            maxlength="20"
                            autocomplete="tel"
                            placeholder="Enter phone number"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <label for="camp_id">Select Health Camp *</label>
                        <select id="camp_id" name="camp_id" required>
                            <option value="">Choose a camp</option>

                            <?php foreach ($camps as $camp): ?>
                                <option
                                    value="<?= (int) $camp["camp_id"] ?>"
                                    <?= $selectedCampId === (int) $camp["camp_id"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($camp["camp_name"], ENT_QUOTES, "UTF-8") ?>
                                    — <?= htmlspecialchars($camp["location"], ENT_QUOTES, "UTF-8") ?>
                                    (<?= htmlspecialchars($camp["camp_date"], ENT_QUOTES, "UTF-8") ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="registration_type">Registration Type *</label>
                        <select
                            id="registration_type"
                            name="registration_type"
                            required
                        >
                            <option value="Online">Online Registration</option>
                            <option value="On-Spot">On-Spot Registration</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <button class="registration-submit" type="submit">
                            Register and Get Token →
                        </button>
                    </div>

                </form>

            <?php else: ?>

                <div class="no-camps">
                    <strong>No camps are available for registration.</strong>
                    <p>Please check the camp dates or contact the camp team.</p>
                    <a href="camp-details.php">View Camp Details</a>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>
</main>

<footer class="registration-footer">
    <p>Smart Community Health Camp Monitoring System</p>
</footer>

</body>
</html>
