
<?php
session_start();
require_once "database.php";

$message = "";
$messageType = "success";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Require an administrator login.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    strcasecmp($_SESSION["role"], "Admin") !== 0
) {
    header("Location: login.php");
    exit;
}

// Add a new health camp or update an existing camp's status.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "add_camp") {
            $campName = trim($_POST["camp_name"] ?? "");
            $location = trim($_POST["location"] ?? "");
            $campDate = trim($_POST["camp_date"] ?? "");
            $startTime = trim($_POST["start_time"] ?? "");
            $endTime = trim($_POST["end_time"] ?? "");
            $organizer = trim($_POST["organizer"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if (
                $campName === "" ||
                $location === "" ||
                $campDate === "" ||
                $startTime === "" ||
                $endTime === "" ||
                $organizer === ""
            ) {
                throw new RuntimeException(
                    "Please fill in all required fields."
                );
            }

            $date = DateTime::createFromFormat("!Y-m-d", $campDate);
            if (!$date || $date->format("Y-m-d") !== $campDate) {
                throw new RuntimeException("Please enter a valid camp date.");
            }

            // Check that the times are valid 24-hour times.
            if (
                !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime) ||
                !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime)
            ) {
                throw new RuntimeException(
                    "Please enter valid start and end times."
                );
            }

            // End time must be later than start time.
            if ($endTime <= $startTime) {
                throw new RuntimeException(
                    "End time must be later than start time. Example: 09:00 to 18:00."
                );
            }

            $stmt = $pdo->prepare(
                "INSERT INTO health_camps
                (camp_name, location, camp_date, start_time, end_time,
                 organizer, description, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Planned')"
            );

            $stmt->execute([
                $campName,
                $location,
                $campDate,
                $startTime,
                $endTime,
                $organizer,
                $description
            ]);

            header("Location: manage-camps.php?added=1");
            exit;
        }

        if ($action === "update_status") {
            $campId = filter_input(
                INPUT_POST,
                "camp_id",
                FILTER_VALIDATE_INT
            );
            $status = $_POST["status"] ?? "";

            if (!$campId || !in_array(
                $status,
                ["Planned", "Active", "Completed"],
                true
            )) {
                throw new RuntimeException(
                    "Invalid camp or status."
                );
            }

            $stmt = $pdo->prepare(
                "UPDATE health_camps SET status = ? WHERE camp_id = ?"
            );
            $stmt->execute([$status, $campId]);

            header("Location: manage-camps.php?updated=1");
            exit;
        }

        throw new RuntimeException("Invalid request.");
    } catch (Throwable $error) {
        $message = $error instanceof PDOException
            ? "The operation failed. Please check your database fields and try again."
            : $error->getMessage();

        $messageType = "error";
    }
}

if (isset($_GET["added"])) {
    $message = "Health camp added successfully!";
}
if (isset($_GET["updated"])) {
    $message = "Camp status updated successfully!";
}

// Retrieve all health camps.
$stmt = $pdo->query(
    "SELECT * FROM health_camps ORDER BY camp_date DESC, camp_id DESC"
);
$camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Camp Management</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef5fb;
            color: #23344d;
        }

        .page-header {
            background: linear-gradient(120deg, #075bbb, #0878e8);
            color: white;
            padding: 25px 6%;
        }

        .page-header h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }

        .page-header p { margin: 0; }

        .top-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
            margin-top: 20px;
        }

        .top-nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .container {
            max-width: 1150px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .panel {
            background: white;
            padding: 28px;
            margin-bottom: 28px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(30, 70, 120, .08);
        }

        .panel h2 {
            color: #075bbb;
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field.full { grid-column: 1 / -1; }

        label { font-weight: bold; }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd8e6;
            border-radius: 8px;
            background: #fff;
            color: #23344d;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            padding: 11px 17px;
            border: none;
            border-radius: 8px;
            background: #0878e8;
            color: white;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            text-align: center;
        }

        .btn:hover { background: #075bbb; }

        .btn-danger { background: #d94152; }
        .btn-secondary { background: #52657d; }

        .message {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .success {
            background: #dff7e8;
            color: #17683b;
        }

        .error {
            background: #ffe3e7;
            color: #9b2435;
        }

        .camp-card {
            padding: 22px;
            margin-top: 18px;
            border: 1px solid #e0e9f2;
            border-top: 4px solid #0878e8;
            border-radius: 12px;
            background: #fff;
        }

        .camp-card h3 {
            color: #075bbb;
            margin-top: 0;
        }

        .camp-details p {
            margin: 9px 0;
            overflow-wrap: anywhere;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .status-form select { width: auto; min-width: 145px; }

        .camp-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .empty {
            padding: 20px;
            background: #f5f9fd;
            border-radius: 8px;
        }

        @media (max-width: 650px) {
            .form-grid { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .panel { padding: 18px; }
            .page-header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1>Health Camp Management</h1>
    <p>Create camps, update their status, and manage camp information.</p>

    <nav class="top-nav">
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="register.php">Patient Registration</a>
        <a href="manage-queue.php">Patient Queue</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <?php if ($message !== ""): ?>
        <div class="message <?= e($messageType) ?>" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>＋ Add a New Health Camp</h2>
        <p>Enter the camp details below. Fields marked * are required.</p>

        <form method="post" action="manage-camps.php">
            <input type="hidden" name="action" value="add_camp">

            <div class="form-grid">
                <div class="field">
                    <label for="camp_name">Camp Name *</label>
                    <input
                        type="text"
                        id="camp_name"
                        name="camp_name"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="field">
                    <label for="location">Location *</label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="field">
                    <label for="camp_date">Camp Date *</label>
                    <input
                        type="date"
                        id="camp_date"
                        name="camp_date"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="organizer">Organizer *</label>
                    <input
                        type="text"
                        id="organizer"
                        name="organizer"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="field">
                    <label for="start_time">Start Time *</label>
                    <input
                        type="time"
                        id="start_time"
                        name="start_time"
                        required
                    >
                </div>

                <div class="field">
                    <label for="end_time">End Time *</label>
                    <input
                        type="time"
                        id="end_time"
                        name="end_time"
                        required
                    >
                </div>

                <div class="field full">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        maxlength="2000"
                        placeholder="Enter camp activities and services..."
                    ></textarea>
                </div>

                <div class="field full">
                    <button class="btn" type="submit">
                        + Add Health Camp
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Existing Health Camps</h2>

        <?php if (count($camps) === 0): ?>
            <p class="empty">
                No health camps have been added yet. Use the form above to
                create your first camp.
            </p>
        <?php else: ?>

            <?php foreach ($camps as $camp): ?>
                <article class="camp-card">
                    <h3><?= e($camp["camp_name"]) ?></h3>

                    <div class="camp-details">
                        <p>
                            <strong>Location:</strong>
                            <?= e($camp["location"]) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= e($camp["camp_date"]) ?>
                        </p>

                        <p>
                            <strong>Time:</strong>
                            <?= e(substr((string)$camp["start_time"], 0, 5)) ?>
                            –
                            <?= e(substr((string)$camp["end_time"], 0, 5)) ?>
                        </p>

                        <p>
                            <strong>Organizer:</strong>
                            <?= e($camp["organizer"]) ?>
                        </p>

                        <p>
                            <strong>Current status:</strong>
                            <?= e($camp["status"]) ?>
                        </p>

                        <?php if (!empty($camp["description"])): ?>
                            <p>
                                <strong>Description:</strong>
                                <?= nl2br(e($camp["description"])) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <form method="post"
                          action="manage-camps.php"
                          class="status-form">

                        <input type="hidden"
                               name="action"
                               value="update_status">

                        <input type="hidden"
                               name="camp_id"
                               value="<?= (int)$camp["camp_id"] ?>">

                        <label for="status-<?= (int)$camp["camp_id"] ?>">
                            Update Camp Status
                        </label>

                        <select
                            id="status-<?= (int)$camp["camp_id"] ?>"
                            name="status"
                        >
                            <?php foreach (
                                ["Planned", "Active", "Completed"] as $status
                            ): ?>
                                <option
                                    value="<?= e($status) ?>"
                                    <?= $camp["status"] === $status
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= e($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button class="btn" type="submit">
                            Update Status
                        </button>
                    </form>

                    <div class="camp-actions">
                        <a class="btn btn-secondary"
                           href="edit-camp.php?camp_id=<?= (int)$camp["camp_id"] ?>">
                            Edit Camp
                        </a>

                        <a class="btn"
                           href="camp-details.php">
                            View Public Camp Page
                        </a>

                        <form method="post"
                              action="delete-camp.php"
                              onsubmit="return confirm('Are you sure you want to delete this camp? This may fail if registrations are linked to it.');">

                            <input type="hidden"
                                   name="camp_id"
                                   value="<?= (int)$camp["camp_id"] ?>">

                            <button class="btn btn-danger" type="submit">
                                Delete Camp
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>

        <?php endif; ?>
    </section>

</main>

<script>
const startInput = document.getElementById("start_time");
const endInput = document.getElementById("end_time");

function validateCampTimes() {
    if (startInput.value && endInput.value) {
        endInput.setCustomValidity(
            endInput.value <= startInput.value
                ? "End time must be later than start time."
                : ""
        );
    } else {
        endInput.setCustomValidity("");
    }
}

startInput.addEventListener("change", validateCampTimes);
endInput.addEventListener("change", validateCampTimes);
</script>

</body>
</html>
