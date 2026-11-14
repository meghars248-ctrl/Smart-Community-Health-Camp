
<?php
session_start();

// Require login before displaying the dashboard.
if (!isset($_SESSION["user_id"], $_SESSION["role"])) {
    header("Location: login.php");
    exit;
}

// Connect to the database.
require_once __DIR__ . "/database.php";

$role = $_SESSION["role"];
$name = $_SESSION["full_name"] ?? "User";

// Dashboard counts.
$totalCamps = 0;
$totalPatients = 0;
$waitingPatients = 0;
$totalMedicines = 0;
$dbError = false;

try {
    $totalCamps = (int)$pdo->query(
        "SELECT COUNT(*) FROM health_camps"
    )->fetchColumn();

    $totalPatients = (int)$pdo->query(
        "SELECT COUNT(*) FROM patients"
    )->fetchColumn();

    $waitingPatients = (int)$pdo->query(
        "SELECT COUNT(*) FROM registrations
         WHERE queue_status = 'Waiting'"
    )->fetchColumn();

    // If the medicines table/column differs, this query may fail.
    $totalMedicines = (int)$pdo->query(
        "SELECT COALESCE(SUM(quantity), 0) FROM medicines"
    )->fetchColumn();

} catch (PDOException $e) {
    // Keep the dashboard available if a statistics query fails.
    $dbError = true;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Camp Dashboard</title>
<link rel="stylesheet" href="css/style.css">
<style>
.dashboard-page {
    min-height: 100vh;
    background: #f0f7ff;
    color: #17365d;
}
.dashboard-main {
    max-width: 1250px;
    margin: 0 auto;
    padding: 28px 20px;
}
.welcome-card {
    background: #fff;
    border-radius: 18px;
    padding: 28px;
    margin-bottom: 28px;
    box-shadow: 0 5px 20px rgba(20,60,100,.08);
}
.welcome-card h1 {
    margin-top: 0;
    color: #075fba;
}
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin: 20px 0 32px;
}
.stat-card {
    background: #fff;
    padding: 22px;
    border-radius: 15px;
    border-top: 5px solid #087fe5;
    box-shadow: 0 5px 18px rgba(20,60,100,.09);
}
.stat-card.patients { border-top-color: #16a085; }
.stat-card.waiting { border-top-color: #f0a020; }
.stat-card.medicines { border-top-color: #8b5cf6; }
.stat-card h3 {
    margin: 0 0 12px;
    font-size: 15px;
    color: #52657a;
}
.stat-number {
    margin: 0;
    font-size: 32px;
    font-weight: bold;
    color: #123b70;
}
.section-title { margin: 20px 0; }
.dashboard-links {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}
.dashboard-link {
    display: block;
    padding: 24px;
    border-radius: 15px;
    background: #fff;
    border-top: 5px solid #087fe5;
    box-shadow: 0 5px 18px rgba(20,60,100,.08);
    text-decoration: none;
    color: #17365d;
    transition: transform .2s ease;
}
.dashboard-link:hover { transform: translateY(-4px); }
.dashboard-link h3 { color: #075fba; margin: 8px 0; }
.dashboard-link p { color: #52657a; margin-bottom: 0; }
.dashboard-message {
    background: #fff4d6;
    color: #755500;
    padding: 12px 16px;
    border-radius: 8px;
    margin: 15px 0;
}
@media (max-width: 900px) {
    .dashboard-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dashboard-links { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 550px) {
    .dashboard-main { padding: 16px 12px; }
    .welcome-card { padding: 20px; }
    .dashboard-stats, .dashboard-links { grid-template-columns: 1fr; }
}
</style>
</head>
<body class="dashboard-page">

<header>
    <h2>Smart Community Health Camp</h2>
    <nav>
        <a href="index.php">Home</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="register.php">Patient Registration</a>
        <a href="queue-status.php">Queue Status</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="dashboard-main">

    <section class="welcome-card">
        <h1>Welcome, <?= h($name) ?>!</h1>
        <p>Your role: <strong><?= h($role) ?></strong></p>
        <p>Manage community health camp activities from one place.</p>
    </section>

    <?php if ($dbError): ?>
        <p class="dashboard-message">
            Some statistics could not be loaded. Check your database
            table and column names.
        </p>
    <?php endif; ?>

    <section class="dashboard-stats">
        <div class="stat-card">
            <h3> Total Health Camps</h3>
            <p class="stat-number"><?= $totalCamps ?></p>
        </div>

        <div class="stat-card patients">
            <h3> Registered Patients</h3>
            <p class="stat-number"><?= $totalPatients ?></p>
        </div>

        <div class="stat-card waiting">
            <h3> Patients Waiting</h3>
            <p class="stat-number"><?= $waitingPatients ?></p>
        </div>

        <div class="stat-card medicines">
            <h3> Medicine Stock</h3>
            <p class="stat-number"><?= $totalMedicines ?></p>
            <p>Units in inventory</p>
        </div>
    </section>

    <?php if ($role === "Admin"): ?>
        <h2 class="section-title">Administration</h2>
        <section class="dashboard-links">
            <a class="dashboard-link" href="manage-camps.php">
                <h3> Manage Health Camps</h3>
                <p>Create, update and manage camp information.</p>
            </a>
            <a class="dashboard-link" href="manage-queue.php">
                <h3> Patient Queue</h3>
                <p>Manage patient consultation status.</p>
            </a>
            <a class="dashboard-link" href="manage-users.php">
                <h3> Manage Users</h3>
                <p>Manage system user accounts.</p>
            </a>
            <a class="dashboard-link" href="manage-medicines.php">
                <h3> Medicine Inventory</h3>
                <p>Review medicines and update stock.</p>
            </a>
            <a class="dashboard-link" href="reports.php">
                <h3> Reports</h3>
                <p>Review camp and registration reports.</p>
            </a>
            <a class="dashboard-link" href="volunteer-list.php">
                <h3> Volunteer List</h3>
                <p>View volunteer information.</p>
            </a>
        </section>

    <?php elseif ($role === "Doctor"): ?>
        <h2 class="section-title">Doctor Dashboard</h2>
        <section class="dashboard-links">
            <a class="dashboard-link" href="manage-queue.php">
                <h3> Patient Queue</h3>
                <p>Review the queue and update consultation status.</p>
            </a>
            <a class="dashboard-link" href="queue-status.php">
                <h3> Queue Status</h3>
                <p>Check current patient queue status.</p>
            </a>
        </section>

    <?php elseif ($role === "Volunteer"): ?>
        <h2 class="section-title">Volunteer Dashboard</h2>
        <section class="dashboard-links">
            <a class="dashboard-link" href="register.php">
                <h3> Patient Registration</h3>
                <p>Register patients at the camp counter.</p>
            </a>
            <a class="dashboard-link" href="queue-status.php">
                <h3> Queue Status</h3>
                <p>Check patient tokens and queue status.</p>
            </a>
            <a class="dashboard-link" href="volunteer-list.php">
                <h3> Volunteer List</h3>
                <p>View volunteer information.</p>
            </a>
        </section>

    <?php else: ?>
        <p class="dashboard-message">
            Your account role is not recognized. Please contact the administrator.
        </p>
    <?php endif; ?>

</main>
</body>
</html>
