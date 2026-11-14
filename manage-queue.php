
<?php
session_start();
require_once __DIR__ . "/database.php";

// Keep the queue available only to authorized staff.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["Admin", "Doctor", "Volunteer"], true)
) {
    header("Location: login.php");
    exit;
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$message = "";
$error = "";

// Update queue status.
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_status"])) {

    $registrationId = filter_input(
        INPUT_POST,
        "registration_id",
        FILTER_VALIDATE_INT
    );

    $newStatus = $_POST["queue_status"] ?? "";

    $allowedStatuses = [
        "Waiting",
        "In Consultation",
        "Completed"
    ];

    if (
        !$registrationId ||
        !in_array($newStatus, $allowedStatuses, true)
    ) {
        $error = "Please select a valid patient and queue status.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "UPDATE registrations
                 SET queue_status = ?
                 WHERE registration_id = ?"
            );
            $stmt->execute([$newStatus, $registrationId]);

            // Confirm the registration exists, even if its status
            // was already the selected value.
            $check = $pdo->prepare(
                "SELECT registration_id
                 FROM registrations
                 WHERE registration_id = ?"
            );
            $check->execute([$registrationId]);

            if ($check->fetch()) {
                header("Location: manage-queue.php?updated=1");
                exit;
            }

            $error = "Patient registration was not found.";
        } catch (PDOException $e) {
            $error = "Unable to update the queue. Please try again.";
        }
    }
}

if (isset($_GET["updated"])) {
    $message = "Queue status updated successfully.";
}

// Search and filter inputs.
$search = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$campFilter = filter_input(
    INPUT_GET,
    "camp_id",
    FILTER_VALIDATE_INT
);

$validStatuses = ["Waiting", "In Consultation", "Completed"];

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = "";
}

if (!$campFilter || $campFilter < 1) {
    $campFilter = null;
}

$registrations = [];
$camps = [];
$totalDisplayed = 0;

try {
    $camps = $pdo->query(
        "SELECT camp_id, camp_name, camp_date
         FROM health_camps
         ORDER BY camp_date DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            r.registration_id,
            r.token_number,
            r.registration_type,
            r.queue_status,
            p.full_name,
            p.age,
            p.gender,
            p.phone,
            c.camp_id,
            c.camp_name,
            c.location,
            c.camp_date
        FROM registrations r
        INNER JOIN patients p
            ON r.patient_id = p.patient_id
        INNER JOIN health_camps c
            ON r.camp_id = c.camp_id
        WHERE 1 = 1
    ";

    $params = [];

    // Search by patient name or token number.
    if ($search !== "") {
        $sql .= " AND (
            p.full_name LIKE ?
            OR CAST(r.token_number AS CHAR) = ?
        )";

        $params[] = "%" . $search . "%";
        $params[] = $search;
    }

    if ($statusFilter !== "") {
        $sql .= " AND r.queue_status = ?";
        $params[] = $statusFilter;
    }

    if ($campFilter !== null) {
        $sql .= " AND c.camp_id = ?";
        $params[] = $campFilter;
    }

    $sql .= "
        ORDER BY
            c.camp_date ASC,
            r.token_number ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalDisplayed = count($registrations);

} catch (PDOException $e) {
    $error = "Unable to load queue records. Check your database tables and columns.";
}

// Counts across all registrations, independent of current filters.
$counts = [
    "Waiting" => 0,
    "In Consultation" => 0,
    "Completed" => 0
];

try {
    $countRows = $pdo->query(
        "SELECT queue_status, COUNT(*) AS total
         FROM registrations
         GROUP BY queue_status"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($countRows as $countRow) {
        if (isset($counts[$countRow["queue_status"]])) {
            $counts[$countRow["queue_status"]] =
                (int)$countRow["total"];
        }
    }
} catch (PDOException $e) {
    // The main queue error is displayed above if applicable.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Queue Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    color: #23364d;
    background: #f0f6fc;
}

header {
    background: linear-gradient(120deg, #075bbb, #0878e8);
    color: white;
    padding: 20px 5%;
}

.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

header h1 {
    margin: 0;
    font-size: 24px;
}

nav {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 18px;
}

nav a {
    color: white;
    text-decoration: none;
    font-weight: bold;
}

nav a:hover {
    text-decoration: underline;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-heading h2 {
    color: #124c91;
    margin-bottom: 8px;
}

.page-heading p {
    color: #64748b;
    line-height: 1.6;
}

.notice {
    padding: 14px 16px;
    border-radius: 9px;
    margin: 18px 0;
    background: #dcfce7;
    color: #166534;
}

.notice.error {
    background: #fee2e2;
    color: #991b1b;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin: 24px 0;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 14px;
    border-top: 5px solid #e6a23c;
    box-shadow: 0 5px 18px rgba(20, 50, 90, .06);
}

.summary-card.consultation {
    border-top-color: #2783df;
}

.summary-card.completed {
    border-top-color: #19a36b;
}

.summary-card p {
    color: #64748b;
    margin: 0 0 10px;
}

.summary-number {
    color: #163e70;
    font-size: 30px;
    font-weight: bold;
}

.panel {
    background: white;
    padding: 24px;
    margin-bottom: 24px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(20, 50, 90, .06);
}

.panel h3 {
    margin-top: 0;
    color: #164b87;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr auto auto;
    align-items: end;
    gap: 14px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: bold;
}

.field input,
.field select {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #cbd8e6;
    border-radius: 8px;
    background: white;
    color: #23364d;
    font-size: 15px;
}

.field input:focus,
.field select:focus {
    outline: 3px solid #dceeff;
    border-color: #0878e8;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 43px;
    padding: 10px 15px;
    border: 0;
    border-radius: 8px;
    background: #0878e8;
    color: white;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.btn:hover {
    background: #075bbb;
}

.btn-light {
    background: #e7f2ff;
    color: #075bbb;
}

.btn-light:hover {
    background: #d5e9ff;
}

.patient-card {
    background: white;
    padding: 22px;
    margin-bottom: 18px;
    border: 1px solid #e2eaf3;
    border-left: 5px solid #0878e8;
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(20, 50, 90, .05);
}

.patient-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.patient-top h3 {
    color: #075bbb;
    margin: 0;
    overflow-wrap: anywhere;
}

.token {
    display: inline-block;
    padding: 8px 13px;
    border-radius: 25px;
    background: #e7f2ff;
    color: #075bbb;
    font-weight: bold;
}

.patient-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 20px 0;
}

.detail {
    padding: 12px;
    border-radius: 8px;
    background: #f5f9fe;
    overflow-wrap: anywhere;
}

.detail strong {
    display: block;
    margin-bottom: 5px;
    color: #52657b;
    font-size: 13px;
}

.status-badge {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

.status-waiting {
    background: #fff0c2;
    color: #855900;
}

.status-consultation {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-completed {
    background: #dcfce7;
    color: #166534;
}

.update-form {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 12px;
    padding-top: 18px;
    border-top: 1px solid #e5edf5;
}

.update-form .field {
    flex: 1;
    min-width: 190px;
}

.empty-state {
    background: white;
    padding: 35px 20px;
    border-radius: 12px;
    text-align: center;
}

.results-count {
    color: #64748b;
    margin-bottom: 18px;
}

@media (max-width: 900px) {
    .filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filter-grid .field:first-child {
        grid-column: 1 / -1;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .container {
        margin: 20px auto;
        padding: 0 12px;
    }

    .panel,
    .patient-card {
        padding: 17px;
    }

    .filter-grid,
    .summary-grid,
    .patient-details {
        grid-template-columns: 1fr;
    }

    .filter-grid .field:first-child {
        grid-column: auto;
    }

    .filter-grid .btn {
        width: 100%;
    }

    .update-form {
        align-items: stretch;
        flex-direction: column;
    }

    .update-form .field {
        min-width: 0;
        width: 100%;
    }

    .update-form .btn {
        width: 100%;
    }

    header h1 {
        font-size: 21px;
    }
}
</style>
</head>

<body>

<header>
    <div class="header-row">
        <h1>♥ Smart Community Health Camp</h1>
        <span>Patient Queue Management</span>
    </div>

    <nav>
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="register.php">Patient Registration</a>
        <a href="queue-status.php">Queue Status</a>
        <a href="reports.php">Reports</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <section class="page-heading">
        <h2>Patient Queue</h2>
        <p>Search by patient name or token, filter registrations, and update consultation status.</p>
    </section>

    <?php if ($message !== ""): ?>
        <div class="notice" role="status"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="notice error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card">
            <p>Waiting</p>
            <div class="summary-number"><?= $counts["Waiting"] ?></div>
        </div>

        <div class="summary-card consultation">
            <p>In Consultation</p>
            <div class="summary-number"><?= $counts["In Consultation"] ?></div>
        </div>

        <div class="summary-card completed">
            <p>Completed</p>
            <div class="summary-number"><?= $counts["Completed"] ?></div>
        </div>
    </section>

    <section class="panel">
        <h3>Search and Filter Patients</h3>

        <form method="get" action="manage-queue.php" class="filter-grid">
            <div class="field">
                <label for="search">Patient Name or Token Number</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Enter name or token number"
                    value="<?= h($search) ?>"
                >
            </div>

            <div class="field">
                <label for="status">Queue Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($validStatuses as $option): ?>
                        <option value="<?= h($option) ?>"
                            <?= $statusFilter === $option ? "selected" : "" ?>>
                            <?= h($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="camp_id">Health Camp</label>
                <select id="camp_id" name="camp_id">
                    <option value="">All camps</option>
                    <?php foreach ($camps as $camp): ?>
                        <option value="<?= (int)$camp["camp_id"] ?>"
                            <?= $campFilter === (int)$camp["camp_id"] ? "selected" : "" ?>>
                            <?= h($camp["camp_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">Search</button>
            <a href="manage-queue.php" class="btn btn-light">Clear</a>
        </form>
    </section>

    <p class="results-count">
        Showing <strong><?= $totalDisplayed ?></strong> matching registration(s).
    </p>

    <?php if (count($registrations) === 0): ?>
        <section class="empty-state">
            <h3>No matching patients found</h3>
            <p>Try another name, token number, camp, or status filter.</p>
            <a href="manage-queue.php" class="btn">Show All Patients</a>
        </section>
    <?php else: ?>

        <?php foreach ($registrations as $patient): ?>
            <?php
                $statusClass = "status-waiting";

                if ($patient["queue_status"] === "In Consultation") {
                    $statusClass = "status-consultation";
                } elseif ($patient["queue_status"] === "Completed") {
                    $statusClass = "status-completed";
                }
            ?>

            <article class="patient-card">
                <div class="patient-top">
                    <h3><?= h($patient["full_name"]) ?></h3>
                    <span class="token">Token #<?= (int)$patient["token_number"] ?></span>
                </div>

                <div class="patient-details">
                    <div class="detail">
                        <strong>Health Camp</strong>
                        <?= h($patient["camp_name"]) ?>
                    </div>

                    <div class="detail">
                        <strong>Camp Date</strong>
                        <?= h($patient["camp_date"]) ?>
                    </div>

                    <div class="detail">
                        <strong>Location</strong>
                        <?= h($patient["location"]) ?>
                    </div>

                    <div class="detail">
                        <strong>Age / Gender</strong>
                        <?= h($patient["age"]) ?> / <?= h($patient["gender"]) ?>
                    </div>

                    <div class="detail">
                        <strong>Registration Type</strong>
                        <?= h($patient["registration_type"]) ?>
                    </div>

                    <div class="detail">
                        <strong>Current Queue Status</strong>
                        <span class="status-badge <?= h($statusClass) ?>">
                            <?= h($patient["queue_status"]) ?>
                        </span>
                    </div>
                </div>

                <form method="post" action="manage-queue.php" class="update-form">
                    <input
                        type="hidden"
                        name="registration_id"
                        value="<?= (int)$patient["registration_id"] ?>"
                    >

                    <div class="field">
                        <label for="queue-status-<?= (int)$patient["registration_id"] ?>">
                            Change Status
                        </label>

                        <select
                            id="queue-status-<?= (int)$patient["registration_id"] ?>"
                            name="queue_status"
                            required
                        >
                            <?php foreach ($validStatuses as $option): ?>
                                <option value="<?= h($option) ?>"
                                    <?= $patient["queue_status"] === $option ? "selected" : "" ?>>
                                    <?= h($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button
                        type="submit"
                        name="update_status"
                        value="1"
                        class="btn"
                    >
                        Update Status
                    </button>
                </form>
            </article>
        <?php endforeach; ?>

    <?php endif; ?>

</main>
</body>
</html>
